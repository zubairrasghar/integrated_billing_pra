<?php

require_once __DIR__ . '/includes/auth.php';

require_login();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$token = (string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    json_response(['ok' => false, 'error' => 'Invalid security token.'], 400);
}

$name = trim((string) ($data['name'] ?? ''));
$ntn = trim((string) ($data['ntn_cnic'] ?? ''));
$type = (string) ($data['customer_type'] ?? 'Unregistered');
$phone = trim((string) ($data['phone'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));

if ($name === '') {
    json_response(['ok' => false, 'error' => 'Customer name is required.'], 422);
}
if (!in_array($type, ['Registered', 'Unregistered', 'Individual'], true)) {
    $type = 'Unregistered';
}

$stmt = db()->prepare(
    'INSERT INTO customers (name, ntn_cnic, customer_type, phone, address, is_active) VALUES (?, ?, ?, ?, ?, 1)'
);
$stmt->execute([$name, $ntn, $type, $phone, $address]);
$id = (int) db()->lastInsertId();

json_response([
    'ok' => true,
    'customer' => [
        'id' => $id,
        'name' => $name,
        'ntn_cnic' => $ntn,
        'customer_type' => $type,
        'phone' => $phone,
        'address' => $address,
    ],
]);
