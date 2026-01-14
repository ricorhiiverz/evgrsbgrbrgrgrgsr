<?php
// File: config/koneksi.php

// 1. FUNGSI LOAD ENV
// -------------------------------------------------
function getEnvVars($path) {
    if (!file_exists($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $vars[trim($name)] = trim($value);
            
            if (!getenv(trim($name))) {
                putenv(sprintf('%s=%s', trim($name), trim($value)));
                $_ENV[trim($name)] = trim($value);
            }
        }
    }
    return $vars;
}

// Load Environment Variables
$envPath = __DIR__ . '/../v1/.env';
$env = getEnvVars($envPath);

// 2. KONEKSI DATABASE
// -------------------------------------------------
$host = isset($env['DB_HOST']) ? $env['DB_HOST'] : 'localhost';
$user = isset($env['DB_USER']) ? $env['DB_USER'] : 'root';
$pass = isset($env['DB_PASS']) ? $env['DB_PASS'] : '';
$db   = isset($env['DB_NAME']) ? $env['DB_NAME'] : 'voucherku';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    date_default_timezone_set('Asia/Jakarta');
    
} catch(PDOException $e) {
    // SECURITY: Jangan tampilkan detail error SQL ke publik
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        "status" => false, 
        "message" => "Terjadi gangguan koneksi server."
    ]);
    exit;
}
?>