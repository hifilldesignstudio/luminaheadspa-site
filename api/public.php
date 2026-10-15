<?php
/* Lumina Booking System — public API (no login).
   GET  ?action=health
   GET  ?action=treatments
   GET  ?action=availability&date=YYYY-MM-DD&minutes=150&units=1[&exclude=bookingId]
   POST ?action=book            JSON {name, phone, email, note, date, start, items:[treatmentId,...]}
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
    lumina_json(['ok' => true, 'live' => $installed]);
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
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    lumina_json(['ok' => true, 'starts' => lumina_available_starts($pdo, $date, $minutes, $units, $exclude)]);
}

if ($action === 'book') {
    $in = body_json();
    $name = trim((string)($in['name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $note = trim((string)($in['note'] ?? ''));
    $date = (string)($in['date'] ?? '');
    $start = (int)($in['start'] ?? -1);
    $ids = array_slice(array_map('intval', (array)($in['items'] ?? [])), 0, 5);
    if ($name === '' || $phone === '' || !$ids || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        lumina_json(['ok' => false, 'error' => 'Please complete your name, phone, treatment, date and time.'], 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        lumina_json(['ok' => false, 'error' => 'That email looks incomplete — please check it, or leave it blank.'], 422);
    }
    $ph = $pdo->prepare("SELECT * FROM treatments WHERE id = ? AND active = 1");
    $items = [];
    foreach ($ids as $id) { $ph->execute([$id]); $t = $ph->fetch(); if (!$t) lumina_json(['ok' => false, 'error' => 'One of the treatments is no longer available.'], 422); $items[] = $t; }
    $totalMin = 0; $totalPrice = 0; $units = 1;
    foreach ($items as $t) { $totalMin += (int)$t['minutes']; $totalPrice += (int)$t['price']; $units = max($units, (int)$t['units']); }

    /* lock, re-check availability, insert — so two guests cannot take the last spot at once */
    $pdo->query("SELECT GET_LOCK('lumina_book', 5)");
    try {
        $starts = lumina_available_starts($pdo, $date, $totalMin, $units);
        if (!in_array($start, $starts, true)) {
            $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
            lumina_json(['ok' => false, 'error' => 'That time was just taken — please pick another time.'], 409);
        }
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $token = bin2hex(random_bytes(24));
        $end = $start + $totalMin;
        $ins = $pdo->prepare("INSERT INTO bookings (ref, name, phone, email, note, date, start_min, end_min, total_min, total_price, units, status, source, token, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'online', ?, ?)");
        $ins->execute([$ref, $name, $phone, $email, $note, $date, $start, $end, $totalMin, $totalPrice, $units, $token, lumina_now()->format('Y-m-d H:i:s')]);
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
    $shop = $cfg['shop_email'] ?? 'info@luminaheadspa.ca';
    lumina_mail($shop, "New booking $ref — $name, $when",
        "New online booking (reference $ref)\n\nGuest: $name\nPhone: $phone\nEmail: " . ($email ?: '(not given)') .
        "\nDate: $when\n" . implode("\n", $lines) . "\nTotal: $$totalPrice · $totalMin min" .
        ($note !== '' ? "\nGuest note: $note" : '') .
        "\n\nStatus: pending — please confirm it in the admin: https://luminaheadspa.ca/admin/");
    if ($email !== '') {
        lumina_mail($email, "Your Lumina booking request — $ref",
            "Hi $name,\n\nThank you for booking with Lumina Head Spa & Beauty.\nWe have received your request and will confirm shortly.\n\nReference: $ref\nDate: $when\n" .
            implode("\n", $lines) . "\nTotal: $$totalPrice" .
            "\n\nNeed to change or cancel? Use your private link (no login needed):\n$manageUrl" .
            "\n\nLumina Head Spa & Beauty · 9505 Keele Street, Unit 1A, Maple · 647-368-8148");
    }
    lumina_json(['ok' => true, 'ref' => $ref, 'manageUrl' => $manageUrl,
                 'summary' => ['date' => $date, 'dayLabel' => $when, 'lines' => $lines, 'totalPrice' => $totalPrice, 'totalMin' => $totalMin]]);
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
    $shop = $cfg['shop_email'] ?? 'info@luminaheadspa.ca';
    lumina_mail($shop, "Website message from $name",
        "New message from the Lumina website contact form:\n\nName: $name\nEmail: $email\nPhone: " . ($phone ?: '(not given)') . "\n\n$msg\n\n— Just reply to this email to answer them.", $email);
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
    $shop = $cfg['shop_email'] ?? 'info@luminaheadspa.ca';
    lumina_mail($shop, "Booking cancelled — {$b['ref']}",
        "The guest cancelled booking {$b['ref']}.\n\nGuest: {$b['name']} ({$b['phone']})\nWas: " . lumina_fmt_day($b['date']) . ', ' . lumina_fmt_time((int)$b['start_min']) . "\nThe time slot is now free again.");
    lumina_json(['ok' => true, 'status' => 'cancelled']);
}

if ($action === 'manage_reschedule') {
    $in = body_json();
    $b = find_by_token($pdo, (string)($in['token'] ?? ''));
    if ($b['status'] === 'cancelled') lumina_json(['ok' => false, 'error' => 'This booking was cancelled.'], 422);
    $date = (string)($in['date'] ?? '');
    $start = (int)($in['start'] ?? -1);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    $pdo->query("SELECT GET_LOCK('lumina_book', 5)");
    try {
        $starts = lumina_available_starts($pdo, $date, (int)$b['total_min'], (int)$b['units'], (int)$b['id']);
        if (!in_array($start, $starts, true)) {
            lumina_json(['ok' => false, 'error' => 'That time is not available — please pick another.'], 409);
        }
        $end = $start + (int)$b['total_min'];
        $pdo->prepare("UPDATE bookings SET date = ?, start_min = ?, end_min = ? WHERE id = ?")->execute([$date, $start, $end, $b['id']]);
        /* shift each item by the same delta */
        $delta = $start - (int)$b['start_min'];
        $pdo->prepare("UPDATE booking_items SET start_min = start_min + ?, end_min = end_min + ? WHERE booking_id = ?")->execute([$delta, $delta, $b['id']]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
    }
    $shop = $cfg['shop_email'] ?? 'info@luminaheadspa.ca';
    $lines = lumina_booking_lines($pdo, (int)$b['id']);
    lumina_mail($shop, "Booking rescheduled — {$b['ref']}",
        "Booking {$b['ref']} was moved by the guest.\n\nGuest: {$b['name']} ({$b['phone']})\nNew time: " . lumina_fmt_day($date) . "\n" . implode("\n", $lines));
    if ($b['email'] !== '') {
        lumina_mail($b['email'], "Your Lumina booking was moved — {$b['ref']}",
            "Hi {$b['name']},\n\nYour booking {$b['ref']} is now:\n" . lumina_fmt_day($date) . "\n" . implode("\n", $lines) .
            "\n\nLumina Head Spa & Beauty · 647-368-8148");
    }
    lumina_json(['ok' => true, 'date' => $date, 'dayLabel' => lumina_fmt_day($date), 'lines' => $lines]);
}

lumina_json(['ok' => false, 'error' => 'Unknown action'], 400);
