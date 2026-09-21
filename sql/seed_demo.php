<?php
/**
 * Inserts demo customers, invoices, lines, and PRA logs.
 * Safe to re-run: skips invoice numbers that already exist.
 *
 * Usage: C:\xampp\php\php.exe sql/seed_demo.php
 */

require_once dirname(__DIR__) . '/includes/db.php';

$pdo = db();
$pdo->beginTransaction();

$newCustomers = [
    ['Faisalabad Mills', '5210987-3', 'Registered', '+92 41 111 888 999', 'Jaranwala Road, Faisalabad'],
    ['Sialkot Exports', '6112345-6', 'Registered', '+92 52 111 222 111', 'Saddar Bazaar, Sialkot'],
    ['Islamabad Tech Hub', '9011223-4', 'Registered', '+92 51 111 333 444', 'Blue Area, Islamabad'],
    ['Gujranwala Steel', '', 'Unregistered', '+92 55 111 666 777', 'GT Road, Gujranwala'],
    ['Bahawalpur Services', '35202-7654321-8', 'Individual', '+92 300 7654321', 'Satellite Town, Bahawalpur'],
];

$insCust = $pdo->prepare(
    'INSERT INTO customers (name, ntn_cnic, customer_type, phone, address)
     SELECT ?, ?, ?, ?, ?
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM customers WHERE name = ?)'
);
foreach ($newCustomers as $c) {
    $insCust->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[0]]);
}

$customers = $pdo->query('SELECT id, name, ntn_cnic, customer_type FROM customers ORDER BY id')->fetchAll();
$items = $pdo->query('SELECT id, name, default_rate, default_tax_rate FROM items WHERE is_active = 1 ORDER BY id')->fetchAll();
if (!$customers || !$items) {
    $pdo->rollBack();
    fwrite(STDERR, "Customers or items missing. Import sql/schema.sql first.\n");
    exit(1);
}

$today = new DateTimeImmutable('today');

$plans = [
    // Last 6 months of mixed activity, including several invoices dated today.
    ['offset' => '-5 months', 'day' => 4,  'cust' => 0, 'item' => 0, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-5 months', 'day' => 12, 'cust' => 1, 'item' => 2, 'qty' => 2, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => '-5 months', 'day' => 21, 'cust' => 2, 'item' => 1, 'qty' => 1, 'status' => 'PRA_REJECTED', 'pay' => 'Card'],
    ['offset' => '-5 months', 'day' => 28, 'cust' => 3, 'item' => 4, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-4 months', 'day' => 3,  'cust' => 4, 'item' => 3, 'qty' => 3, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => '-4 months', 'day' => 9,  'cust' => 5, 'item' => 0, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-4 months', 'day' => 16, 'cust' => 6, 'item' => 2, 'qty' => 1, 'status' => 'PENDING', 'pay' => 'Cash'],
    ['offset' => '-4 months', 'day' => 24, 'cust' => 0, 'item' => 1, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Card'],
    ['offset' => '-3 months', 'day' => 2,  'cust' => 1, 'item' => 4, 'qty' => 2, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-3 months', 'day' => 8,  'cust' => 2, 'item' => 0, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => '-3 months', 'day' => 14, 'cust' => 3, 'item' => 3, 'qty' => 4, 'status' => 'PRA_REJECTED', 'pay' => 'Card'],
    ['offset' => '-3 months', 'day' => 19, 'cust' => 4, 'item' => 1, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-3 months', 'day' => 27, 'cust' => 5, 'item' => 2, 'qty' => 1, 'status' => 'SUBMITTED', 'pay' => 'Cash'],
    ['offset' => '-2 months', 'day' => 5,  'cust' => 6, 'item' => 0, 'qty' => 2, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-2 months', 'day' => 11, 'cust' => 0, 'item' => 4, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Card'],
    ['offset' => '-2 months', 'day' => 17, 'cust' => 1, 'item' => 1, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => '-2 months', 'day' => 22, 'cust' => 2, 'item' => 3, 'qty' => 2, 'status' => 'PENDING', 'pay' => 'Bank'],
    ['offset' => '-2 months', 'day' => 29, 'cust' => 3, 'item' => 2, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => '-1 month',  'day' => 4,  'cust' => 4, 'item' => 0, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-1 month',  'day' => 9,  'cust' => 5, 'item' => 1, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Card'],
    ['offset' => '-1 month',  'day' => 13, 'cust' => 6, 'item' => 4, 'qty' => 3, 'status' => 'PRA_REJECTED', 'pay' => 'Cash'],
    ['offset' => '-1 month',  'day' => 18, 'cust' => 0, 'item' => 2, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => '-1 month',  'day' => 23, 'cust' => 1, 'item' => 3, 'qty' => 2, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => '-1 month',  'day' => 27, 'cust' => 2, 'item' => 0, 'qty' => 1, 'status' => 'PENDING', 'pay' => 'Card'],
    ['offset' => 'this month', 'day' => 3,  'cust' => 3, 'item' => 1, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => 'this month', 'day' => 7,  'cust' => 4, 'item' => 4, 'qty' => 2, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => 'this month', 'day' => 10, 'cust' => 5, 'item' => 2, 'qty' => 1, 'status' => 'PRA_REJECTED', 'pay' => 'Card'],
    ['offset' => 'this month', 'day' => 12, 'cust' => 6, 'item' => 0, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => 'today',     'day' => 0,  'cust' => 0, 'item' => 1, 'qty' => 1, 'status' => 'PRA_APPROVED', 'pay' => 'Cash'],
    ['offset' => 'today',     'day' => 0,  'cust' => 1, 'item' => 3, 'qty' => 2, 'status' => 'PRA_APPROVED', 'pay' => 'Bank'],
    ['offset' => 'today',     'day' => 0,  'cust' => 2, 'item' => 2, 'qty' => 1, 'status' => 'PENDING', 'pay' => 'Card'],
    ['offset' => 'today',     'day' => 0,  'cust' => 4, 'item' => 4, 'qty' => 1, 'status' => 'PREPARED', 'pay' => 'Cash'],
];

$exists = $pdo->prepare('SELECT id FROM invoice_m WHERE doc_no = ?');
$insM = $pdo->prepare(
    'INSERT INTO invoice_m
        (doc_no, doc_date, customer_id, customer_name, ntn_cnic, customer_type, payment_mode,
         sub_total, tax_amount, net_amount, status, pra_status, pra_invoice_no, created_by, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
);
$insD = $pdo->prepare(
    'INSERT INTO invoice_d (m_id, item_id, item_name, description, qty, rate, amount, tax_rate, tax_amount, net_amount)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insLog = $pdo->prepare(
    'INSERT INTO pra_invoice_log (invoice_id, request_data, response_data, pra_status, pra_invoice_no, error_message, submitted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$seq = (int) $pdo->query("SELECT next_val FROM doc_sequences WHERE name = 'invoice'")->fetchColumn();
if ($seq < 1) {
    $seq = 1;
}

$created = 0;
foreach ($plans as $i => $plan) {
    $docNo = 'INV-' . str_pad((string) (101 + $i), 6, '0', STR_PAD_LEFT);
    $exists->execute([$docNo]);
    if ($exists->fetch()) {
        continue;
    }

    if ($plan['offset'] === 'today') {
        $date = $today;
    } else {
        $base = new DateTimeImmutable('first day of ' . $plan['offset']);
        $lastDay = (int) $base->format('t');
        $day = min((int) $plan['day'], $lastDay);
        $date = $base->setDate((int) $base->format('Y'), (int) $base->format('n'), $day);
    }

    $cust = $customers[$plan['cust'] % count($customers)];
    $item = $items[$plan['item'] % count($items)];
    $qty = (float) $plan['qty'];
    $rate = (float) $item['default_rate'];
    $taxRate = (float) $item['default_tax_rate'];
    $amount = round($qty * $rate, 2);
    $taxAmount = round($amount * $taxRate / 100, 2);
    $net = round($amount + $taxAmount, 2);
    $status = $plan['status'];
    $praNo = $status === 'PRA_APPROVED' ? 'PRA-' . strtoupper(substr(md5($docNo), 0, 10)) : '';
    $praStatus = [
        'PRA_APPROVED' => 'APPROVED',
        'PRA_REJECTED' => 'REJECTED',
        'PENDING' => 'ERROR',
        'SUBMITTED' => 'SUBMITTED',
        'PREPARED' => '',
        'CANCELED' => '',
    ][$status];
    $createdAt = $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', 9 + ($i % 8), ($i * 7) % 60);

    $insM->execute([
        $docNo,
        $date->format('Y-m-d'),
        $cust['id'],
        $cust['name'],
        $cust['ntn_cnic'],
        $cust['customer_type'],
        $plan['pay'],
        $amount,
        $taxAmount,
        $net,
        $status,
        $praStatus,
        $praNo,
        $createdAt,
    ]);
    $mid = (int) $pdo->lastInsertId();
    $insD->execute([
        $mid,
        $item['id'],
        $item['name'],
        'Demo billing ' . $date->format('M Y'),
        $qty,
        $rate,
        $amount,
        $taxRate,
        $taxAmount,
        $net,
    ]);

    if ($status !== 'PREPARED') {
        $req = json_encode(['doc_no' => $docNo, 'buyer' => $cust['name'], 'net' => $net], JSON_UNESCAPED_UNICODE);
        $ok = $status === 'PRA_APPROVED';
        $resp = json_encode([
            'mode' => 'sandbox-mock',
            'code' => $ok ? 'OK' : ($status === 'PRA_REJECTED' ? 'REJECTED' : 'TIMEOUT'),
            'praInvoiceNo' => $praNo,
        ], JSON_UNESCAPED_UNICODE);
        $err = $ok ? '' : ($status === 'PRA_REJECTED' ? 'PRA rejected: buyer NTN mismatch (demo).' : 'PRA service temporarily unavailable (demo).');
        $insLog->execute([$mid, $req, $resp, $praStatus, $praNo, $err, $createdAt]);
    }
    $created++;
    $seq = max($seq, 101 + $i + 1);
}

$pdo->prepare("UPDATE doc_sequences SET next_val = GREATEST(next_val, ?) WHERE name = 'invoice'")->execute([$seq]);
$pdo->commit();

echo "Demo seed complete. Inserted {$created} invoices.\n";
