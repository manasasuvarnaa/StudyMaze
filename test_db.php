<?php
// test_db.php - Database Connection Health Check for Development
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDbConnection();
    echo "✓ Database Connection: SUCCESSFUL\n";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Detected Tables (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        echo "   - $t\n";
    }

    // Check users columns
    $stmt = $pdo->query("DESCRIBE users");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Users Columns: " . implode(', ', $cols) . "\n";

    echo "\nAll database health checks PASSED.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "✗ Database Connection FAILED: " . $e->getMessage() . "\n";
}
