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
        'booking_mode' => 'pool',
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

/* Adds the technicians.email column on installs created before it existed. */
function lumina_ensure_tech_email($pdo) {
    static $done = false;
    if ($done || !$pdo) return;
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM technicians LIKE 'email'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE technicians ADD COLUMN email VARCHAR(190) NOT NULL DEFAULT ''");
    } catch (Exception $e) { /* table may not exist yet */ }
}

/* Shop notifications: the shop's main email always receives them, plus any
   extra addresses from the admin 'notify_emails' setting, plus every active
   technician who has an email on file. */
function lumina_notify($pdo, $subject, $body, $replyTo = null) {
    $cfg = lumina_config();
    $list = [ ($cfg && !empty($cfg['shop_email'])) ? $cfg['shop_email'] : 'info@luminaheadspa.ca' ];
    if ($pdo) {
        $raw = '';
        try { $set = lumina_settings($pdo); $raw = trim((string)($set['notify_emails'] ?? '')); } catch (Exception $e) { $raw = ''; }
        if ($raw !== '') {
            foreach (preg_split('/[,;]+/', $raw) as $a) {
                $a = trim($a);
                if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) $list[] = $a;
            }
        }
        lumina_ensure_tech_email($pdo);
        try {
            foreach ($pdo->query("SELECT email FROM technicians WHERE active = 1 AND email <> ''")->fetchAll() as $t) {
                $a = trim((string)$t['email']);
                if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) $list[] = $a;
            }
        } catch (Exception $e) { /* technicians table not ready */ }
    }
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
/* =====================================================================
   Model B — per-technician booking (mode-gated by settings.booking_mode).
   Everything below is inert while booking_mode = 'pool': the pool engine
   above stays the only code path. Staff behaviour activates only when an
   admin switches the setting to 'staff'.
   ===================================================================== */

/* Lazy, idempotent schema for Model B (mirrors lumina_ensure_tech_email). */
function lumina_ensure_modelb($pdo) {
    static $done = false;
    if ($done || !$pdo) return;
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM technicians LIKE 'sched'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE technicians ADD COLUMN sched TEXT NULL");
        $col = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'tech_id'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE bookings ADD COLUMN tech_id INT NULL");
        $col = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'tech2_id'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE bookings ADD COLUMN tech2_id INT NULL");
        $col = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'tech_pref'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE bookings ADD COLUMN tech_pref TINYINT NOT NULL DEFAULT 0");
        $tbl = $pdo->query("SHOW TABLES LIKE 'tech_days_off'")->fetch();
        if (!$tbl) $pdo->exec("CREATE TABLE tech_days_off (
            tech_id INT NOT NULL, d DATE NOT NULL, PRIMARY KEY (tech_id, d)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { /* tables may not exist yet */ }
}

function lumina_mode($pdo) {
    $set = lumina_settings($pdo);
    return (isset($set['booking_mode']) && $set['booking_mode'] === 'staff') ? 'staff' : 'pool';
}

/* Validate + normalize a weekly schedule. Accepts an array or JSON string.
   Returns a JSON string of a 7-entry array (index 0 = Sunday .. 6 = Saturday);
   each entry is [startMin, endMin] or null (day off). '' means "no schedule". */
function lumina_clean_sched($raw) {
    $d = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($d)) return '';
    $out = [];
    for ($dow = 0; $dow <= 6; $dow++) {
        $v = isset($d[$dow]) ? $d[$dow] : null;
        if (is_array($v) && count($v) >= 2) {
            $a = (int)$v[0]; $b = (int)$v[1];
            $out[] = ($a >= 0 && $b <= 1440 && $b > $a) ? [$a, $b] : null;
        } else $out[] = null;
    }
    return json_encode($out);
}

/* Parse a stored sched value into a 7-entry array, or null when the tech
   has no schedule (works the shop's hours every day). */
function lumina_parse_sched($json) {
    if ($json === null || trim((string)$json) === '') return null;
    $d = json_decode((string)$json, true);
    if (!is_array($d)) return null;
    $out = [];
    for ($dow = 0; $dow <= 6; $dow++) {
        $v = isset($d[$dow]) ? $d[$dow] : null;
        $out[] = (is_array($v) && count($v) >= 2) ? [(int)$v[0], (int)$v[1]] : null;
    }
    return $out;
}

/* Active technicians, each with a parsed 'schedMap' (null = shop hours). */
function lumina_techs($pdo) {
    lumina_ensure_modelb($pdo);
    $rows = [];
    try { $rows = $pdo->query("SELECT * FROM technicians WHERE active = 1 ORDER BY sort, id")->fetchAll(); } catch (Exception $e) { $rows = []; }
    foreach ($rows as &$t) { $t['schedMap'] = lumina_parse_sched(isset($t['sched']) ? $t['sched'] : null); }
    unset($t);
    return $rows;
}

/* id => name for every technician (active or not), for display purposes. */
function lumina_tech_name_map($pdo) {
    $map = [];
    try { foreach ($pdo->query("SELECT id, name FROM technicians")->fetchAll() as $t) $map[(int)$t['id']] = $t['name']; } catch (Exception $e) {}
    return $map;
}

/* "\nWith: Amy" / "\nWith: Amy & Tammy" for emails — '' when unassigned. */
function lumina_with_line($pdo, $b) {
    if (empty($b['tech_id'])) return '';
    $map = lumina_tech_name_map($pdo);
    $n = [];
    if (!empty($map[(int)$b['tech_id']])) $n[] = $map[(int)$b['tech_id']];
    if (!empty($b['tech2_id']) && !empty($map[(int)$b['tech2_id']])) $n[] = $map[(int)$b['tech2_id']];
    return $n ? "\nWith: " . implode(' & ', $n) : '';
}

/* The [startMin, endMin] window a tech works on a date, or null = day off. */
function lumina_tech_window($tech, $dateStr, $set) {
    $map = isset($tech['schedMap']) ? $tech['schedMap'] : null;
    if ($map === null) return [(int)$set['open_min'], (int)$set['close_min']];
    $d = DateTime::createFromFormat('Y-m-d', $dateStr, lumina_tz());
    if (!$d) return null;
    $v = $map[(int)$d->format('w')];
    return is_array($v) ? $v : null;
}

/* Set of tech ids with a one-off day off on $dateStr: [techId => true]. */
function lumina_tech_off_set($pdo, $dateStr) {
    $out = [];
    try {
        $st = $pdo->prepare("SELECT tech_id FROM tech_days_off WHERE d = ?");
        $st->execute([$dateStr]);
        foreach ($st->fetchAll() as $r) $out[(int)$r['tech_id']] = true;
    } catch (Exception $e) {}
    return $out;
}

/* Can $tech personally cover [start, end) on $dateStr? Checks the weekly
   schedule window, one-off days off, and overlaps with that tech's own
   pending/confirmed bookings (as tech or as second tech). */
function lumina_tech_free($pdo, $tech, $dateStr, $start, $end, $set, $offSet, $excludeId = 0, $ignoreOverlap = false) {
    $w = lumina_tech_window($tech, $dateStr, $set);
    if ($w === null) return false;
    if ($start < $w[0] || $end > $w[1]) return false;
    if (isset($offSet[(int)$tech['id']])) return false;
    if ($ignoreOverlap) return true;
    $st = $pdo->prepare(
        "SELECT COUNT(*) c FROM bookings
         WHERE date = ? AND status IN ('pending','confirmed')
           AND (tech_id = ? OR tech2_id = ?)
           AND start_min < ? AND ? < end_min AND id <> ?"
    );
    $st->execute([$dateStr, (int)$tech['id'], (int)$tech['id'], $end, $start, $excludeId]);
    return (int)$st->fetch()['c'] === 0;
}

/* Minutes already booked for a tech on a date (load-balancing signal). */
function lumina_tech_booked_min($pdo, $techId, $dateStr, $excludeId = 0) {
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(end_min - start_min),0) m FROM bookings
         WHERE date = ? AND status <> 'cancelled' AND (tech_id = ? OR tech2_id = ?) AND id <> ?"
    );
    $st->execute([$dateStr, $techId, $techId, $excludeId]);
    return (int)$st->fetch()['m'];
}

/* Staff-mode bookable starts for a chain of $totalMin needing $units techs.
   $techId > 0 restricts to that named tech (units 2 still needs a partner).
   Returns ['starts' => [...], 'byStart' => [startMin => [free tech ids]],
            'techs' => [['id'=>, 'name'=>], ...]] */
function lumina_available_starts_staff($pdo, $dateStr, $totalMin, $units, $techId = 0, $excludeId = 0) {
    $set = lumina_settings($pdo);
    $open = (int)$set['open_min']; $close = (int)$set['close_min']; $step = (int)$set['slot_step'];
    $techs = lumina_techs($pdo);
    $techList = [];
    foreach ($techs as $t) $techList[] = ['id' => (int)$t['id'], 'name' => $t['name']];
    $empty = ['starts' => [], 'byStart' => [], 'techs' => $techList];
    $today = lumina_now()->format('Y-m-d');
    $last = lumina_now()->modify('+' . (int)$set['window_days'] . ' days')->format('Y-m-d');
    if ($dateStr < $today || $dateStr > $last) return $empty;
    if (lumina_blocked($pdo, $dateStr)) return $empty;
    $nowMin = null;
    if ($dateStr === $today) { $n = lumina_now(); $nowMin = (int)$n->format('H') * 60 + (int)$n->format('i'); }
    $offSet = lumina_tech_off_set($pdo, $dateStr);
    $starts = []; $byStart = [];
    for ($st = $open; $st + $totalMin <= $close; $st += $step) {
        if ($nowMin !== null && $st <= $nowMin) continue;
        $free = [];
        foreach ($techs as $t) {
            if (lumina_tech_free($pdo, $t, $dateStr, $st, $st + $totalMin, $set, $offSet, $excludeId)) $free[] = (int)$t['id'];
        }
        if ($techId > 0) $ok = in_array($techId, $free, true) && ($units < 2 || count($free) >= 2);
        else $ok = count($free) >= $units;
        if ($ok) { $starts[] = $st; $byStart[$st] = $free; }
    }
    return ['starts' => $starts, 'byStart' => $byStart, 'techs' => $techList];
}

/* Choose the technician(s) for a booking. $techId > 0 = a named pick (strict:
   that tech must be free; a 2-unit booking pairs them with the least-loaded
   other free tech). $techId = 0 = no preference: least booked minutes that
   day first, ties broken by sort then id. Returns
   ['tech_id' => x, 'tech2_id' => y|null] or null when infeasible. */
function lumina_staff_assign($pdo, $dateStr, $start, $totalMin, $units, $techId = 0, $excludeId = 0, $ignoreOverlap = false) {
    $set = lumina_settings($pdo);
    $end = $start + $totalMin;
    $techs = lumina_techs($pdo);
    $offSet = lumina_tech_off_set($pdo, $dateStr);
    $free = [];
    foreach ($techs as $t) {
        if (lumina_tech_free($pdo, $t, $dateStr, $start, $end, $set, $offSet, $excludeId, $ignoreOverlap)) {
            $t['_load'] = lumina_tech_booked_min($pdo, (int)$t['id'], $dateStr, $excludeId);
            $free[] = $t;
        }
    }
    $byLoad = function ($a, $b) {
        if ($a['_load'] !== $b['_load']) return $a['_load'] < $b['_load'] ? -1 : 1;
        if ((int)$a['sort'] !== (int)$b['sort']) return (int)$a['sort'] < (int)$b['sort'] ? -1 : 1;
        return (int)$a['id'] < (int)$b['id'] ? -1 : 1;
    };
    if ($techId > 0) {
        $chosen = null;
        foreach ($free as $t) if ((int)$t['id'] === $techId) $chosen = $t;
        if (!$chosen) return null;
        if ($units < 2) return ['tech_id' => $techId, 'tech2_id' => null];
        $others = [];
        foreach ($free as $t) if ((int)$t['id'] !== $techId) $others[] = $t;
        if (!$others) return null;
        usort($others, $byLoad);
        return ['tech_id' => $techId, 'tech2_id' => (int)$others[0]['id']];
    }
    if (count($free) < $units) return null;
    usort($free, $byLoad);
    return ['tech_id' => (int)$free[0]['id'], 'tech2_id' => $units >= 2 ? (int)$free[1]['id'] : null];
}
