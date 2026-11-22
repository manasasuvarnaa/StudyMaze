<?php
// api/quotes.php - Motivational Quotes API
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = getDbConnection();
    
    // Fetch random active quote
    $stmt = $pdo->query("SELECT quote_id, quote_text, author, category FROM quotes WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
    $quote = $stmt->fetch();

    if (!$quote) {
        $quote = [
            'quote_id' => 0,
            'quote_text' => 'An investment in knowledge pays the best interest.',
            'author' => 'Benjamin Franklin',
            'category' => 'study'
        ];
    }

    echo json_encode([
        'success' => true,
        'quote' => $quote
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch quote.'
    ]);
}
