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
        'booking_mode' => 'staff',
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

/* Simultaneous guests is no longer a hand-set number: it follows the team.
   One guest at a time per ACTIVE technician; if nobody is on the team list
   yet, fall back to the stored setting so the shop never drops to zero. */
function lumina_capacity($pdo, $set) {
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM technicians WHERE active=1")->fetchColumn();
    } catch (Throwable $e) { $n = 0; }
    if ($n > 0) return $n;
    return max(1, (int)($set['capacity'] ?? 2));
}

/* Bookable start times (minutes from midnight) for a chain of $totalMin needing $units.
   Returns [] when the date is out of window or blocked. */
function lumina_available_starts($pdo, $dateStr, $totalMin, $units, $excludeId = 0) {
    $set = lumina_settings($pdo);
    $open = (int)$set['open_min']; $close = (int)$set['close_min'];
    $step = (int)$set['slot_step']; $cap = lumina_capacity($pdo, $set);
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
   extra addresses from the admin 'notify_emails' setting, plus technicians.
   In pool mode every active technician with an email on file is included.
   In staff mode (Model B) technicians are scoped: $techIds lists the only
   technician ids to include — a booking's assigned techs for booking events,
   [] for non-booking notices (technicians then receive none). $techIds is
   ignored in pool mode, so pool behaviour is unchanged either way. */
function lumina_notify($pdo, $subject, $body, $replyTo = null, $techIds = null) {
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
        $staffMode = false;
        try { $staffMode = lumina_mode($pdo) === 'staff'; } catch (Exception $e) { $staffMode = false; }
        if ($staffMode) {
            $ids = [];
            if (is_array($techIds)) foreach ($techIds as $tid) { $tid = (int)$tid; if ($tid > 0) $ids[] = $tid; }
            if ($ids) {
                $in = implode(',', array_map('intval', array_unique($ids)));
                try {
                    foreach ($pdo->query("SELECT email FROM technicians WHERE active = 1 AND email <> '' AND id IN ($in)")->fetchAll() as $t) {
                        $a = trim((string)$t['email']);
                        if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) $list[] = $a;
                    }
                } catch (Exception $e) { /* technicians table not ready */ }
            }
        } else {
            try {
                foreach ($pdo->query("SELECT email FROM technicians WHERE active = 1 AND email <> ''")->fetchAll() as $t) {
                    $a = trim((string)$t['email']);
                    if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) $list[] = $a;
                }
            } catch (Exception $e) { /* technicians table not ready */ }
        }
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
   Per-technician booking — the single booking model since 2026-10-16.
   (The shared-pool engine above remains only as a legacy fallback for the
   stored capacity setting; availability and booking all run through the
   technician engine below.)
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
        $tbl = $pdo->query("SHOW TABLES LIKE 'tech_shifts'")->fetch();
        if (!$tbl) $pdo->exec("CREATE TABLE tech_shifts (
            tech_id INT NOT NULL, d DATE NOT NULL,
            start_min INT NOT NULL, end_min INT NOT NULL,
            PRIMARY KEY (tech_id, d)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { /* tables may not exist yet */ }
}

/* Treatment groups (provider-managed: per Illo 2026-10-16 the shop picks a
   treatment's group from a dropdown but cannot add or remove groups — the
   category structure stays under Hifill's control, matching the website's
   designed category tabs). The list drives the Group dropdown on each
   treatment row so a typo can't split a group in two. Seeded once from the
   distinct group names already in use, in their current display order. */
function lumina_ensure_groups($pdo) {
    static $done = false;
    if ($done || !$pdo) return;
    $done = true;
    try {
        $tbl = $pdo->query("SHOW TABLES LIKE 'treatment_groups'")->fetch();
        if (!$tbl) {
            $pdo->exec("CREATE TABLE treatment_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                sort INT NOT NULL DEFAULT 0,
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $n = (int)$pdo->query("SELECT COUNT(*) FROM treatment_groups")->fetchColumn();
        if ($n === 0) {
            $cats = $pdo->query("SELECT cat FROM treatments WHERE cat <> '' GROUP BY cat ORDER BY MIN(sort), MIN(id)")->fetchAll(PDO::FETCH_COLUMN);
            $ins = $pdo->prepare("INSERT IGNORE INTO treatment_groups (name, sort) VALUES (?, ?)");
            foreach ($cats as $i => $c) $ins->execute([$c, ($i + 1) * 10]);
        }
    } catch (Exception $e) { /* table may not exist yet */ }
}

function lumina_mode($pdo) {
    /* Single-model system (2026-10-16, per Illo): there is no shared-pool
       mode anymore — every booking belongs to the technician team. The
       stored booking_mode setting is ignored. */
    return 'staff';
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

/* The weekly-template window for a date, BEFORE per-date overrides:
   [startMin, endMin], or null when the weekly schedule marks it a day off.
   A tech with no saved schedule gets the shop hours every day. */
function lumina_tech_window_weekly($tech, $dateStr, $set) {
    $map = isset($tech['schedMap']) ? $tech['schedMap'] : null;
    if ($map === null) return [(int)$set['open_min'], (int)$set['close_min']];
    $d = DateTime::createFromFormat('Y-m-d', $dateStr, lumina_tz());
    if (!$d) return null;
    $v = $map[(int)$d->format('w')];
    return is_array($v) ? $v : null;
}

/* Per-date shift override (custom working hours) for one tech on one date:
   [startMin, endMin], or null when there is no override row. */
function lumina_tech_shift($pdo, $techId, $dateStr) {
    if (!$pdo) return null;
    try {
        $st = $pdo->prepare("SELECT start_min, end_min FROM tech_shifts WHERE tech_id = ? AND d = ?");
        $st->execute([(int)$techId, $dateStr]);
        $r = $st->fetch();
        if ($r) return [(int)$r['start_min'], (int)$r['end_min']];
    } catch (Exception $e) { /* overrides table not ready */ }
    return null;
}

/* Every per-date override for one date in two queries, for month loops:
   ['off' => [techId => true], 'shifts' => [techId => [startMin, endMin]]].
   Pass the result as $over to lumina_tech_resolve to resolve a whole day
   (or a whole month, day by day) without per-tech queries. */
function lumina_tech_overrides_for_date($pdo, $dateStr) {
    $over = ['off' => [], 'shifts' => []];
    if (!$pdo) return $over;
    $over['off'] = lumina_tech_off_set($pdo, $dateStr);
    try {
        $st = $pdo->prepare("SELECT tech_id, start_min, end_min FROM tech_shifts WHERE d = ?");
        $st->execute([$dateStr]);
        foreach ($st->fetchAll() as $r) $over['shifts'][(int)$r['tech_id']] = [(int)$r['start_min'], (int)$r['end_min']];
    } catch (Exception $e) { /* overrides table not ready */ }
    return $over;
}

/* THE roster resolution — one tech, one date. Fixed order:
     1. a one-off day off (tech_days_off) beats everything;
     2. a per-date shift (tech_shifts) beats the weekly template;
     3. the weekly template (technicians.sched) decides work / day off;
     4. no weekly schedule at all -> the shop hours.
   Returns ['state' => 'work'|'off', 'start' => min|null, 'end' => min|null,
   'source' => 'off_override'|'shift'|'weekly'|'weekly_off'|'shop'].
   $over may pre-supply the date's overrides (see
   lumina_tech_overrides_for_date); when given it must be COMPLETE for the
   date — the database is then not consulted for overrides. */
function lumina_tech_resolve($pdo, $tech, $dateStr, $set, $over = null) {
    $tid = (int)($tech['id'] ?? 0);
    if ($over !== null) {
        if (!empty($over['off'][$tid]))
            return ['state' => 'off', 'start' => null, 'end' => null, 'source' => 'off_override'];
    } elseif ($pdo) {
        $off = lumina_tech_off_set($pdo, $dateStr);
        if (isset($off[$tid]))
            return ['state' => 'off', 'start' => null, 'end' => null, 'source' => 'off_override'];
    }
    if ($over !== null) {
        if (isset($over['shifts'][$tid])) {
            $sh = $over['shifts'][$tid];
            return ['state' => 'work', 'start' => (int)$sh[0], 'end' => (int)$sh[1], 'source' => 'shift'];
        }
    } elseif ($pdo) {
        $sh = lumina_tech_shift($pdo, $tid, $dateStr);
        if ($sh !== null)
            return ['state' => 'work', 'start' => $sh[0], 'end' => $sh[1], 'source' => 'shift'];
    }
    $w = lumina_tech_window_weekly($tech, $dateStr, $set);
    if ($w === null) return ['state' => 'off', 'start' => null, 'end' => null, 'source' => 'weekly_off'];
    $hasSched = isset($tech['schedMap']) && $tech['schedMap'] !== null;
    return ['state' => 'work', 'start' => $w[0], 'end' => $w[1], 'source' => $hasSched ? 'weekly' : 'shop'];
}

/* The [startMin, endMin] window a tech works on a date, or null = day off.
   Full roster resolution via lumina_tech_resolve (day-off override >
   per-date shift > weekly template > shop hours), so every caller of this
   helper — the staff availability engine, assignment, the phone calendar —
   sees hand-set days automatically. */
function lumina_tech_window($pdo, $tech, $dateStr, $set) {
    $r = lumina_tech_resolve($pdo, $tech, $dateStr, $set);
    return $r['state'] === 'work' ? [$r['start'], $r['end']] : null;
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

/* Can $tech personally cover [start, end) on $dateStr? Checks the tech's
   resolved window for the date (weekly template + per-date overrides — a
   one-off day off resolves to no window at all), and overlaps with that
   tech's own pending/confirmed bookings (as tech or as second tech). */
function lumina_tech_free($pdo, $tech, $dateStr, $start, $end, $set, $excludeId = 0, $ignoreOverlap = false) {
    $w = lumina_tech_window($pdo, $tech, $dateStr, $set);
    if ($w === null) return false;
    if ($start < $w[0] || $end > $w[1]) return false;
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
    $starts = []; $byStart = [];
    for ($st = $open; $st + $totalMin <= $close; $st += $step) {
        if ($nowMin !== null && $st <= $nowMin) continue;
        $free = [];
        foreach ($techs as $t) {
            if (lumina_tech_free($pdo, $t, $dateStr, $st, $st + $totalMin, $set, $excludeId)) $free[] = (int)$t['id'];
        }
        if ($techId > 0) $ok = in_array($techId, $free, true) && ($units < 2 || count($free) >= 2);
        else $ok = count($free) >= $units;
        /* Unassigned bookings (guest picked "No preference", the shop has
           not assigned a technician yet) still have to land on somebody —
           make sure a full placement of everything overlapping exists. */
        if ($ok) $ok = lumina_team_feasible($pdo, $dateStr, $st, $st + $totalMin, $units, $excludeId, $techId > 0 ? [$techId] : []);
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
    $free = [];
    foreach ($techs as $t) {
        if (lumina_tech_free($pdo, $t, $dateStr, $start, $end, $set, $excludeId, $ignoreOverlap)) {
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

/* Combinations of $k items from $items (order preserved). */
function lumina_combos($items, $k) {
    if ($k === 0) return [[]];
    if (count($items) < $k) return [];
    $out = [];
    $n = count($items);
    for ($i = 0; $i <= $n - $k; $i++) {
        foreach (lumina_combos(array_slice($items, $i + 1), $k - 1) as $rest) {
            $out[] = array_merge([$items[$i]], $rest);
        }
    }
    return $out;
}

/* Backtracking placement: can every job still to place get its `need`
   distinct technicians from its `win` list without clashing with the
   intervals already in $occ (techId => [[s,e), ...])? */
function lumina_place($jobs, $idx, &$occ) {
    if ($idx >= count($jobs)) return true;
    $j = $jobs[$idx];
    $cand = [];
    foreach ($j['win'] as $tid) {
        $clash = false;
        foreach ($occ[$tid] ?? [] as $iv) { if ($j['s'] < $iv[1] && $iv[0] < $j['e']) { $clash = true; break; } }
        if (!$clash) $cand[] = $tid;
    }
    if (count($cand) < $j['need']) return false;
    foreach (lumina_combos($cand, $j['need']) as $combo) {
        foreach ($combo as $tid) $occ[$tid][] = [$j['s'], $j['e']];
        if (lumina_place($jobs, $idx + 1, $occ)) return true;
        foreach ($combo as $tid) array_pop($occ[$tid]);
    }
    return false;
}

/* Can the team cover one more booking [start, end) needing $units
   technicians on $dateStr, alongside every existing pending/confirmed
   booking? Assigned bookings stay pinned to their technicians; UNASSIGNED
   ones (guest chose "No preference", awaiting the shop's assignment) may
   land on anyone whose roster covers them. $pin lists technician ids the
   new booking is already fixed to (a named pick — the caller must have
   verified those technicians are free; they are occupied first).
   Fast path: when nothing unassigned overlaps, the per-technician free
   checks elsewhere already decide, so this returns true at once. */
function lumina_team_feasible($pdo, $dateStr, $start, $end, $units, $excludeId = 0, $pin = []) {
    $set = lumina_settings($pdo);
    $techs = lumina_techs($pdo);
    if (count($techs) < $units) return false;
    foreach ($pin as $pt) {
        $pw = null;
        foreach ($techs as $t) if ((int)$t['id'] === $pt) $pw = lumina_tech_window($pdo, $t, $dateStr, $set);
        if ($pw === null || $start < $pw[0] || $end > $pw[1]) return false;
    }
    $st = $pdo->prepare("SELECT start_min, end_min, units, tech_id, tech2_id FROM bookings
        WHERE date = ? AND status IN ('pending','confirmed') AND id <> ? AND start_min < ? AND ? < end_min
        ORDER BY start_min, id");
    $st->execute([$dateStr, $excludeId, $end, $start]);
    $rows = $st->fetchAll();
    $anyUnassigned = false;
    foreach ($rows as $b) { if (empty($b['tech_id'])) { $anyUnassigned = true; break; } }
    if (!$anyUnassigned) return true;
    $winOf = [];
    foreach ($techs as $t) {
        $w = lumina_tech_window($pdo, $t, $dateStr, $set);
        if ($w !== null) $winOf[(int)$t['id']] = $w;
    }
    $cover = function ($s, $e) use ($winOf) {
        $o = [];
        foreach ($winOf as $tid => $w) { if ($s >= $w[0] && $e <= $w[1]) $o[] = $tid; }
        return $o;
    };
    $occ = []; $jobs = [];
    foreach ($rows as $b) {
        $fixed = [];
        if (!empty($b['tech_id'])) $fixed[] = (int)$b['tech_id'];
        if (!empty($b['tech2_id'])) $fixed[] = (int)$b['tech2_id'];
        foreach ($fixed as $ft) $occ[$ft][] = [(int)$b['start_min'], (int)$b['end_min']];
        $need = max(1, (int)$b['units']) - count($fixed);
        if ($need > 0) $jobs[] = ['s' => (int)$b['start_min'], 'e' => (int)$b['end_min'], 'need' => $need, 'win' => $cover((int)$b['start_min'], (int)$b['end_min'])];
    }
    $newWin = $cover($start, $end);
    foreach ($pin as $pt) { if (!in_array($pt, $newWin, true)) return false; }
    foreach ($pin as $pt) $occ[$pt][] = [$start, $end];
    $newNeed = $units - count($pin);
    if ($newNeed > 0) $jobs[] = ['s' => $start, 'e' => $end, 'need' => $newNeed, 'win' => $newWin];
    usort($jobs, function ($a, $b) { return count($a['win']) - count($b['win']); });
    return lumina_place($jobs, 0, $occ);
}
