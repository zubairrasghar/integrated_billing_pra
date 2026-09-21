<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pra_adapter.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('billing.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM invoice_m WHERE id = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('form', 'Invoice not found.', 'danger');
    redirect('billing.php');
}
if ($invoice['status'] === 'PRA_APPROVED') {
    flash('form', 'This invoice is already PRA approved.', 'info');
    redirect('invoice_view.php?id=' . $id);
}
if ($invoice['status'] === 'CANCELED') {
    flash('form', 'Canceled invoices cannot be submitted.', 'danger');
    redirect('invoice_view.php?id=' . $id);
}

$lines = $pdo->prepare('SELECT * FROM invoice_d WHERE m_id = ? ORDER BY id');
$lines->execute([$id]);
$pra = pra_submit_invoice($pdo, $invoice, $lines->fetchAll());

if ($pra['ok']) {
    flash('form', 'PRA accepted invoice ' . $invoice['doc_no'] . ' (' . $pra['pra_invoice_no'] . ').', 'success');
} else {
    flash('form', 'PRA retry failed: ' . ($pra['error_message'] ?: $pra['pra_status']), 'warning');
}

redirect('invoice_view.php?id=' . $id);
