<?php
/* One-time installer. Visit: /api/install.php?key=<setup_key from config>
   Creates the tables, seeds treatments + settings. Locks itself after the
   first successful run (checks for the settings table). */
require __DIR__ . '/lib.php';

$cfg = lumina_config();
if (!$cfg) { http_response_code(500); exit('Config file not found (../lumina-config.php). Create it first — see the setup sheet.'); }
if (!isset($_GET['key']) || !hash_equals((string)($cfg['setup_key'] ?? ''), (string)$_GET['key'])) {
    http_response_code(403); exit('Forbidden — wrong setup key.');
}
$pdo = lumina_db();
if (!$pdo) { http_response_code(500); exit('Database connection failed — check db_host / db_name / db_user / db_pass in the config file.'); }

$exists = $pdo->query("SHOW TABLES LIKE 'settings'")->fetch();
if ($exists) { exit('Already installed — the booking system is set up. This installer is now locked.'); }

$pdo->exec("CREATE TABLE settings (
  k VARCHAR(64) PRIMARY KEY, v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE treatments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  cat VARCHAR(80) NOT NULL,
  minutes INT NOT NULL,
  price INT NOT NULL,
  units TINYINT NOT NULL DEFAULT 1,
  active TINYINT NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE technicians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  sched TEXT NULL,
  active TINYINT NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE tech_days_off (
  tech_id INT NOT NULL, d DATE NOT NULL, PRIMARY KEY (tech_id, d)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE tech_shifts (
  tech_id INT NOT NULL, d DATE NOT NULL,
  start_min INT NOT NULL, end_min INT NOT NULL,
  PRIMARY KEY (tech_id, d)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE blocked_dates (
  d DATE PRIMARY KEY, note VARCHAR(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(12) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(160) NOT NULL DEFAULT '',
  note TEXT,
  date DATE NOT NULL,
  start_min INT NOT NULL,
  end_min INT NOT NULL,
  total_min INT NOT NULL,
  total_price INT NOT NULL,
  units INT NOT NULL DEFAULT 1,
  tech_id INT NULL,
  tech2_id INT NULL,
  tech_pref TINYINT NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','cancelled','done') NOT NULL DEFAULT 'pending',
  source ENUM('online','phone') NOT NULL DEFAULT 'online',
  token VARCHAR(64) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  message TEXT NOT NULL,
  seen TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE booking_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  treatment_id INT NOT NULL DEFAULT 0,
  name_snapshot VARCHAR(160) NOT NULL,
  minutes INT NOT NULL,
  price INT NOT NULL,
  start_min INT NOT NULL,
  end_min INT NOT NULL,
  INDEX (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$set = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?)");
foreach ([
    'open_min' => '600', 'close_min' => '1260', 'slot_step' => '30',
    'window_days' => '30', 'capacity' => '2', 'cancel_cutoff_hours' => '3',
    'booking_mode' => 'pool',
] as $k => $v) $set->execute([$k, $v]);

/* Treatments — exactly as on the website (order = price low to high within each group). */
$treatments = [
  // name, cat, minutes, price, units
  ['Aromatherapy Head Spa for Relaxation', 'Signature', 75, 99, 1],
  ['New Chinese-Style Wellness Head Therapy', 'Signature', 75, 129, 1],
  ['Floral Head Spa', 'Signature', 75, 129, 1],
  ['Herbal Hot Compress Head Treatment', 'Signature', 90, 149, 1],
  ['Holographic Head Spa', 'Head Spa', 60, 68, 1],
  ['Nourishing Scalp Cleansing Treatment', 'Head Spa', 60, 88, 1],
  ['Deep Cleansing & Firming Facial', 'Facial', 60, 68, 1],
  ['Probiotic Skincare Treatment', 'Facial', 60, 108, 1],
  ['HydroGlow Facial', 'Facial', 90, 168, 1],
  ['Swedish Massage', 'Massage', 60, 78, 1],
  ['Foot Reflexology', 'Massage', 60, 78, 1],
  ['Hot Stone Massage', 'Massage', 60, 88, 1],
  ['Himalayan Salt Stone Therapy', 'Massage', 60, 98, 1],
  ['Swedish Massage for Two', 'Massage', 60, 130, 2],
  ['Japanese Head Spa & Facial — 60-Minute Combo', 'Combos', 60, 79, 1],
  ['Japanese Head Spa & Hot Stone Massage — 60-Minute Combo', 'Combos', 60, 79, 1],
  ['Japanese Head Spa & Foot Massage — 60-Minute Combo', 'Combos', 60, 79, 1],
  ['Japanese Head Spa & Facial — 90-Minute Combo', 'Combos', 90, 99, 1],
  ['Japanese Head Spa & Hot Stone Massage — 90-Minute Combo', 'Combos', 90, 99, 1],
  ['Japanese Head Spa & Foot Massage — 90-Minute Combo', 'Combos', 90, 99, 1],
];
$ins = $pdo->prepare("INSERT INTO treatments (name, cat, minutes, price, units, sort) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($treatments as $i => $t) $ins->execute([$t[0], $t[1], $t[2], $t[3], $t[4], $i + 1]);

$pdo->prepare("INSERT INTO technicians (name, sort) VALUES (?, 1)")->execute(['Technician 1']);

echo 'Installed OK — tables created, ' . count($treatments) . ' treatments seeded, default settings saved (capacity 2). '
   . 'You can now open /admin/ and log in with the admin password from your config file.';
