<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$pdo = db();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$prevMonthStart = date('Y-m-01', strtotime('first day of last month'));
$prevMonthEnd = date('Y-m-t', strtotime('first day of last month'));
$sixStart = date('Y-m-01', strtotime('first day of -5 month'));
$fourteen = date('Y-m-d', strtotime('-13 days'));

$kpis = [
    'invoices_today' => 0,
    'sales_today' => 0,
    'tax_today' => 0,
    'pra_approved' => 0,
    'pra_failed' => 0,
    'pending' => 0,
];

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS invoices_today,
        COALESCE(SUM(net_amount), 0) AS sales_today,
        COALESCE(SUM(tax_amount), 0) AS tax_today
     FROM invoice_m
     WHERE doc_date = ? AND status <> 'CANCELED'"
);
$stmt->execute([$today]);
$kpis = array_merge($kpis, $stmt->fetch() ?: []);

$kpis['pra_approved'] = (int) $pdo->query("SELECT COUNT(*) FROM invoice_m WHERE status = 'PRA_APPROVED'")->fetchColumn();
$kpis['pra_failed'] = (int) $pdo->query("SELECT COUNT(*) FROM invoice_m WHERE status = 'PRA_REJECTED'")->fetchColumn();
$kpis['pending'] = (int) $pdo->query("SELECT COUNT(*) FROM invoice_m WHERE status IN ('PENDING','PREPARED','SUBMITTED')")->fetchColumn();

$monthStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(net_amount),0) FROM invoice_m WHERE doc_date >= ? AND status <> 'CANCELED'"
);
$monthStmt->execute([$monthStart]);
$monthSales = (float) $monthStmt->fetchColumn();
$prevStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(net_amount),0) FROM invoice_m WHERE doc_date BETWEEN ? AND ? AND status <> 'CANCELED'"
);
$prevStmt->execute([$prevMonthStart, $prevMonthEnd]);
$prevSales = (float) $prevStmt->fetchColumn();
$salesDelta = $prevSales > 0 ? round((($monthSales - $prevSales) / $prevSales) * 100, 1) : ($monthSales > 0 ? 100 : 0);

$months = [];
$mStmt = $pdo->query(
    "SELECT DATE_FORMAT(doc_date, '%Y-%m') AS ym,
            COUNT(*) AS invoices,
            COALESCE(SUM(net_amount), 0) AS sales,
            COALESCE(SUM(tax_amount), 0) AS tax
     FROM invoice_m
     WHERE doc_date >= '{$sixStart}'
       AND status <> 'CANCELED'
     GROUP BY ym
     ORDER BY ym ASC"
);
$byMonth = [];
foreach ($mStmt as $row) {
    $byMonth[$row['ym']] = $row;
}
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("first day of -{$i} month"));
    $months[] = [
        'key' => $key,
        'label' => date('M Y', strtotime($key . '-01')),
        'invoices' => (int) ($byMonth[$key]['invoices'] ?? 0),
        'sales' => (float) ($byMonth[$key]['sales'] ?? 0),
        'tax' => (float) ($byMonth[$key]['tax'] ?? 0),
    ];
}

$statusKeys = ['PRA_APPROVED', 'PENDING', 'PRA_REJECTED', 'PREPARED', 'SUBMITTED'];
$statusCounts = array_fill_keys($statusKeys, 0);
foreach ($pdo->query("SELECT status, COUNT(*) c FROM invoice_m GROUP BY status") as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['c'];
    }
}

$payCounts = ['Cash' => 0, 'Bank' => 0, 'Card' => 0];
foreach ($pdo->query("SELECT payment_mode, COALESCE(SUM(net_amount),0) amt FROM invoice_m WHERE status <> 'CANCELED' GROUP BY payment_mode") as $row) {
    $payCounts[$row['payment_mode']] = (float) $row['amt'];
}

$stackRows = $pdo->query(
    "SELECT DATE_FORMAT(doc_date, '%Y-%m') ym, status, COUNT(*) c
     FROM invoice_m
     WHERE doc_date >= '{$sixStart}' AND status <> 'CANCELED'
     GROUP BY ym, status"
)->fetchAll();
$stackMap = [];
foreach ($stackRows as $row) {
    $stackMap[$row['ym']][$row['status']] = (int) $row['c'];
}
$stackApproved = [];
$stackPending = [];
$stackRejected = [];
foreach ($months as $m) {
    $cell = $stackMap[$m['key']] ?? [];
    $stackApproved[] = (int) ($cell['PRA_APPROVED'] ?? 0);
    $stackPending[] = (int) (($cell['PENDING'] ?? 0) + ($cell['PREPARED'] ?? 0) + ($cell['SUBMITTED'] ?? 0));
    $stackRejected[] = (int) ($cell['PRA_REJECTED'] ?? 0);
}

$daily = [];
$dStmt = $pdo->prepare(
    "SELECT doc_date, COUNT(*) invoices, COALESCE(SUM(net_amount),0) sales
     FROM invoice_m
     WHERE doc_date >= ? AND status <> 'CANCELED'
     GROUP BY doc_date"
);
$dStmt->execute([$fourteen]);
$byDay = [];
foreach ($dStmt as $row) {
    $byDay[$row['doc_date']] = $row;
}
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $daily[] = [
        'label' => date('d M', strtotime($d)),
        'invoices' => (int) ($byDay[$d]['invoices'] ?? 0),
        'sales' => (float) ($byDay[$d]['sales'] ?? 0),
    ];
}

$topCustomers = $pdo->query(
    "SELECT customer_name, COUNT(*) invoices, COALESCE(SUM(net_amount),0) sales
     FROM invoice_m
     WHERE status <> 'CANCELED'
     GROUP BY customer_name
     ORDER BY sales DESC
     LIMIT 5"
)->fetchAll();
$maxCust = 0;
foreach ($topCustomers as $c) {
    $maxCust = max($maxCust, (float) $c['sales']);
}

$recent = $pdo->query(
    "SELECT id, doc_no, doc_date, customer_name, net_amount, status, pra_invoice_no
     FROM invoice_m
     ORDER BY id DESC
     LIMIT 8"
)->fetchAll();

$chart = [
    'months' => array_column($months, 'label'),
    'sales' => array_map('floatval', array_column($months, 'sales')),
    'tax' => array_map('floatval', array_column($months, 'tax')),
    'stackApproved' => $stackApproved,
    'stackPending' => $stackPending,
    'stackRejected' => $stackRejected,
    'statusLabels' => ['Approved', 'Pending', 'Rejected', 'Prepared', 'Submitted'],
    'statusValues' => array_values($statusCounts),
    'payLabels' => array_keys($payCounts),
    'payValues' => array_values($payCounts),
    'days' => array_column($daily, 'label'),
    'daySales' => array_map('floatval', array_column($daily, 'sales')),
    'dayInvoices' => array_map('intval', array_column($daily, 'invoices')),
];

$kpiMeta = [
    [
        'label' => 'Invoices today',
        'value' => (string) (int) $kpis['invoices_today'],
        'icon' => 'bi-receipt',
        'tone' => 'navy',
        'hint' => date('d M Y'),
    ],
    [
        'label' => 'Sales today',
        'value' => money_label($kpis['sales_today']),
        'icon' => 'bi-graph-up-arrow',
        'tone' => 'teal',
        'hint' => 'This month ' . money_label($monthSales),
    ],
    [
        'label' => 'Tax today',
        'value' => money_label($kpis['tax_today']),
        'icon' => 'bi-percent',
        'tone' => 'navy',
        'hint' => 'PRA sales tax',
    ],
    [
        'label' => 'PRA approved',
        'value' => (string) (int) $kpis['pra_approved'],
        'icon' => 'bi-check2-circle',
        'tone' => 'teal',
        'hint' => 'Lifetime accepted',
    ],
    [
        'label' => 'PRA failed',
        'value' => (string) (int) $kpis['pra_failed'],
        'icon' => 'bi-x-octagon',
        'tone' => 'warn',
        'hint' => 'Needs retry',
    ],
    [
        'label' => 'Pending PRA',
        'value' => (string) (int) $kpis['pending'],
        'icon' => 'bi-hourglass-split',
        'tone' => 'pending',
        'hint' => 'In queue',
    ],
];

layout_start('Dashboard', 'dashboard', $user);
?>
<div class="dash-hero card-soft mb-4">
    <div>
        <div class="top-kicker">Live overview</div>
        <h2>Billing pulse</h2>
        <p>Month-to-date sales <?= e(money_label($monthSales)) ?>
            <span class="delta <?= $salesDelta >= 0 ? 'up' : 'down' ?>">
                <?= $salesDelta >= 0 ? '+' : '' ?><?= e((string) $salesDelta) ?>% vs last month
            </span>
        </p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('invoice_create.php')) ?>">Create invoice</a>
</div>

<div class="kpi-grid mb-4">
    <?php foreach ($kpiMeta as $k): ?>
        <div class="card-soft kpi tone-<?= e($k['tone']) ?>">
            <div class="kpi-icon"><i class="bi <?= e($k['icon']) ?>"></i></div>
            <div>
                <div class="label"><?= e($k['label']) ?></div>
                <div class="value"><?= e($k['value']) ?></div>
                <div class="hint"><?= e($k['hint']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card-soft p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="section-title mb-0">Sales vs PRA tax</h2>
                <span class="text-muted small">Last 6 months</span>
            </div>
            <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft p-3 h-100">
            <h2 class="section-title">PRA status mix</h2>
            <div class="chart-wrap chart-wrap-sm"><canvas id="statusChart"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="card-soft p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="section-title mb-0">Invoice volume by status</h2>
                <span class="text-muted small">Stacked monthly</span>
            </div>
            <div class="chart-wrap"><canvas id="stackChart"></canvas></div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card-soft p-3 h-100">
            <h2 class="section-title">Daily sales (14 days)</h2>
            <div class="chart-wrap chart-wrap-sm"><canvas id="dailyChart"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card-soft p-3 h-100">
            <h2 class="section-title">Top customers</h2>
            <?php if (!$topCustomers): ?>
                <p class="text-muted mb-0">No billed customers yet.</p>
            <?php endif; ?>
            <?php foreach ($topCustomers as $c): ?>
                <?php $pct = $maxCust > 0 ? round(((float) $c['sales'] / $maxCust) * 100) : 0; ?>
                <div class="stack-row">
                    <div class="d-flex justify-content-between">
                        <strong><?= e($c['customer_name']) ?></strong>
                        <span><?= e(money_label($c['sales'])) ?></span>
                    </div>
                    <div class="stack-track">
                        <div class="stack-fill" style="width:<?= (int) $pct ?>%"></div>
                    </div>
                    <div class="text-muted small"><?= (int) $c['invoices'] ?> invoices</div>
                </div>
            <?php endforeach; ?>
            <h2 class="section-title mt-4">Payment mix</h2>
            <div class="chart-wrap chart-wrap-xs"><canvas id="payChart"></canvas></div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card-soft p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="section-title mb-0">Recent invoices</h2>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('billing.php')) ?>">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="5" class="text-muted">No invoices yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $inv): ?>
                        <tr>
                            <td><a href="<?= e(base_url('invoice_view.php?id=' . (int) $inv['id'])) ?>"><?= e($inv['doc_no']) ?></a></td>
                            <td><?= e($inv['doc_date']) ?></td>
                            <td><?= e($inv['customer_name']) ?></td>
                            <td class="text-end"><?= e(money($inv['net_amount'])) ?></td>
                            <td><span class="badge-st <?= e(status_class($inv['status'])) ?>"><?= e(status_label($inv['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script id="dash-data" type="application/json"><?= json_encode($chart, JSON_UNESCAPED_UNICODE) ?></script>
<?php
layout_end('assets/js/dashboard.js');
