<?php

require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM invoice_m WHERE id = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('form', 'Invoice not found.', 'danger');
    redirect('billing.php');
}

$lines = $pdo->prepare('SELECT * FROM invoice_d WHERE m_id = ? ORDER BY id');
$lines->execute([$id]);
$details = $lines->fetchAll();
$company = company_settings();
$app = app_config();
$qrData = $invoice['pra_invoice_no'] !== '' ? $invoice['pra_invoice_no'] : $invoice['doc_no'];
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($qrData);
$flash = flash('form');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($invoice['doc_no']) ?> · Print</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body style="background:#e8edf3">
<div class="no-print text-center py-3">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> d-inline-block"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <div>
        <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('invoice_view.php?id=' . (int) $invoice['id'])) ?>">Invoice details</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('billing.php')) ?>">Billing</a>
        <?php if (invoice_can_retry($invoice['status']) && $invoice['status'] !== 'PRA_APPROVED'): ?>
            <form class="d-inline" method="post" action="<?= e(base_url('invoice_retry.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
                <button class="btn btn-outline-primary" type="submit">Retry PRA</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="print-sheet">
    <div class="print-head">
        <div>
            <div style="font-size:12px;letter-spacing:.12em;color:#1aa58a;font-weight:700">TAX INVOICE</div>
            <h1><?= e($company['name']) ?></h1>
            <div><?= e($company['address']) ?><?= $company['city'] ? ', ' . e($company['city']) : '' ?></div>
            <div>NTN <?= e($company['ntn']) ?> &nbsp;|&nbsp; STRN <?= e($company['strn']) ?></div>
            <div><?= e($company['phone']) ?> <?= $company['email'] ? ' · ' . e($company['email']) : '' ?></div>
        </div>
        <div class="print-meta">
            <div><strong>Invoice #</strong> <?= e($invoice['doc_no']) ?></div>
            <div><strong>Date</strong> <?= e(date('d M Y', strtotime($invoice['doc_date']))) ?></div>
            <div><strong>Status</strong> <?= e(status_label($invoice['status'])) ?></div>
            <div><strong>PRA ref.</strong> <?= e($invoice['pra_invoice_no'] ?: 'Pending') ?></div>
            <img class="qr-box mt-2" src="<?= e($qrUrl) ?>" alt="PRA QR">
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-7">
            <div class="text-muted" style="font-size:12px">Bill to</div>
            <strong><?= e($invoice['customer_name']) ?></strong><br>
            Type: <?= e($invoice['customer_type']) ?><br>
            NTN/CNIC: <?= e($invoice['ntn_cnic'] ?: '—') ?><br>
            Payment: <?= e($invoice['payment_mode']) ?>
        </div>
        <div class="col-5 text-end">
            Prepared by <?= e($user['full_name']) ?>
        </div>
    </div>

    <table class="table table-sm">
        <thead>
            <tr>
                <th>#</th>
                <th>Service / item</th>
                <th>Description</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Rate</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Tax</th>
                <th class="text-end">Net</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $i => $line): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($line['item_name']) ?></td>
                <td><?= e($line['description']) ?></td>
                <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $line['qty'], 3, '.', ''), '0'), '.')) ?></td>
                <td class="text-end"><?= e(money($line['rate'])) ?></td>
                <td class="text-end"><?= e(money($line['amount'])) ?></td>
                <td class="text-end"><?= e(money($line['tax_amount'])) ?></td>
                <td class="text-end"><?= e(money($line['net_amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-end">
        <table style="width:280px">
            <tr><td>Sub total</td><td class="text-end"><?= e(money_label($invoice['sub_total'])) ?></td></tr>
            <tr><td>PRA sales tax</td><td class="text-end"><?= e(money_label($invoice['tax_amount'])) ?></td></tr>
            <tr style="font-weight:700;font-size:16px"><td>Grand total</td><td class="text-end"><?= e(money_label($invoice['net_amount'])) ?></td></tr>
        </table>
    </div>

    <div class="mt-5 d-flex justify-content-between" style="font-size:12px;color:#555">
        <div>
            This is a computer generated invoice for <?= e($app['name']) ?>.<br>
            PRA digital invoicing <?= $invoice['pra_invoice_no'] ? 'reference ' . e($invoice['pra_invoice_no']) : 'submission pending' ?>.
        </div>
        <div class="text-center" style="width:180px;border-top:1px solid #ccc;padding-top:6px">
            Authorized signature
        </div>
    </div>
</div>
</body>
</html>
