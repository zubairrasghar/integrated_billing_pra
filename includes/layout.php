<?php

require_once __DIR__ . '/helpers.php';

function layout_start(string $title, string $nav = 'dashboard', array $user = []): void
{
    $company = company_settings();
    $app = app_config();
    $pageTitle = $title . ' · ' . $app['short_name'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
    <?php if ($nav === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body>
<aside class="app-sidebar">
    <div class="brand">
        <div class="brand-mark">PRA</div>
        <div>
            <div class="brand-kicker"><?= e($company['name'] ?? $app['name']) ?></div>
            <div class="brand-title"><?= e($app['short_name']) ?></div>
        </div>
    </div>
    <nav class="side-nav">
        <a class="<?= $nav === 'dashboard' ? 'active' : '' ?>" href="<?= e(base_url('dashboard.php')) ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a class="<?= $nav === 'billing' ? 'active' : '' ?>" href="<?= e(base_url('billing.php')) ?>">
            <i class="bi bi-receipt"></i> Billing
        </a>
        <a class="<?= $nav === 'create' ? 'active' : '' ?>" href="<?= e(base_url('invoice_create.php')) ?>">
            <i class="bi bi-plus-square"></i> Create Invoice
        </a>
    </nav>
    <div class="side-foot">
        PRA sandbox ready
    </div>
</aside>
<div class="app-main">
    <nav class="mobile-nav d-md-none">
        <a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a>
        <a href="<?= e(base_url('billing.php')) ?>">Billing</a>
        <a href="<?= e(base_url('invoice_create.php')) ?>">Create</a>
    </nav>
    <header class="app-top">
        <div>
            <div class="top-kicker">Operations</div>
            <h1><?= e($title) ?></h1>
        </div>
        <div class="top-user">
            <div class="avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?></div>
            <div>
                <div class="user-name"><?= e($user['full_name'] ?? '') ?></div>
                <div class="user-role"><?= e($user['username'] ?? '') ?></div>
            </div>
            <a class="btn btn-ghost" href="<?= e(base_url('logout.php')) ?>">Logout</a>
        </div>
    </header>
    <main class="app-content">
    <?php
    foreach (flash() as $item) {
        $type = $item['type'] ?? 'success';
        echo '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
            . e($item['message'] ?? '')
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

function layout_end(string $extraJs = ''): void
{
    ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
<?php if ($extraJs !== ''): ?>
<script src="<?= e(base_url($extraJs)) ?>"></script>
<?php endif; ?>
</body>
</html>
    <?php
}
