<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require dirname(__DIR__) . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database error</title>';
        echo '<style>body{font-family:Segoe UI,sans-serif;background:#f4f6f9;padding:48px;color:#1e293b}';
        echo '.box{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px}';
        echo 'h1{font-size:18px;margin:0 0 8px}p{margin:0;color:#64748b;line-height:1.5}</style></head><body>';
        echo '<div class="box"><h1>Cannot connect to MySQL</h1>';
        echo '<p>Import <code>sql/schema.sql</code> in phpMyAdmin, then confirm <code>config/database.php</code> matches your local MySQL user and password.</p></div></body></html>';
        exit;
    }

    return $pdo;
}
