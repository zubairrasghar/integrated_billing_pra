<?php

require_once __DIR__ . '/includes/auth.php';

start_session();
if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Enter username and password.';
    } elseif (!attempt_login($username, $password)) {
        $error = 'Invalid username or password.';
    } else {
        redirect('dashboard.php');
    }
}

$app = app_config();
$company = [];
try {
    $company = company_settings();
} catch (Throwable $e) {
    $company = ['name' => $app['name']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e($app['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="login-body">
    <form class="login-card" method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="login-mark">PRA</div>
        <h1><?= e($app['name']) ?></h1>
        <p class="sub"><?= e($company['name'] ?? '') ?> — sign in to continue.</p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" required>
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
        <p class="text-center text-muted mt-3 mb-0" style="font-size:.8rem">Default: admin / admin123</p>
    </form>
</body>
</html>
