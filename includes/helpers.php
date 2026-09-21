<?php

require_once __DIR__ . '/db.php';

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config/app.php';
    }
    return $cfg;
}

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim(dirname($script), '/');
    if ($base === '\\' || $base === '.') {
        $base = '';
    }
    $path = ltrim($path, '/');
    return $base . ($path !== '' ? '/' . $path : '');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    if (!preg_match('#^https?://#i', $path)) {
        $path = base_url(ltrim($path, '/'));
    }
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        exit('Invalid security token. Please refresh and try again.');
    }
}

function flash(?string $key = null, ?string $message = null, string $type = 'success')
{
    if ($key !== null && $message !== null) {
        $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
        return null;
    }
    if ($key !== null) {
        $item = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $item;
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function money($amount): string
{
    return number_format((float) $amount, 2);
}

function money_label($amount): string
{
    return 'Rs ' . money($amount);
}

function status_class(string $status): string
{
    $map = [
        'PREPARED' => 'st-prepared',
        'SUBMITTED' => 'st-submitted',
        'PRA_APPROVED' => 'st-approved',
        'PRA_REJECTED' => 'st-rejected',
        'PENDING' => 'st-pending',
        'CANCELED' => 'st-canceled',
    ];
    return $map[$status] ?? 'st-prepared';
}

function status_label(string $status): string
{
    return str_replace('_', ' ', $status);
}

function company_settings(): array
{
    $row = db()->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1')->fetch();
    return $row ?: [
        'name' => app_config()['name'],
        'address' => '',
        'city' => '',
        'ntn' => '',
        'strn' => '',
        'phone' => '',
        'email' => '',
        'logo_path' => '',
    ];
}

function pra_config(): array
{
    $row = db()->query('SELECT * FROM pra_config ORDER BY id ASC LIMIT 1')->fetch();
    return $row ?: [
        'api_base_url' => '',
        'client_id' => '',
        'client_secret' => '',
        'sandbox_mode' => 1,
        'seller_ntn' => '',
    ];
}

function next_doc_no(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT next_val FROM doc_sequences WHERE name = 'invoice' FOR UPDATE");
    $n = (int) $stmt->fetchColumn();
    if ($n < 1) {
        $n = 1;
    }
    $pdo->prepare("UPDATE doc_sequences SET next_val = ? WHERE name = 'invoice'")->execute([$n + 1]);
    return 'INV-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
}

function peek_doc_no(): string
{
    $n = (int) db()->query("SELECT next_val FROM doc_sequences WHERE name = 'invoice'")->fetchColumn();
    if ($n < 1) {
        $n = 1;
    }
    return 'INV-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function invoice_can_retry(string $status): bool
{
    return in_array($status, ['PENDING', 'PRA_REJECTED', 'PREPARED', 'SUBMITTED'], true);
}

function round_money($n): float
{
    return round((float) $n, 2);
}
