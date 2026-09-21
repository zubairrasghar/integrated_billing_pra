<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pra_adapter.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('invoice_create.php');
}
verify_csrf();

$pdo = db();
$docDate = (string) ($_POST['doc_date'] ?? '');
$customerId = (int) ($_POST['customer_id'] ?? 0);
$customerName = trim((string) ($_POST['customer_name'] ?? ''));
$ntn = trim((string) ($_POST['ntn_cnic'] ?? ''));
$customerType = (string) ($_POST['customer_type'] ?? 'Unregistered');
$paymentMode = (string) ($_POST['payment_mode'] ?? 'Cash');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate)) {
    flash('form', 'Invoice date is invalid.', 'danger');
    redirect('invoice_create.php');
}
if ($customerName === '') {
    flash('form', 'Customer name is required.', 'danger');
    redirect('invoice_create.php');
}
if (!in_array($customerType, ['Registered', 'Unregistered', 'Individual'], true)) {
    $customerType = 'Unregistered';
}
if (!in_array($paymentMode, ['Cash', 'Bank', 'Card'], true)) {
    $paymentMode = 'Cash';
}

$itemIds = $_POST['item_id'] ?? [];
$itemNames = $_POST['item_name'] ?? [];
$descs = $_POST['description'] ?? [];
$qtys = $_POST['qty'] ?? [];
$rates = $_POST['rate'] ?? [];
$taxRates = $_POST['tax_rate'] ?? [];

$lines = [];
$count = max(count($itemNames), count($qtys), count($rates));
for ($i = 0; $i < $count; $i++) {
    $name = trim((string) ($itemNames[$i] ?? ''));
    $qty = round_money($qtys[$i] ?? 0);
    $rate = round_money($rates[$i] ?? 0);
    if ($name === '' && $qty <= 0 && $rate <= 0) {
        continue;
    }
    if ($name === '') {
        continue;
    }
    $taxRate = round_money($taxRates[$i] ?? 0);
    $amount = round_money($qty * $rate);
    $taxAmount = round_money($amount * $taxRate / 100);
    $net = round_money($amount + $taxAmount);
    $itemId = (int) ($itemIds[$i] ?? 0);
    $lines[] = [
        'item_id' => $itemId > 0 ? $itemId : null,
        'item_name' => $name,
        'description' => trim((string) ($descs[$i] ?? '')),
        'qty' => $qty,
        'rate' => $rate,
        'amount' => $amount,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'net_amount' => $net,
    ];
}

if (!$lines) {
    flash('form', 'Add at least one invoice line.', 'danger');
    redirect('invoice_create.php');
}

$subTotal = round_money(array_sum(array_column($lines, 'amount')));
$taxTotal = round_money(array_sum(array_column($lines, 'tax_amount')));
$netTotal = round_money(array_sum(array_column($lines, 'net_amount')));

try {
    $pdo->beginTransaction();
    $docNo = next_doc_no($pdo);
    $ins = $pdo->prepare(
        'INSERT INTO invoice_m
            (doc_no, doc_date, customer_id, customer_name, ntn_cnic, customer_type, payment_mode,
             sub_total, tax_amount, net_amount, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $docNo,
        $docDate,
        $customerId > 0 ? $customerId : null,
        $customerName,
        $ntn,
        $customerType,
        $paymentMode,
        $subTotal,
        $taxTotal,
        $netTotal,
        'PREPARED',
        $user['id'],
    ]);
    $invoiceId = (int) $pdo->lastInsertId();

    $lineStmt = $pdo->prepare(
        'INSERT INTO invoice_d (m_id, item_id, item_name, description, qty, rate, amount, tax_rate, tax_amount, net_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($lines as $line) {
        $lineStmt->execute([
            $invoiceId,
            $line['item_id'],
            $line['item_name'],
            $line['description'],
            $line['qty'],
            $line['rate'],
            $line['amount'],
            $line['tax_rate'],
            $line['tax_amount'],
            $line['net_amount'],
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('form', 'Could not save invoice. ' . $e->getMessage(), 'danger');
    redirect('invoice_create.php');
}

$invoice = $pdo->prepare('SELECT * FROM invoice_m WHERE id = ?');
$invoice->execute([$invoiceId]);
$header = $invoice->fetch();
$detail = $pdo->prepare('SELECT * FROM invoice_d WHERE m_id = ? ORDER BY id');
$detail->execute([$invoiceId]);
$savedLines = $detail->fetchAll();

$pra = pra_submit_invoice($pdo, $header, $savedLines);

if ($pra['ok']) {
    flash('form', 'Invoice ' . $header['doc_no'] . ' saved and approved by PRA (' . $pra['pra_invoice_no'] . ').', 'success');
} else {
    flash('form', 'Invoice ' . $header['doc_no'] . ' saved. PRA: ' . ($pra['error_message'] ?: $pra['pra_status']), 'warning');
}

redirect('invoice_print.php?id=' . $invoiceId);
