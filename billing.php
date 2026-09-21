<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$sql = 'SELECT id, doc_no, doc_date, customer_name, payment_mode, net_amount, status, pra_invoice_no FROM invoice_m WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (doc_no LIKE ? OR customer_name LIKE ? OR ntn_cnic LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$statuses = ['PREPARED', 'SUBMITTED', 'PRA_APPROVED', 'PRA_REJECTED', 'PENDING', 'CANCELED'];

layout_start('Billing', 'billing', $user);
?>
<div class="card-soft p-3 mb-3">
    <form class="toolbar" method="get">
        <div>
            <label class="form-label mb-1">Search</label>
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Invoice no. or customer" style="min-width:240px">
        </div>
        <div>
            <label class="form-label mb-1">Status</label>
            <select class="form-select" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('billing.php')) ?>">Reset</a>
        <div class="ms-auto">
            <a class="btn btn-primary" href="<?= e(base_url('invoice_create.php')) ?>"><i class="bi bi-plus-lg"></i> New invoice</a>
        </div>
    </form>
</div>

<div class="card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Payment</th>
                    <th class="text-end">Grand total</th>
                    <th>Status</th>
                    <th>PRA ref.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-muted py-4">No invoices found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(base_url('invoice_view.php?id=' . (int) $row['id'])) ?>"><?= e($row['doc_no']) ?></a></td>
                    <td><?= e($row['doc_date']) ?></td>
                    <td><?= e($row['customer_name']) ?></td>
                    <td><?= e($row['payment_mode']) ?></td>
                    <td class="text-end"><?= e(money_label($row['net_amount'])) ?></td>
                    <td><span class="badge-st <?= e(status_class($row['status'])) ?>"><?= e(status_label($row['status'])) ?></span></td>
                    <td><?= e($row['pra_invoice_no'] ?: '—') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('invoice_print.php?id=' . (int) $row['id'])) ?>">Print</a>
                        <?php if (invoice_can_retry($row['status']) && $row['status'] !== 'PRA_APPROVED'): ?>
                            <form class="d-inline" method="post" action="<?= e(base_url('invoice_retry.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-sm btn-outline-primary" type="submit" data-confirm="Retry PRA submission for <?= e($row['doc_no']) ?>?">Retry</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
layout_end();
