<?php
/* Lumina Booking System — shared library.
   Config lives OUTSIDE the web root and outside git: ../lumina-config.php
   relative to the document root. It returns an array:
   ['db_host','db_name','db_user','db_pass','admin_pass','setup_key','shop_email'] */

function lumina_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $path = $_SERVER['DOCUMENT_ROOT'] . '/../lumina-config.php';
    if (!file_exists($path)) { $cfg = false; return $cfg; }
    $cfg = require $path;
    return $cfg;
}

function lumina_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $cfg = lumina_config();
    if (!$cfg) return null;
    try {
        $pdo = new PDO(
            'mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4',
            $cfg['db_user'], $cfg['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) { return null; }
    return $pdo;
}

function lumina_tz() { return new DateTimeZone('America/Toronto'); }
function lumina_now() { return new DateTime('now', lumina_tz()); }

function lumina_settings($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $defaults = [
        'open_min' => '600', 'close_min' => '1260', 'slot_step' => '30',
        'window_days' => '30', 'capacity' => '2', 'cancel_cutoff_hours' => '3',
    ];
    try {
        $rows = $pdo->query("SELECT k, v FROM settings")->fetchAll();
        foreach ($rows as $r) $defaults[$r['k']] = $r['v'];
    } catch (Exception $e) { /* not installed yet */ }
    $cache = $defaults;
    return $cache;
}

function lumina_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function lumina_fmt_time($mins) {
    $h = intdiv($mins, 60); $m = $mins % 60;
    $ap = $h >= 12 ? 'PM' : 'AM'; $h12 = $h % 12; if ($h12 === 0) $h12 = 12;
    return $h12 . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ' . $ap;
}

function lumina_fmt_day($dateStr) {  /* '2026-10-20' -> 'Tuesday, October 20' */
    $d = DateTime::createFromFormat('Y-m-d', $dateStr, lumina_tz());
    return $d ? $d->format('l, F j') : $dateStr;
}

/* Is $dateStr blocked in admin? */
function lumina_blocked($pdo, $dateStr) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM blocked_dates WHERE d = ?");
    $st->execute([$dateStr]);
    return (int)$st->fetch()['c'] > 0;
}

/* Sum of capacity units already booked (pending + confirmed) overlapping [start, end). */
function lumina_overlap_units($pdo, $dateStr, $start, $end, $excludeId = 0) {
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(units),0) u FROM bookings
         WHERE date = ? AND status IN ('pending','confirmed')
           AND start_min < ? AND ? < end_min AND id <> ?"
    );
    $st->execute([$dateStr, $end, $start, $excludeId]);
    return (int)$st->fetch()['u'];
}

/* Bookable start times (minutes from midnight) for a chain of $totalMin needing $units.
   Returns [] when the date is out of window or blocked. */
function lumina_available_starts($pdo, $dateStr, $totalMin, $units, $excludeId = 0) {
    $set = lumina_settings($pdo);
    $open = (int)$set['open_min']; $close = (int)$set['close_min'];
    $step = (int)$set['slot_step']; $cap = (int)$set['capacity'];
    $today = lumina_now()->format('Y-m-d');
    $last = lumina_now()->modify('+' . (int)$set['window_days'] . ' days')->format('Y-m-d');
    if ($dateStr < $today || $dateStr > $last) return [];
    if (lumina_blocked($pdo, $dateStr)) return [];
    $nowMin = null;
    if ($dateStr === $today) {
        $n = lumina_now(); $nowMin = (int)$n->format('H') * 60 + (int)$n->format('i');
    }
    $out = [];
    for ($st = $open; $st + $totalMin <= $close; $st += $step) {
        if ($nowMin !== null && $st <= $nowMin) continue;
        if (lumina_overlap_units($pdo, $dateStr, $st, $st + $totalMin, $excludeId) + $units <= $cap) $out[] = $st;
    }
    return $out;
}

function lumina_mail($to, $subject, $body, $replyTo = null) {
    $cfg = lumina_config();
    $from = $cfg && !empty($cfg['shop_email']) ? $cfg['shop_email'] : 'info@luminaheadspa.ca';
    $headers = "From: Lumina Head Spa & Beauty <$from>\r\n"
             . "Reply-To: " . ($replyTo ?: $from) . "\r\n"
             . "Content-Type: text/plain; charset=utf-8\r\n";
    return @mail($to, $subject, $body, $headers);
}

/* Shop notifications: every address in the admin 'notify_emails' setting
   (comma-separated), falling back to the configured shop email. */
function lumina_notify($pdo, $subject, $body, $replyTo = null) {
    $cfg = lumina_config();
    $list = [];
    if ($pdo) {
        $raw = '';
        try { $set = lumina_settings($pdo); $raw = trim((string)($set['notify_emails'] ?? '')); } catch (Exception $e) { $raw = ''; }
        if ($raw !== '') {
            foreach (preg_split('/[,;]+/', $raw) as $a) {
                $a = trim($a);
                if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) $list[] = $a;
            }
        }
    }
    if (!$list) $list = [ ($cfg && !empty($cfg['shop_email'])) ? $cfg['shop_email'] : 'info@luminaheadspa.ca' ];
    foreach (array_unique($list) as $to) lumina_mail($to, $subject, $body, $replyTo);
}

function lumina_booking_lines($pdo, $bookingId) {
    $st = $pdo->prepare("SELECT name_snapshot, start_min, end_min FROM booking_items WHERE booking_id = ? ORDER BY start_min");
    $st->execute([$bookingId]);
    $lines = [];
    foreach ($st->fetchAll() as $it) {
        $lines[] = $it['name_snapshot'] . ' ' . lumina_fmt_time((int)$it['start_min']) . ' - ' . lumina_fmt_time((int)$it['end_min']);
    }
    return $lines;
}
