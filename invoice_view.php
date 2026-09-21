<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

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

$logs = $pdo->prepare('SELECT * FROM pra_invoice_log WHERE invoice_id = ? ORDER BY id DESC');
$logs->execute([$id]);
$logRows = $logs->fetchAll();

layout_start('Invoice ' . $invoice['doc_no'], 'billing', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge-st <?= e(status_class($invoice['status'])) ?>"><?= e(status_label($invoice['status'])) ?></span>
        <?php if ($invoice['pra_invoice_no']): ?>
            <span class="ms-2 text-muted">PRA ref: <strong><?= e($invoice['pra_invoice_no']) ?></strong></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('billing.php')) ?>">Back to list</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('invoice_print.php?id=' . (int) $invoice['id'])) ?>">Print</a>
        <?php if (invoice_can_retry($invoice['status']) && $invoice['status'] !== 'PRA_APPROVED'): ?>
            <form method="post" action="<?= e(base_url('invoice_retry.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
                <button class="btn btn-primary" type="submit" data-confirm="Retry PRA submission?">Retry PRA</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card-soft form-section">
    <h2>Header</h2>
    <div class="row g-3">
        <div class="col-md-3"><div class="text-muted">Invoice no.</div><strong><?= e($invoice['doc_no']) ?></strong></div>
        <div class="col-md-3"><div class="text-muted">Date</div><strong><?= e($invoice['doc_date']) ?></strong></div>
        <div class="col-md-3"><div class="text-muted">Customer</div><strong><?= e($invoice['customer_name']) ?></strong></div>
        <div class="col-md-3"><div class="text-muted">Payment</div><strong><?= e($invoice['payment_mode']) ?></strong></div>
        <div class="col-md-3"><div class="text-muted">NTN / CNIC</div><strong><?= e($invoice['ntn_cnic'] ?: '—') ?></strong></div>
        <div class="col-md-3"><div class="text-muted">Customer type</div><strong><?= e($invoice['customer_type']) ?></strong></div>
        <div class="col-md-3"><div class="text-muted">PRA status</div><strong><?= e($invoice['pra_status'] ?: '—') ?></strong></div>
    </div>
</div>

<div class="card-soft form-section">
    <h2>Lines</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Tax %</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Net</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($details as $line): ?>
                <tr>
                    <td><?= e($line['item_name']) ?></td>
                    <td><?= e($line['description']) ?></td>
                    <td class="text-end"><?= e(number_format((float) $line['qty'], 3)) ?></td>
                    <td class="text-end"><?= e(money($line['rate'])) ?></td>
                    <td class="text-end"><?= e(money($line['amount'])) ?></td>
                    <td class="text-end"><?= e(money($line['tax_rate'])) ?></td>
                    <td class="text-end"><?= e(money($line['tax_amount'])) ?></td>
                    <td class="text-end"><?= e(money($line['net_amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="totals-box mt-3">
        <div class="row-line"><span>Sub total</span><strong><?= e(money($invoice['sub_total'])) ?></strong></div>
        <div class="row-line"><span>PRA sales tax</span><strong><?= e(money($invoice['tax_amount'])) ?></strong></div>
        <div class="row-line grand"><span>Grand total</span><span><?= e(money_label($invoice['net_amount'])) ?></span></div>
    </div>
</div>

<div class="card-soft form-section">
    <h2>PRA communication log</h2>
    <?php if (!$logRows): ?>
        <p class="text-muted mb-0">No PRA submissions yet.</p>
    <?php endif; ?>
    <?php foreach ($logRows as $log): ?>
        <div class="border rounded p-3 mb-2">
            <div class="d-flex justify-content-between">
                <strong><?= e($log['pra_status']) ?> <?= e($log['pra_invoice_no']) ?></strong>
                <span class="text-muted"><?= e($log['submitted_at']) ?></span>
            </div>
            <?php if ($log['error_message']): ?>
                <div class="text-danger mt-1"><?= e($log['error_message']) ?></div>
            <?php endif; ?>
            <details class="mt-2">
                <summary>Request / response</summary>
                <pre class="small mt-2 mb-0" style="white-space:pre-wrap"><?= e($log['request_data']) ?></pre>
                <pre class="small mt-2 mb-0" style="white-space:pre-wrap"><?= e($log['response_data']) ?></pre>
            </details>
        </div>
    <?php endforeach; ?>
</div>
<?php
layout_end();
