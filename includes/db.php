<?php
// includes/db.php - Database connection using PDO
declare(strict_types=1);

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
}

// Simple .env loader if .env file exists
(function() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
})();

function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'studymaze';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // If database doesn't exist yet, connect to MySQL server and create it
            if ($e->getCode() === 1049) {
                try {
                    $initDsn = "mysql:host={$host};port={$port};charset={$charset}";
                    $rootPdo = new PDO($initDsn, $username, $password, $options);
                    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO($dsn, $username, $password, $options);
                } catch (PDOException $createEx) {
                    handleDbError($createEx);
                }
            } else {
                handleDbError($e);
            }
        }
    }
    
    return $pdo;
}

function handleDbError(PDOException $e): void {
    http_response_code(500);
    error_log("Database connection error: " . $e->getMessage());

    $isApiRequest = (
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)
    );

    if ($isApiRequest) {
        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode([
            'success' => false,
            'error' => 'Database connection failed. Please ensure MySQL is running in XAMPP.'
        ]));
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        die('<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Database Error — StudyMaze</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #fafafa; color: #1f2937; }
    .box { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 40px; max-width: 460px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    .icon { width: 48px; height: 48px; margin: 0 auto 16px; color: #ea580c; }
    h2 { margin: 0 0 12px; font-size: 1.35rem; color: #111827; }
    p { margin: 0 0 20px; color: #6b7280; font-size: 0.95rem; line-height: 1.5; }
    .btn { display: inline-block; background: #ea580c; color: #fff; padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
  </style>
</head>
<body>
  <div class="box">
    <div class="icon">⚠️</div>
    <h2>Database Unavailable</h2>
    <p>StudyMaze cannot connect to the database. Please ensure Apache and MySQL are running in your XAMPP Control Panel, then refresh this page.</p>
    <a href="javascript:location.reload()" class="btn">Retry Connection</a>
  </div>
</body>
</html>');
    }
}
