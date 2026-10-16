<?php
/* Lumina Booking System — admin API. Session login with the password from the
   config file. All actions except login/me need a session. */
require __DIR__ . '/lib.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

$action = $_GET['action'] ?? '';
$cfg = lumina_config();
$pdo = lumina_db();
if (!$pdo) lumina_json(['ok' => false, 'error' => 'Backend not installed yet.'], 503);

function body_json() { $d = json_decode(file_get_contents('php://input'), true); return is_array($d) ? $d : []; }
function need_login() { if (empty($_SESSION['lumina_admin'])) lumina_json(['ok' => false, 'error' => 'Not logged in'], 401); }

if ($action === 'me') lumina_json(['ok' => true, 'loggedIn' => !empty($_SESSION['lumina_admin'])]);

if ($action === 'login') {
    $in = body_json();
    $pass = (string)($in['password'] ?? '');
    if ($cfg && hash_equals((string)($cfg['admin_pass'] ?? ''), $pass) && $pass !== '' && $pass !== 'CHANGE_ME') {
        $_SESSION['lumina_admin'] = true;
        lumina_json(['ok' => true]);
    }
    lumina_json(['ok' => false, 'error' => 'Wrong password'], 401);
}
if ($action === 'logout') { $_SESSION = []; session_destroy(); lumina_json(['ok' => true]); }

need_login();

function booking_full($pdo, $b) {
    static $techMap = null;
    if ($techMap === null) {
        $techMap = [];
        try { foreach ($pdo->query("SELECT id, name FROM technicians")->fetchAll() as $t) $techMap[(int)$t['id']] = $t['name']; } catch (Exception $e) {}
    }
    $b['techName'] = (!empty($b['tech_id']) && isset($techMap[(int)$b['tech_id']])) ? $techMap[(int)$b['tech_id']] : null;
    $b['tech2Name'] = (!empty($b['tech2_id']) && isset($techMap[(int)$b['tech2_id']])) ? $techMap[(int)$b['tech2_id']] : null;
    /* For an unassigned booking, suggest a technician (least-loaded free
       one) so the admin can confirm-and-assign in one tap. */
    $b['suggest'] = null;
    if (empty($b['tech_id']) && in_array($b['status'], ['pending', 'confirmed'], true)) {
        try {
            $as = lumina_staff_assign($pdo, $b['date'], (int)$b['start_min'], (int)$b['total_min'], max(1, (int)$b['units']), 0, (int)$b['id']);
            if ($as) $b['suggest'] = $as['tech_id'];
        } catch (Exception $e) {}
    }
    $st = $pdo->prepare("SELECT name_snapshot, minutes, price, start_min, end_min FROM booking_items WHERE booking_id = ? ORDER BY start_min");
    $st->execute([$b['id']]);
    $items = $st->fetchAll();
    foreach ($items as &$it) { $it['startLabel'] = lumina_fmt_time((int)$it['start_min']); $it['endLabel'] = lumina_fmt_time((int)$it['end_min']); }
    unset($it);
    $b['items'] = $items;
    $b['dayLabel'] = lumina_fmt_day($b['date']);
    $b['startLabel'] = lumina_fmt_time((int)$b['start_min']);
    $b['endLabel'] = lumina_fmt_time((int)$b['end_min']);
    return $b;
}

if ($action === 'bookings') {
    $where = []; $args = [];
    if (!empty($_GET['status'])) { $where[] = "status = ?"; $args[] = $_GET['status']; }
    if (!empty($_GET['date'])) { $where[] = "date = ?"; $args[] = $_GET['date']; }
    if (!empty($_GET['q'])) { $where[] = "(name LIKE ? OR phone LIKE ? OR ref LIKE ?)"; $q = '%' . $_GET['q'] . '%'; $args = array_merge($args, [$q, $q, $q]); }
    if (!empty($_GET['from'])) { $where[] = "date >= ?"; $args[] = $_GET['from']; }
    $sql = "SELECT * FROM bookings" . ($where ? " WHERE " . implode(' AND ', $where) : "")
         . " ORDER BY " . (!empty($_GET['from']) ? "date, start_min" : "(status='pending') DESC, date, start_min") . " LIMIT 300";
    $st = $pdo->prepare($sql); $st->execute($args);
    lumina_json(['ok' => true, 'bookings' => array_map(fn($b) => booking_full($pdo, $b), $st->fetchAll())]);
}

if ($action === 'month_counts') {
    $ym = $_GET['ym'] ?? lumina_now()->format('Y-m');
    $st = $pdo->prepare("SELECT date, COUNT(*) total, SUM(status='pending') pend FROM bookings WHERE date LIKE ? AND status <> 'cancelled' GROUP BY date");
    $st->execute([$ym . '-%']);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[$r['date']] = ['total' => (int)$r['total'], 'pending' => (int)$r['pend']];
    lumina_json(['ok' => true, 'counts' => $out]);
}

if ($action === 'month_report') {
    /* Monthly record: aggregates + the month's booking rows (for the CSV
       download). Records are kept permanently; this is the shop's monthly
       bookkeeping view. Revenue/counts exclude cancelled bookings except
       where stated. */
    $ym = $_GET['ym'] ?? lumina_now()->format('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', (string)$ym)) lumina_json(['ok' => false, 'error' => 'Bad month'], 400);
    $like = $ym . '-%';
    $techMap = [];
    try { foreach ($pdo->query("SELECT id, name FROM technicians")->fetchAll() as $t) $techMap[(int)$t['id']] = $t['name']; } catch (Exception $e) {}
    $st = $pdo->prepare("SELECT * FROM bookings WHERE date LIKE ? ORDER BY date, start_min LIMIT 1000");
    $st->execute([$like]);
    $rows = $st->fetchAll();
    $counts = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'done' => 0, 'cancelled' => 0];
    $revenue = 0; $guests = 0;
    $perTech = []; $ids = [];
    foreach ($rows as $b) {
        $counts['total']++;
        if (isset($counts[$b['status']])) $counts[$b['status']]++;
        if ($b['status'] !== 'cancelled') {
            $revenue += (int)$b['total_price']; $guests++;
            $ids[] = (int)$b['id'];
            foreach (['tech_id', 'tech2_id'] as $k) {
                if (!empty($b[$k])) {
                    $tid = (int)$b[$k];
                    if (!isset($perTech[$tid])) $perTech[$tid] = ['name' => $techMap[$tid] ?? ('#' . $tid), 'bookings' => 0, 'minutes' => 0];
                    $perTech[$tid]['bookings']++; $perTech[$tid]['minutes'] += (int)$b['total_min'];
                }
            }
        }
    }
    $perTreatment = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        foreach ($pdo->query("SELECT name_snapshot, COUNT(*) c FROM booking_items WHERE booking_id IN ($in) GROUP BY name_snapshot ORDER BY c DESC, name_snapshot") as $r) {
            $perTreatment[] = ['name' => $r['name_snapshot'], 'count' => (int)$r['c']];
        }
    }
    $out = [];
    $itemSt = $pdo->prepare("SELECT name_snapshot FROM booking_items WHERE booking_id = ? ORDER BY start_min");
    foreach ($rows as $b) {
        $itemSt->execute([(int)$b['id']]);
        $names = array_map(fn($r) => $r['name_snapshot'], $itemSt->fetchAll());
        $out[] = [
            'date' => $b['date'], 'time' => lumina_fmt_time((int)$b['start_min']), 'ref' => $b['ref'],
            'name' => $b['name'], 'phone' => $b['phone'], 'email' => $b['email'],
            'treatments' => implode(' + ', $names), 'total' => (int)$b['total_price'],
            'status' => $b['status'], 'source' => $b['source'],
            'therapist' => implode(' & ', array_filter([
                !empty($b['tech_id']) ? ($techMap[(int)$b['tech_id']] ?? '') : '',
                !empty($b['tech2_id']) ? ($techMap[(int)$b['tech2_id']] ?? '') : '',
            ])),
        ];
    }
    lumina_json(['ok' => true, 'ym' => $ym, 'counts' => $counts, 'revenue' => $revenue, 'bookingsServed' => $guests,
                 'perTreatment' => $perTreatment, 'perTech' => array_values($perTech), 'rows' => $out]);
}

if ($action === 'booking_status') {
    $in = body_json();
    $id = (int)($in['id'] ?? 0);
    $status = (string)($in['status'] ?? '');
    if (!in_array($status, ['confirmed', 'cancelled', 'done', 'pending'], true)) lumina_json(['ok' => false, 'error' => 'Bad status'], 400);
    $st = $pdo->prepare("SELECT * FROM bookings WHERE id = ?"); $st->execute([$id]); $b = $st->fetch();
    if (!$b) lumina_json(['ok' => false, 'error' => 'Not found'], 404);
    if ($status === 'confirmed' && $b['status'] === 'cancelled') {
        /* Restoring a cancelled booking: only if the time still fits the
           team — it may have been taken since the cancellation. */
        lumina_ensure_modelb($pdo);
        $pdo->query("SELECT GET_LOCK('lumina_book', 5)");
        try {
            if (!empty($b['tech_id'])) {
                $as = lumina_staff_assign($pdo, $b['date'], (int)$b['start_min'], (int)$b['total_min'], max(1, (int)$b['units']), (int)$b['tech_id'], $id);
                if (!$as) lumina_json(['ok' => false, 'error' => 'That time has been taken since the cancellation, so this booking cannot be restored as-is.'], 409);
                $pdo->prepare("UPDATE bookings SET tech_id = ?, tech2_id = ? WHERE id = ?")->execute([$as['tech_id'], $as['tech2_id'], $id]);
            } else {
                if (!lumina_team_feasible($pdo, $b['date'], (int)$b['start_min'], (int)$b['start_min'] + (int)$b['total_min'], max(1, (int)$b['units']), $id)) {
                    lumina_json(['ok' => false, 'error' => 'That time has been taken since the cancellation, so this booking cannot be restored as-is.'], 409);
                }
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
        }
    }
    $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$status, $id]);
    if ($status === 'cancelled' && $b['source'] === 'online' && $b['email'] !== '') {
        lumina_mail($b['email'], "Your Lumina booking {$b['ref']} was cancelled",
            "Hi {$b['name']},\n\nWe're sorry — your booking {$b['ref']} on " . lumina_fmt_day($b['date']) . " at " . lumina_fmt_time((int)$b['start_min']) .
            " has been cancelled by the shop. Please call us at 647-368-8148 and we will gladly rebook you.\n\nLumina Head Spa & Beauty");
    }
    if ($status === 'confirmed' && $b['status'] !== 'confirmed' && $b['email'] !== '') {
        $lines = lumina_booking_lines($pdo, (int)$b['id']);
        $withLine = lumina_with_line($pdo, $b);
        $manageUrl = 'https://luminaheadspa.ca/manage/?t=' . $b['token'];
        lumina_mail($b['email'], "Your Lumina booking is confirmed — {$b['ref']}",
            "Hi {$b['name']},\n\nGood news — your booking is confirmed. We look forward to seeing you!\n\nReference: {$b['ref']}\nDate: " . lumina_fmt_day($b['date']) . "\n" .
            implode("\n", $lines) . $withLine . "\nTotal: $" . (int)$b['total_price'] .
            "\n\nNeed to change or cancel? Use your private link (no login needed):\n$manageUrl" .
            "\n\nLumina Head Spa & Beauty · 9505 Keele Street, Unit 1A, Maple · 647-368-8148");
    }
    lumina_json(['ok' => true]);
}

if ($action === 'tech_day_bookings') {
    /* One technician's bookings for one day — the admin can print the list
       or email it to the technician. Cancelled bookings are excluded. */
    lumina_ensure_modelb($pdo);
    $techId = (int)($_GET['techId'] ?? 0);
    $date = (string)($_GET['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    $st = $pdo->prepare("SELECT * FROM technicians WHERE id = ?"); $st->execute([$techId]); $t = $st->fetch();
    if (!$t) lumina_json(['ok' => false, 'error' => 'Technician not found'], 404);
    $set = lumina_settings($pdo);
    $w = lumina_tech_window($pdo, $t, $date, $set);
    $q = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND status IN ('pending','confirmed','done') AND (tech_id = ? OR tech2_id = ?) ORDER BY start_min, id");
    $q->execute([$date, $techId, $techId]);
    $rows = [];
    foreach ($q->fetchAll() as $b) $rows[] = booking_full($pdo, $b);
    lumina_json(['ok' => true, 'tech' => ['id' => (int)$t['id'], 'name' => $t['name'], 'email' => $t['email'] ?? ''], 'window' => $w, 'dayLabel' => lumina_fmt_day($date), 'bookings' => $rows]);
}

if ($action === 'tech_day_email') {
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $techId = (int)($in['techId'] ?? 0);
    $date = (string)($in['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    $st = $pdo->prepare("SELECT * FROM technicians WHERE id = ?"); $st->execute([$techId]); $t = $st->fetch();
    if (!$t) lumina_json(['ok' => false, 'error' => 'Technician not found'], 404);
    if (empty($t['email'])) lumina_json(['ok' => false, 'error' => 'No email on file for this technician — add one above first.'], 422);
    $set = lumina_settings($pdo);
    $w = lumina_tech_window($pdo, $t, $date, $set);
    $q = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND status IN ('pending','confirmed','done') AND (tech_id = ? OR tech2_id = ?) ORDER BY start_min, id");
    $q->execute([$date, $techId, $techId]);
    $day = lumina_fmt_day($date);
    $body = "Hi {$t['name']},\n\nYour Lumina bookings for $day\nWorking hours: " . ($w ? lumina_fmt_time($w[0]) . ' – ' . lumina_fmt_time($w[1]) : 'Day off') . "\n";
    $rows = $q->fetchAll();
    if (!$rows) {
        $body .= "\nNo bookings this day.\n";
    } else {
        foreach ($rows as $b) {
            $body .= "\n" . lumina_fmt_time((int)$b['start_min']) . ' – ' . lumina_fmt_time((int)$b['start_min'] + (int)$b['total_min']) . " · {$b['name']} · {$b['phone']}";
            if ($b['status'] !== 'confirmed') $body .= $b['status'] === 'pending' ? ' · WAITING FOR CONFIRMATION' : ' · done';
            $body .= "\n";
            foreach (lumina_booking_lines($pdo, (int)$b['id']) as $ln) $body .= "  $ln\n";
            if (!empty($b['note'])) $body .= "  Note: {$b['note']}\n";
        }
    }
    $body .= "\nLumina Head Spa & Beauty · 9505 Keele Street, Unit 1A, Maple · 647-368-8148\nSent from the Lumina booking admin.";
    lumina_mail($t['email'], "Your Lumina schedule — $day", $body);
    lumina_json(['ok' => true]);
}

if ($action === 'phone_availability') {
    /* Month grid for the Phone Booking tab: how many feasible start times
       each day still has for a chain of $minutes needing $units (and, in
       staff mode, for one named technician when techId > 0). Read-only —
       the phone_booking submit still revalidates. State precedence follows
       the order below: past, then blocked, then outside the booking
       window, then the computed counts. Uses the same engines as booking:
       lumina_available_starts (pool) / lumina_available_starts_staff. */
    $ym = (string)($_GET['ym'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) lumina_json(['ok' => false, 'error' => 'Bad month'], 400);
    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01', lumina_tz());
    if (!$first || $first->format('Y-m') !== $ym) lumina_json(['ok' => false, 'error' => 'Bad month'], 400);
    $minutes = max(1, (int)($_GET['minutes'] ?? 60));
    $units = max(1, (int)($_GET['units'] ?? 1));
    $techId = (int)($_GET['techId'] ?? 0);
    lumina_ensure_modelb($pdo);
    $set = lumina_settings($pdo);
    $today = lumina_now()->format('Y-m-d');
    $last = lumina_now()->modify('+' . (int)$set['window_days'] . ' days')->format('Y-m-d');
    $days = [];
    $dim = (int)$first->format('t');
    for ($d = 1; $d <= $dim; $d++) {
        $ds = $ym . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
        if ($ds < $today) { $days[$ds] = ['starts' => 0, 'state' => 'past']; continue; }
        if (lumina_blocked($pdo, $ds)) { $days[$ds] = ['starts' => 0, 'state' => 'blocked']; continue; }
        if ($ds > $last) { $days[$ds] = ['starts' => 0, 'state' => 'outside']; continue; }
        $n = count(lumina_available_starts_staff($pdo, $ds, $minutes, $units, $techId)['starts']);
        $days[$ds] = ['starts' => $n, 'state' => $n >= 6 ? 'many' : ($n >= 3 ? 'some' : ($n >= 1 ? 'few' : 'full'))];
    }
    lumina_json(['ok' => true, 'days' => $days]);
}

if ($action === 'phone_booking') {
    $in = body_json();
    $name = trim((string)($in['name'] ?? '')); $phone = trim((string)($in['phone'] ?? ''));
    $email = trim((string)($in['email'] ?? '')); $note = trim((string)($in['note'] ?? ''));
    $date = (string)($in['date'] ?? ''); $start = (int)($in['start'] ?? -1);
    $ids = array_slice(array_map('intval', (array)($in['items'] ?? [])), 0, 5);
    $force = !empty($in['force']);
    $techId = (int)($in['techId'] ?? 0);
    if ($name === '' || !$ids || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $start < 0) lumina_json(['ok' => false, 'error' => 'Name, treatment, date and time are required.'], 422);
    $ph = $pdo->prepare("SELECT * FROM treatments WHERE id = ?");
    $items = []; $totalMin = 0; $totalPrice = 0; $units = 1;
    foreach ($ids as $id) { $ph->execute([$id]); $t = $ph->fetch(); if (!$t) lumina_json(['ok' => false, 'error' => 'Unknown treatment'], 422); $items[] = $t; $totalMin += (int)$t['minutes']; $totalPrice += (int)$t['price']; $units = max($units, (int)$t['units']); }
    $asTech = null; $asTech2 = null;
    lumina_ensure_modelb($pdo);
    if ($techId > 0) {
        $as = lumina_staff_assign($pdo, $date, $start, $totalMin, $units, $techId, 0, $force);
        if (!$as && !$force) lumina_json(['ok' => false, 'full' => true, 'error' => 'That therapist is not free at this time. Save anyway (overbook)?'], 409);
        if ($as) { $asTech = $as['tech_id']; $asTech2 = $as['tech2_id']; }
    } elseif (!$force) {
        /* "Assign later": the booking is created unassigned; feasibility
           only checks the team can cover it. */
        if (!lumina_team_feasible($pdo, $date, $start, $start + $totalMin, $units)) lumina_json(['ok' => false, 'full' => true, 'error' => 'No therapist is free at this time. Save anyway (overbook)?'], 409);
    }
    $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $token = bin2hex(random_bytes(24));
    $ins = $pdo->prepare("INSERT INTO bookings (ref, name, phone, email, note, date, start_min, end_min, total_min, total_price, units, tech_id, tech2_id, tech_pref, status, source, token, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'phone', ?, ?)");
    $ins->execute([$ref, $name, $phone, $email, $note, $date, $start, $start + $totalMin, $totalMin, $totalPrice, $units, $asTech, $asTech2, $techId > 0 ? 1 : 0, $token, lumina_now()->format('Y-m-d H:i:s')]);
    $bid = (int)$pdo->lastInsertId();
    $ii = $pdo->prepare("INSERT INTO booking_items (booking_id, treatment_id, name_snapshot, minutes, price, start_min, end_min) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $t = $start;
    foreach ($items as $it) { $ii->execute([$bid, (int)$it['id'], $it['name'], (int)$it['minutes'], (int)$it['price'], $t, $t + (int)$it['minutes']]); $t += (int)$it['minutes']; }
    {
        /* The shop and the assigned technician(s) hear about the phone
           booking (unassigned ones notify the shop only). */
        $lines = lumina_booking_lines($pdo, $bid);
        $withLine = lumina_with_line($pdo, ['tech_id' => $asTech, 'tech2_id' => $asTech2]);
        lumina_notify($pdo, "New phone booking $ref — $name, " . lumina_fmt_day($date),
            "New phone booking (reference $ref)\n\nGuest: $name\nPhone: $phone\nEmail: " . ($email ?: '(not given)') .
            "\nDate: " . lumina_fmt_day($date) . "\n" . implode("\n", $lines) . $withLine . "\nTotal: $$totalPrice · $totalMin min" .
            ($note !== '' ? "\nGuest note: $note" : '') .
            "\n\nStatus: confirmed (taken by phone).",
            null, [$asTech, $asTech2]);
    }
    lumina_json(['ok' => true, 'ref' => $ref]);
}

if ($action === 'messages') {
    lumina_json(['ok' => true, 'messages' => $pdo->query("SELECT * FROM messages ORDER BY id DESC LIMIT 200")->fetchAll()]);
}
if ($action === 'message_seen') {
    $in = body_json();
    $pdo->prepare("UPDATE messages SET seen = ? WHERE id = ?")->execute([!empty($in['seen']) ? 1 : 0, (int)($in['id'] ?? 0)]);
    lumina_json(['ok' => true]);
}
if ($action === 'message_delete') {
    $in = body_json();
    $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([(int)($in['id'] ?? 0)]);
    lumina_json(['ok' => true]);
}

if ($action === 'treatments') {
    $rows = $pdo->query("SELECT * FROM treatments ORDER BY sort, id")->fetchAll();
    lumina_json(['ok' => true, 'treatments' => $rows]);
}
if ($action === 'treatment_save') {
    $in = body_json();
    $id = (int)($in['id'] ?? 0);
    $vals = [trim((string)$in['name']), trim((string)$in['cat']), (int)$in['minutes'], (int)$in['price'], (int)($in['units'] ?? 1), !empty($in['active']) ? 1 : 0, (int)($in['sort'] ?? 0)];
    if ($vals[0] === '' || $vals[2] <= 0) lumina_json(['ok' => false, 'error' => 'Name and minutes are required.'], 422);
    if ($id) { $vals[] = $id; $pdo->prepare("UPDATE treatments SET name=?, cat=?, minutes=?, price=?, units=?, active=?, sort=? WHERE id=?")->execute($vals); }
    else $pdo->prepare("INSERT INTO treatments (name, cat, minutes, price, units, active, sort) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute($vals);
    lumina_json(['ok' => true]);
}

if ($action === 'technicians') {
    lumina_ensure_tech_email($pdo);
    lumina_ensure_modelb($pdo);
    lumina_json(['ok' => true, 'technicians' => $pdo->query("SELECT * FROM technicians ORDER BY sort, id")->fetchAll()]);
}
if ($action === 'technician_save') {
    lumina_ensure_tech_email($pdo);
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $id = (int)($in['id'] ?? 0);
    $name = trim((string)($in['name'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    if ($name === '') lumina_json(['ok' => false, 'error' => 'Name required'], 422);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) lumina_json(['ok' => false, 'error' => 'That email looks incomplete — please check it, or leave it blank.'], 422);
    $active = !empty($in['active']) ? 1 : 0;
    if (array_key_exists('sched', $in)) {
        $sched = lumina_clean_sched($in['sched']);
        $schedVal = $sched === '' ? null : $sched;
        if ($id) $pdo->prepare("UPDATE technicians SET name=?, email=?, active=?, sched=? WHERE id=?")->execute([$name, $email, $active, $schedVal, $id]);
        else $pdo->prepare("INSERT INTO technicians (name, email, active, sched) VALUES (?, ?, 1, ?)")->execute([$name, $email, $schedVal]);
    } else {
        if ($id) $pdo->prepare("UPDATE technicians SET name=?, email=?, active=? WHERE id=?")->execute([$name, $email, $active, $id]);
        else $pdo->prepare("INSERT INTO technicians (name, email, active) VALUES (?, ?, 1)")->execute([$name, $email]);
    }
    lumina_json(['ok' => true]);
}

if ($action === 'settings') {
    lumina_json(['ok' => true, 'settings' => lumina_settings($pdo),
                 'blocked' => $pdo->query("SELECT * FROM blocked_dates ORDER BY d")->fetchAll()]);
}
if ($action === 'settings_save') {
    $in = body_json();
    $allowed = ['open_min', 'close_min', 'slot_step', 'window_days', 'capacity', 'cancel_cutoff_hours'];
    $st = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    foreach ($allowed as $k) if (isset($in[$k])) $st->execute([$k, (string)(int)$in[$k]]);
    if (isset($in['notify_emails'])) $st->execute(['notify_emails', trim((string)$in['notify_emails'])]);
    if (isset($in['booking_mode'])) $st->execute(['booking_mode', $in['booking_mode'] === 'staff' ? 'staff' : 'pool']);
    lumina_json(['ok' => true]);
}
if ($action === 'tech_days_off') {
    lumina_ensure_modelb($pdo);
    $tid = (int)($_GET['techId'] ?? 0);
    $days = [];
    try {
        $st = $pdo->prepare("SELECT d FROM tech_days_off WHERE tech_id = ? ORDER BY d");
        $st->execute([$tid]);
        foreach ($st->fetchAll() as $r) $days[] = $r['d'];
    } catch (Exception $e) {}
    lumina_json(['ok' => true, 'days' => $days]);
}
if ($action === 'tech_dayoff_add') {
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $tid = (int)($in['techId'] ?? 0); $d = trim((string)($in['date'] ?? ''));
    if ($tid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) lumina_json(['ok' => false, 'error' => 'Pick a valid date.'], 422);
    try { $pdo->prepare("INSERT IGNORE INTO tech_days_off (tech_id, d) VALUES (?, ?)")->execute([$tid, $d]); } catch (Exception $e) {}
    lumina_json(['ok' => true]);
}
if ($action === 'tech_dayoff_remove') {
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $tid = (int)($in['techId'] ?? 0); $d = trim((string)($in['date'] ?? ''));
    try { $pdo->prepare("DELETE FROM tech_days_off WHERE tech_id = ? AND d = ?")->execute([$tid, $d]); } catch (Exception $e) {}
    lumina_json(['ok' => true]);
}

/* Roster month calendar — shared resolution for tech_month / team_month.
   Resolves every day of $ym for the given tech rows (each carrying its
   parsed schedMap, as lumina_techs returns) through lumina_tech_resolve —
   the exact helper the booking engine uses — with the whole month's
   per-date overrides loaded in two bulk range queries.
   Returns [dateStr => [techId => resolution]] (resolution arrays as
   lumina_tech_resolve returns them). */
function roster_month_grid($pdo, $techRows, $ym) {
    $set = lumina_settings($pdo);
    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01', lumina_tz());
    $dim = (int)$first->format('t');
    $lastDs = $ym . '-' . str_pad((string)$dim, 2, '0', STR_PAD_LEFT);
    $offByDate = []; $shiftByDate = [];
    try {
        $st = $pdo->prepare("SELECT tech_id, d FROM tech_days_off WHERE d >= ? AND d <= ?");
        $st->execute([$ym . '-01', $lastDs]);
        foreach ($st->fetchAll() as $r) $offByDate[$r['d']][(int)$r['tech_id']] = true;
    } catch (Exception $e) {}
    try {
        $st = $pdo->prepare("SELECT tech_id, d, start_min, end_min FROM tech_shifts WHERE d >= ? AND d <= ?");
        $st->execute([$ym . '-01', $lastDs]);
        foreach ($st->fetchAll() as $r) $shiftByDate[$r['d']][(int)$r['tech_id']] = [(int)$r['start_min'], (int)$r['end_min']];
    } catch (Exception $e) {}
    $grid = [];
    for ($d = 1; $d <= $dim; $d++) {
        $ds = $ym . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
        $over = ['off' => $offByDate[$ds] ?? [], 'shifts' => $shiftByDate[$ds] ?? []];
        foreach ($techRows as $t) $grid[$ds][(int)$t['id']] = lumina_tech_resolve($pdo, $t, $ds, $set, $over);
    }
    return $grid;
}
/* Validates a roster ?ym=YYYY-MM like phone_availability does (regex plus a
   DateTime round-trip, so month 13 is rejected). Returns the first-of-month
   DateTime, or exits with a 400. */
function roster_month_first($ym) {
    if (!preg_match('/^\d{4}-\d{2}$/', (string)$ym)) lumina_json(['ok' => false, 'error' => 'Bad month'], 400);
    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01', lumina_tz());
    if (!$first || $first->format('Y-m') !== $ym) lumina_json(['ok' => false, 'error' => 'Bad month'], 400);
    return $first;
}
/* Fetches one technician row by id (any active state), or null. */
function roster_tech_row($pdo, $tid) {
    $st = $pdo->prepare("SELECT * FROM technicians WHERE id = ?");
    $st->execute([(int)$tid]);
    $t = $st->fetch();
    if (!$t) return null;
    $t['schedMap'] = lumina_parse_sched(isset($t['sched']) ? $t['sched'] : null);
    return $t;
}
if ($action === 'tech_month') {
    /* One technician's resolved roster for a month — the per-tech month
       editor in the admin. Resolution matches the booking engine exactly
       (roster_month_grid -> lumina_tech_resolve). */
    lumina_ensure_modelb($pdo);
    $tid = (int)($_GET['techId'] ?? 0);
    $ym = (string)($_GET['ym'] ?? '');
    $first = roster_month_first($ym);
    $t = $tid > 0 ? roster_tech_row($pdo, $tid) : null;
    if (!$t) lumina_json(['ok' => false, 'error' => 'Unknown technician'], 404);
    $grid = roster_month_grid($pdo, [$t], $ym);
    $days = [];
    foreach ($grid as $ds => $per) {
        $r = $per[$tid];
        $days[$ds] = ['state' => $r['state'], 'start' => $r['start'], 'end' => $r['end'], 'source' => $r['source']];
    }
    lumina_json(['ok' => true, 'tech' => ['id' => $tid, 'name' => $t['name']], 'ym' => $ym,
                 'monthLabel' => $first->format('F Y'), 'days' => $days]);
}
if ($action === 'team_month') {
    /* Team availability overview: every active technician x every day of
       the month, resolved through the same code path as tech_month. */
    lumina_ensure_modelb($pdo);
    $ym = (string)($_GET['ym'] ?? '');
    $first = roster_month_first($ym);
    $techs = lumina_techs($pdo);
    $grid = roster_month_grid($pdo, $techs, $ym);
    $days = [];
    foreach ($grid as $ds => $per) {
        $row = [];
        foreach ($per as $tid => $r) $row[$tid] = $r['state'] === 'work' ? [$r['start'], $r['end']] : null;
        $days[$ds] = $row;
    }
    $list = [];
    foreach ($techs as $t) $list[] = ['id' => (int)$t['id'], 'name' => $t['name']];
    lumina_json(['ok' => true, 'ym' => $ym, 'monthLabel' => $first->format('F Y'), 'techs' => $list, 'days' => $days]);
}
if ($action === 'tech_shift_set') {
    /* Set custom working hours for one tech on one date (an override).
       Working beats a day off: any tech_days_off row for the date goes. */
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $tid = (int)($in['techId'] ?? 0); $d = trim((string)($in['date'] ?? ''));
    $start = (int)($in['start'] ?? -1); $end = (int)($in['end'] ?? -1);
    if ($tid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) lumina_json(['ok' => false, 'error' => 'Pick a valid date.'], 422);
    $dt = DateTime::createFromFormat('Y-m-d', $d, lumina_tz());
    if (!$dt || $dt->format('Y-m-d') !== $d) lumina_json(['ok' => false, 'error' => 'Pick a valid date.'], 422);
    if ($start < 0 || $end > 1440 || $end <= $start) lumina_json(['ok' => false, 'error' => 'End time must be after start time.'], 422);
    if (!roster_tech_row($pdo, $tid)) lumina_json(['ok' => false, 'error' => 'Unknown technician'], 404);
    try {
        $pdo->prepare("INSERT INTO tech_shifts (tech_id, d, start_min, end_min) VALUES (?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE start_min = VALUES(start_min), end_min = VALUES(end_min)")
            ->execute([$tid, $d, $start, $end]);
        $pdo->prepare("DELETE FROM tech_days_off WHERE tech_id = ? AND d = ?")->execute([$tid, $d]);
    } catch (Exception $e) { lumina_json(['ok' => false, 'error' => 'Could not save that day.'], 500); }
    lumina_json(['ok' => true]);
}
if ($action === 'tech_shift_off') {
    /* Mark one tech off on one date (an override): the day-off row goes in
       and any custom-hours row for the date comes out. */
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $tid = (int)($in['techId'] ?? 0); $d = trim((string)($in['date'] ?? ''));
    if ($tid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) lumina_json(['ok' => false, 'error' => 'Pick a valid date.'], 422);
    if (!roster_tech_row($pdo, $tid)) lumina_json(['ok' => false, 'error' => 'Unknown technician'], 404);
    try {
        $pdo->prepare("INSERT IGNORE INTO tech_days_off (tech_id, d) VALUES (?, ?)")->execute([$tid, $d]);
        $pdo->prepare("DELETE FROM tech_shifts WHERE tech_id = ? AND d = ?")->execute([$tid, $d]);
    } catch (Exception $e) {}
    lumina_json(['ok' => true]);
}
if ($action === 'tech_shift_clear') {
    /* Remove BOTH overrides for one tech on one date — the day falls back
       to the weekly template (or the shop hours). */
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $tid = (int)($in['techId'] ?? 0); $d = trim((string)($in['date'] ?? ''));
    if ($tid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) lumina_json(['ok' => false, 'error' => 'Pick a valid date.'], 422);
    try {
        $pdo->prepare("DELETE FROM tech_days_off WHERE tech_id = ? AND d = ?")->execute([$tid, $d]);
        $pdo->prepare("DELETE FROM tech_shifts WHERE tech_id = ? AND d = ?")->execute([$tid, $d]);
    } catch (Exception $e) {}
    lumina_json(['ok' => true]);
}
if ($action === 'booking_assign') {
    /* Staff mode only: (re)assign the technician(s) on an existing booking.
       Revalidates feasibility under the booking lock and refuses
       double-booking. Choosing a name marks the booking as a named pick
       (strict on guest reschedule); Auto returns it to no-preference. */
    if (lumina_mode($pdo) !== 'staff') lumina_json(['ok' => false, 'error' => 'Technician assignment is only available in Per-technician booking mode.'], 422);
    lumina_ensure_modelb($pdo);
    $in = body_json();
    $id = (int)($in['id'] ?? 0); $techId = (int)($in['techId'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM bookings WHERE id = ?"); $st->execute([$id]); $b = $st->fetch();
    if (!$b) lumina_json(['ok' => false, 'error' => 'Booking not found'], 404);
    if ($b['status'] === 'cancelled') lumina_json(['ok' => false, 'error' => 'Cancelled bookings cannot be reassigned.'], 422);
    $pdo->query("SELECT GET_LOCK('lumina_book', 5)");
    try {
        $as = lumina_staff_assign($pdo, $b['date'], (int)$b['start_min'], (int)$b['total_min'], (int)$b['units'], $techId, $id);
        if (!$as) {
            $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
            lumina_json(['ok' => false, 'error' => $techId > 0 ? 'That therapist is not free at this booking\'s time — please pick another.' : 'No therapist is free at this booking\'s time.'], 422);
        }
        /* tech_pref is NOT touched: it records whether the GUEST picked the
           technician (strict on reschedule), and an admin assignment must
           not rewrite that history. */
        $pdo->prepare("UPDATE bookings SET tech_id = ?, tech2_id = ? WHERE id = ?")
            ->execute([$as['tech_id'], $as['tech2_id'], $id]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('lumina_book')");
    }
    /* This action is staff-mode only: tell the shop and the newly assigned
       technician(s) that the booking is now theirs. */
    $map = lumina_tech_name_map($pdo);
    $names = [];
    foreach ([$as['tech_id'], $as['tech2_id']] as $tid) if (!empty($tid) && !empty($map[(int)$tid])) $names[] = $map[(int)$tid];
    $lines = lumina_booking_lines($pdo, (int)$b['id']);
    $withLine = lumina_with_line($pdo, ['tech_id' => $as['tech_id'], 'tech2_id' => $as['tech2_id']]);
    lumina_notify($pdo, "Booking assigned — {$b['ref']}",
        "Booking {$b['ref']} is now assigned to " . ($names ? implode(' & ', $names) : '—') . ".\n\nGuest: {$b['name']} ({$b['phone']})\nDate: " . lumina_fmt_day($b['date']) . ', ' . lumina_fmt_time((int)$b['start_min']) . "\n" . implode("\n", $lines) . $withLine,
        null, [$as['tech_id'], $as['tech2_id']]);
    lumina_json(['ok' => true]);
}
if ($action === 'blocked_add') {
    $in = body_json();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['date'] ?? ''))) lumina_json(['ok' => false, 'error' => 'Bad date'], 400);
    $pdo->prepare("INSERT IGNORE INTO blocked_dates (d, note) VALUES (?, ?)")->execute([$in['date'], trim((string)($in['note'] ?? ''))]);
    lumina_json(['ok' => true]);
}
if ($action === 'blocked_remove') {
    $in = body_json();
    $pdo->prepare("DELETE FROM blocked_dates WHERE d = ?")->execute([(string)($in['date'] ?? '')]);
    lumina_json(['ok' => true]);
}

lumina_json(['ok' => false, 'error' => 'Unknown action'], 400);
