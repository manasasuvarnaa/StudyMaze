<?php
// api/achievements.php - User Achievements API
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$userId = getCurrentUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDbConnection();

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.achievement_id,
            a.achievement_key,
            a.title,
            a.description,
            a.icon,
            a.criteria_type,
            a.criteria_value,
            ua.unlocked_at,
            (ua.unlocked_at IS NOT NULL) AS is_unlocked
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.achievement_id = ua.achievement_id AND ua.user_id = ?
        ORDER BY is_unlocked DESC, a.criteria_value ASC
    ");
    $stmt->execute([$userId]);
    $achievements = $stmt->fetchAll();

    echo json_encode(['success' => true, 'achievements' => $achievements]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load achievements.']);
}
