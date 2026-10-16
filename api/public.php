<?php
/* Lumina Booking System — public API (no login).
   GET  ?action=health
   GET  ?action=treatments
   GET  ?action=availability&date=YYYY-MM-DD&minutes=150&units=1[&exclude=bookingId][&techId=N]
   POST ?action=book            JSON {name, phone, email, note, date, start, items:[treatmentId,...], techId?}
   (techId + staff responses only apply when settings.booking_mode = 'staff')
   GET  ?action=manage_get&token=...
   POST ?action=manage_cancel   JSON {token}
   POST ?action=manage_reschedule JSON {token, date, start} */
require __DIR__ . '/lib.php';

$action = $_GET['action'] ?? '';
$pdo = lumina_db();
$cfg = lumina_config();

if ($action === 'health') {
    $installed = false;
    if ($pdo) { try { $installed = (bool)$pdo->query("SHOW TABLES LIKE 'settings'")->fetch(); } catch (Exception $e) {} }
    $resp = ['ok' => true, 'live' => $installed];
    if ($installed && $pdo && lumina_mode($pdo) === 'staff') {
        $resp['mode'] = 'staff';
        $resp['techs'] = [];
        foreach (lumina_techs($pdo) as $t) $resp['techs'][] = ['id' => (int)$t['id'], 'name' => $t['name']];
    }
    lumina_json($resp);
}
if (!$pdo) lumina_json(['ok' => false, 'error' => 'Booking is not available right now. Please call us at 647-368-8148.'], 503);

function body_json() {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

if ($action === 'treatments') {
    $rows = $pdo->query("SELECT id, name, cat, minutes, price, units FROM treatments WHERE active = 1 ORDER BY sort, id")->fetchAll();
    lumina_json(['ok' => true, 'treatments' => $rows]);
}

if ($action === 'availability') {
    $date = $_GET['date'] ?? '';
    $minutes = max(1, (int)($_GET['minutes'] ?? 60));
    $units = max(1, (int)($_GET['units'] ?? 1));
    $exclude = (int)($_GET['exclude'] ?? 0);
    $techId = (int)($_GET['techId'] ?? 0);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    $r = lumina_available_starts_staff($pdo, $date, $minutes, $units, $techId, $exclude);
    lumina_json(['ok' => true, 'starts' => $r['starts'], 'byStart' => $r['byStart'], 'techs' => $r['techs']]);
}

if ($action === 'book') {
    $in = body_json();
    $name = trim((string)($in['name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $note = trim((string)($in['note'] ?? ''));
    $date = (string)($in['date'] ?? '');
    $start = (int)($in['start'] ?? -1);
    $techId = (int)($in['techId'] ?? 0);
    $ids = array_slice(array_map('intval', (array)($in['items'] ?? [])), 0, 5);
    if ($name === '' || $phone === '' || !$ids || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        lumina_json(['ok' => false, 'error' => 'Please complete your name, phone, treatment, date and time.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        lumina_json(['ok' => false, 'error' => 'Please add your email — your confirmation and booking link are sent there.'], 422);
    }
    $ph = $pdo->prepare("SELECT * FROM treatments WHERE id = ? AND active = 1");
    $items = [];
    foreach ($ids as $id) { $ph->execute([$id]); $t = $ph->fetch(); if (!$t) lumina_json(['ok' => false, 'error' => 'One of the treatments is no longer available.'], 422); $items[] = $t; }
    $totalMin = 0; $totalPrice = 0; $units = 1;
    foreach ($items as $t) { $totalMin += (int)$t['minutes']; $totalPrice += (int)$t['price']; $units = max($units, (int)$t['units']); }

    /* lock, re-check availability, insert — so two guests cannot take the last spot at once */
    $asTech = null; $asTech2 = null;
    $pdo->query("SELECT GET_LOCK('lumina_book', 5)");
    try {
        if ($techId > 0) {
            /* Named pick: assign that technician now (strict). */
            $as = lumina_staff_assign($pdo, $date, $start, $totalMin, $units, $techId);
            if (!$as) {
                $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
                lumina_json(['ok' => false, 'error' => 'That time is no longer available for your chosen therapist — please pick another time, or choose “No preference”.'], 409);
            }
            $asTech = $as['tech_id']; $asTech2 = $as['tech2_id'];
        } else {
            /* No preference: the booking waits UNASSIGNED — the shop assigns
               a technician when they confirm. Feasibility only checks that
               the team can cover it alongside everything else booked. */
            if (!lumina_team_feasible($pdo, $date, $start, $start + $totalMin, $units)) {
                $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
                lumina_json(['ok' => false, 'error' => 'That time was just taken — please pick another time.'], 409);
            }
        }
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $token = bin2hex(random_bytes(24));
        $end = $start + $totalMin;
        $ins = $pdo->prepare("INSERT INTO bookings (ref, name, phone, email, note, date, start_min, end_min, total_min, total_price, units, tech_id, tech2_id, tech_pref, status, source, token, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'online', ?, ?)");
        $ins->execute([$ref, $name, $phone, $email, $note, $date, $start, $end, $totalMin, $totalPrice, $units, $asTech, $asTech2, $techId > 0 ? 1 : 0, $token, lumina_now()->format('Y-m-d H:i:s')]);
        $bid = (int)$pdo->lastInsertId();
        $ii = $pdo->prepare("INSERT INTO booking_items (booking_id, treatment_id, name_snapshot, minutes, price, start_min, end_min) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $t = $start;
        foreach ($items as $it) { $ii->execute([$bid, (int)$it['id'], $it['name'], (int)$it['minutes'], (int)$it['price'], $t, $t + (int)$it['minutes']]); $t += (int)$it['minutes']; }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
    }

    $lines = lumina_booking_lines($pdo, $bid);
    $when = lumina_fmt_day($date);
    $manageUrl = 'https://luminaheadspa.ca/manage/?t=' . $token;
    $withLine = lumina_with_line($pdo, ['tech_id' => $asTech, 'tech2_id' => $asTech2]);
    lumina_notify($pdo, "New booking $ref — $name, $when",
        "New online booking (reference $ref)\n\nGuest: $name\nPhone: $phone\nEmail: " . ($email ?: '(not given)') .
        "\nDate: $when\n" . implode("\n", $lines) . $withLine . "\nTotal: $$totalPrice · $totalMin min" .
        ($note !== '' ? "\nGuest note: $note" : '') .
        "\n\nStatus: pending — please confirm it in the admin: https://luminaheadspa.ca/admin/",
        null, [$asTech, $asTech2]);
    if ($email !== '') {
        lumina_mail($email, "Your Lumina booking request — $ref",
            "Hi $name,\n\nThank you for booking with Lumina Head Spa & Beauty.\nWe have received your request and will confirm shortly.\n\nReference: $ref\nDate: $when\n" .
            implode("\n", $lines) . $withLine . "\nTotal: $$totalPrice" .
            "\n\nNeed to change or cancel? Use your private link (no login needed):\n$manageUrl" .
            "\n\nLumina Head Spa & Beauty · 9505 Keele Street, Unit 1A, Maple · 647-368-8148");
    }
    $summary = ['date' => $date, 'dayLabel' => $when, 'lines' => $lines, 'totalPrice' => $totalPrice, 'totalMin' => $totalMin];
    if ($withLine !== '') $summary['withTechs'] = trim(substr($withLine, strlen("\nWith: ")));
    lumina_json(['ok' => true, 'ref' => $ref, 'manageUrl' => $manageUrl, 'summary' => $summary]);
}

/* ---------- contact form (About & Contact page) ---------- */
if ($action === 'contact') {
    $in = body_json();
    if (!empty($in['website'])) lumina_json(['ok' => true]); /* honeypot: bots get a fake success */
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 120);
    $email = trim((string)($in['email'] ?? ''));
    $phone = mb_substr(trim((string)($in['phone'] ?? '')), 0, 40);
    $msg = mb_substr(trim((string)($in['message'] ?? '')), 0, 4000);
    if ($name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        lumina_json(['ok' => false, 'error' => 'Please add your name, a valid email and your message.'], 422);
    }
    try {
        $pdo->prepare("INSERT INTO messages (name, email, phone, message, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $email, $phone, $msg, lumina_now()->format('Y-m-d H:i:s')]);
    } catch (Exception $e) { /* the email below still goes out */ }
    lumina_notify($pdo, "Website message from $name",
        "New message from the Lumina website contact form:\n\nName: $name\nEmail: $email\nPhone: " . ($phone ?: '(not given)') . "\n\n$msg\n\n— Just reply to this email to answer them.", $email, []);
    lumina_json(['ok' => true]);
}

/* ---------- customer self-manage ---------- */
function find_by_token($pdo, $token) {
    $st = $pdo->prepare("SELECT * FROM bookings WHERE token = ?");
    $st->execute([$token]);
    $b = $st->fetch();
    if (!$b) lumina_json(['ok' => false, 'error' => 'Booking not found.'], 404);
    return $b;
}

if ($action === 'manage_get') {
    $b = find_by_token($pdo, (string)($_GET['token'] ?? ''));
    lumina_json(['ok' => true, 'booking' => [
        'id' => (int)$b['id'], 'ref' => $b['ref'], 'name' => $b['name'], 'date' => $b['date'], 'dayLabel' => lumina_fmt_day($b['date']),
        'start_min' => (int)$b['start_min'], 'end_min' => (int)$b['end_min'],
        'totalPrice' => (int)$b['total_price'], 'totalMin' => (int)$b['total_min'], 'units' => (int)$b['units'],
        'status' => $b['status'], 'lines' => lumina_booking_lines($pdo, (int)$b['id']),
    ]]);
}

if ($action === 'manage_cancel') {
    $in = body_json();
    $b = find_by_token($pdo, (string)($in['token'] ?? ''));
    if ($b['status'] === 'cancelled') lumina_json(['ok' => true, 'status' => 'cancelled']);
    $set = lumina_settings($pdo);
    $startDT = DateTime::createFromFormat('Y-m-d H:i', $b['date'] . ' ' . sprintf('%02d:%02d', intdiv((int)$b['start_min'], 60), (int)$b['start_min'] % 60), lumina_tz());
    $hoursLeft = ($startDT->getTimestamp() - lumina_now()->getTimestamp()) / 3600;
    if ($hoursLeft < (float)$set['cancel_cutoff_hours']) {
        lumina_json(['ok' => false, 'error' => 'It is too close to your appointment to cancel online — please call us at 647-368-8148 and we will help.'], 422);
    }
    $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$b['id']]);
    lumina_notify($pdo, "Booking cancelled — {$b['ref']}",
        "The guest cancelled booking {$b['ref']}.\n\nGuest: {$b['name']} ({$b['phone']})\nWas: " . lumina_fmt_day($b['date']) . ', ' . lumina_fmt_time((int)$b['start_min']) . "\nThe time slot is now free again.",
        null, [!empty($b['tech_id']) ? (int)$b['tech_id'] : 0, !empty($b['tech2_id']) ? (int)$b['tech2_id'] : 0]);
    lumina_json(['ok' => true, 'status' => 'cancelled']);
}

if ($action === 'manage_reschedule') {
    $in = body_json();
    $b = find_by_token($pdo, (string)($in['token'] ?? ''));
    if ($b['status'] === 'cancelled') lumina_json(['ok' => false, 'error' => 'This booking was cancelled.'], 422);
    $date = (string)($in['date'] ?? '');
    $start = (int)($in['start'] ?? -1);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    /* Pre-change assignees: if the reschedule releases the booking, the
       technician who loses it is still notified (union built at send time). */
    $preTechs = [!empty($b['tech_id']) ? (int)$b['tech_id'] : 0, !empty($b['tech2_id']) ? (int)$b['tech2_id'] : 0];
    $pdo->query("SELECT GET_LOCK('lumina_book', 5)");
    try {
        $end = $start + (int)$b['total_min'];
        /* Named picks keep their therapist strictly. Any other booking goes
           back to unassigned at the new time — the shop re-assigns. */
        $named = !empty($b['tech_pref']) && !empty($b['tech_id']);
        if ($named) {
            $as = lumina_staff_assign($pdo, $date, $start, (int)$b['total_min'], (int)$b['units'], (int)$b['tech_id'], (int)$b['id']);
            if (!$as) {
                $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
                lumina_json(['ok' => false, 'error' => 'Your therapist is not available at that time — please pick another time, or call us at 647-368-8148 and we will help.'], 409);
            }
            $pdo->prepare("UPDATE bookings SET date = ?, start_min = ?, end_min = ?, tech_id = ?, tech2_id = ? WHERE id = ?")
                ->execute([$date, $start, $end, $as['tech_id'], $as['tech2_id'], $b['id']]);
            $b['tech_id'] = $as['tech_id']; $b['tech2_id'] = $as['tech2_id'];
        } else {
            if (!lumina_team_feasible($pdo, $date, $start, $end, (int)$b['units'], (int)$b['id'])) {
                $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
                lumina_json(['ok' => false, 'error' => 'That time is not available — please pick another.'], 409);
            }
            $pdo->prepare("UPDATE bookings SET date = ?, start_min = ?, end_min = ?, tech_id = NULL, tech2_id = NULL WHERE id = ?")
                ->execute([$date, $start, $end, $b['id']]);
            $b['tech_id'] = null; $b['tech2_id'] = null;
        }
        /* shift each item by the same delta */
        $delta = $start - (int)$b['start_min'];
        $pdo->prepare("UPDATE booking_items SET start_min = start_min + ?, end_min = end_min + ? WHERE booking_id = ?")->execute([$delta, $delta, $b['id']]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
    }
    $lines = lumina_booking_lines($pdo, (int)$b['id']);
    $withLine = lumina_with_line($pdo, $b);
    lumina_notify($pdo, "Booking rescheduled — {$b['ref']}",
        "Booking {$b['ref']} was moved by the guest.\n\nGuest: {$b['name']} ({$b['phone']})\nNew time: " . lumina_fmt_day($date) . "\n" . implode("\n", $lines) . $withLine,
        null, array_merge($preTechs, [!empty($b['tech_id']) ? (int)$b['tech_id'] : 0, !empty($b['tech2_id']) ? (int)$b['tech2_id'] : 0]));
    if ($b['email'] !== '') {
        lumina_mail($b['email'], "Your Lumina booking was moved — {$b['ref']}",
            "Hi {$b['name']},\n\nYour booking {$b['ref']} is now:\n" . lumina_fmt_day($date) . "\n" . implode("\n", $lines) . $withLine .
            "\n\nLumina Head Spa & Beauty · 647-368-8148");
    }
    lumina_json(['ok' => true, 'date' => $date, 'dayLabel' => lumina_fmt_day($date), 'lines' => $lines]);
}

lumina_json(['ok' => false, 'error' => 'Unknown action'], 400);
