<?php
// Demo seed data (§9.5). Run from the command line: php sql/seed.php
// Re-runnable: clears all data first.

// CLI only, with one sanctioned exception: api/setup.php defines SEED_VIA_SETUP
// because the hosted demo has no shell to run this from.
if (PHP_SAPI !== 'cli' && !defined('SEED_VIA_SETUP')) {
    die("Run this from the command line only.\n");
}

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['activity_log', 'login_attempts', 'payments', 'sale_items', 'preorders',
          'sales', 'customers', 'labour', 'expenses', 'batch_events', 'batches',
          'species', 'users'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// ---- Users ----
$ownerPass  = 'owner1234';
$workerPass = 'worker1234';
$ins = $pdo->prepare(
    'INSERT INTO users (name, phone, role, password_hash, active) VALUES (?, ?, ?, ?, 1)'
);
$ins->execute(['Erick (Owner)', '0700000001', 'owner',  password_hash($ownerPass, PASSWORD_DEFAULT)]);
$ownerId = (int)$pdo->lastInsertId();
$ins->execute(['Kiprop',        '0700000002', 'worker', password_hash($workerPass, PASSWORD_DEFAULT)]);
$ins->execute(['Chebet',        '0700000003', 'worker', password_hash($workerPass, PASSWORD_DEFAULT)]);

// ---- Species ----
$species = [
    // name, category, price, lead days, prefix
    ['Hass Avocado',        'fruit',      150, 270, 'AVO'],
    ['Apple Mango',         'fruit',      120, 240, 'MGA'],
    ['Ngowe Mango',         'fruit',      120, 240, 'MGN'],
    ['Pawpaw',              'fruit',       80,  90, 'PAW'],
    ['Tree Tomato',         'fruit',       70,  90, 'TTO'],
    ['Grevillea',           'indigenous',  30,  60, 'GRE'],
    ['Macadamia',           'fruit',      250, 300, 'MAC'],
    ['Passion Fruit',       'fruit',       90,  75, 'PAS'],
    ['Sukuma Wiki Seedling','vegetable',    5,  30, 'SUK'],
];
$ins = $pdo->prepare(
    'INSERT INTO species (name, category, default_sale_price, lead_time_days, code_prefix, active, created_by)
     VALUES (?, ?, ?, ?, ?, 1, ?)'
);
$speciesIds = [];
foreach ($species as $s) {
    $ins->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $ownerId]);
    $speciesIds[$s[4]] = (int)$pdo->lastInsertId();
}

// ---- Batches across stages ----
$year = date('Y');
$batches = [
    // prefix, seq, sown days ago, initial, lost, sold, stage
    ['AVO', 1, 200, 500, 30, 120, 'ready'],
    ['MGA', 1, 180, 300, 15,  60, 'ready'],
    ['PAW', 1,  60, 400, 20,   0, 'hardening'],
    ['GRE', 1,  25, 1000, 0,   0, 'germinating'],
    ['SUK', 1,   5, 2000, 0,   0, 'sown'],
];
$ins = $pdo->prepare(
    'INSERT INTO batches (batch_code, species_id, sown_date, initial_qty, current_qty,
                          stage, expected_ready_date, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$batchIds = [];
foreach ($batches as $b) {
    [$prefix, $seq, $daysAgo, $initial, $lost, $sold, $stage] = $b;
    $sown = date('Y-m-d', strtotime("-$daysAgo days"));
    $ready = date('Y-m-d', strtotime($sown . ' +' . array_column($species, 3, 4)[$prefix] . ' days'));
    $code = sprintf('%s-%s-%03d', $prefix, $year, $seq);
    $ins->execute([$code, $speciesIds[$prefix], $sown, $initial,
                   $initial - $lost - $sold, $stage, $ready, $ownerId]);
    $batchIds[$prefix] = (int)$pdo->lastInsertId();
}

// ---- Batch events (losses + activities) ----
$ev = $pdo->prepare(
    'INSERT INTO batch_events (batch_id, event_type, qty, loss_reason, event_date, notes, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$ev->execute([$batchIds['AVO'], 'loss', 30, 'dried', date('Y-m-d', strtotime('-90 days')), 'Dry spell in March', 2]);
$ev->execute([$batchIds['MGA'], 'loss', 15, 'pests', date('Y-m-d', strtotime('-45 days')), 'Cutworms', 3]);
$ev->execute([$batchIds['PAW'], 'loss', 20, 'disease', date('Y-m-d', strtotime('-10 days')), 'Damping off', 2]);
$ev->execute([$batchIds['AVO'], 'watering', null, null, date('Y-m-d', strtotime('-1 day')), null, 2]);
$ev->execute([$batchIds['PAW'], 'input_usage', null, null, date('Y-m-d', strtotime('-3 days')), 'Applied DAP fertilizer', 3]);

// ---- Expenses ----
$ex = $pdo->prepare(
    'INSERT INTO expenses (category, description, supplier, amount, expense_date, batch_id, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$ex->execute(['seed',       'Hass avocado seeds',      'Kakuzi', 15000, date('Y-m-d', strtotime('-200 days')), $batchIds['AVO'], $ownerId]);
$ex->execute(['bags',       'Potting bags 6x9 (2000)', 'Eldoret Agrovet', 8000, date('Y-m-d', strtotime('-190 days')), null, $ownerId]);
$ex->execute(['fertilizer', 'DAP 50kg',                'Eldoret Agrovet', 6500, date('Y-m-d', strtotime('-60 days')), null, $ownerId]);
$ex->execute(['water',      'Water bill June',         null, 3500, date('Y-m-d', strtotime('-20 days')), null, $ownerId]);
$ex->execute(['seed',       'Sukuma wiki seed 1kg',    'Simlaw', 1200, date('Y-m-d', strtotime('-6 days')), $batchIds['SUK'], $ownerId]);

// ---- Labour ----
$lb = $pdo->prepare(
    'INSERT INTO labour (worker_name, task, amount, labour_date, batch_id, created_by)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$lb->execute(['Kimutai', 'Potting avocado seedlings', 1500, date('Y-m-d', strtotime('-150 days')), $batchIds['AVO'], $ownerId]);
$lb->execute(['Wanjiru', 'Weeding and watering',       800, date('Y-m-d', strtotime('-30 days')), null, $ownerId]);

// ---- Customers ----
$cu = $pdo->prepare('INSERT INTO customers (name, phone, notes, created_by) VALUES (?, ?, ?, ?)');
$cu->execute(['Moi University Farm', '0722111222', 'Buys in bulk each season', $ownerId]);
$custBulk = (int)$pdo->lastInsertId();
$cu->execute(['Sally Kones', '0733222333', null, $ownerId]);
$custSally = (int)$pdo->lastInsertId();

// ---- Sales (stock already deducted in batch current_qty above) ----
$sa = $pdo->prepare(
    'INSERT INTO sales (customer_id, sale_date, total_amount, amount_paid, payment_method,
                        mpesa_ref, status, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$si = $pdo->prepare(
    'INSERT INTO sale_items (sale_id, batch_id, qty, unit_price, line_total, created_by)
     VALUES (?, ?, ?, ?, ?, ?)'
);

// Paid M-Pesa sale: 100 avocado + 60 mango to Moi University Farm
$sa->execute([$custBulk, date('Y-m-d', strtotime('-15 days')), 22200, 22200, 'mpesa', 'SFE8XK21QT', 'paid', 2]);
$sale1 = (int)$pdo->lastInsertId();
$si->execute([$sale1, $batchIds['AVO'], 100, 150, 15000, 2]);
$si->execute([$sale1, $batchIds['MGA'],  60, 120,  7200, 2]);

// Partial/credit sale: 20 avocado to Sally, half paid
$sa->execute([$custSally, date('Y-m-d', strtotime('-5 days')), 3000, 1500, 'mixed', null, 'partial', 3]);
$sale2 = (int)$pdo->lastInsertId();
$si->execute([$sale2, $batchIds['AVO'], 20, 150, 3000, 3]);
$pm = $pdo->prepare(
    'INSERT INTO payments (sale_id, amount, payment_method, mpesa_ref, paid_at, created_by)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$pm->execute([$sale2, 1500, 'cash', null, date('Y-m-d H:i:s', strtotime('-5 days')), 3]);

// ---- Open pre-order ----
$pdo->prepare(
    'INSERT INTO preorders (customer_id, species_id, qty, agreed_unit_price, deposit_paid,
                            expected_date, status, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([$custBulk, $speciesIds['MAC'], 50, 240, 5000,
            date('Y-m-d', strtotime('+30 days')), 'open', $ownerId]);

$pdo->prepare("UPDATE settings SET v = ? WHERE k = 'nursery_name'")
    ->execute(['Eldoret Greenline Nursery']);

echo "Seed complete.\n";
echo "Owner login:  phone 0700000001  password $ownerPass\n";
echo "Worker login: phone 0700000002 or 0700000003  password $workerPass\n";
