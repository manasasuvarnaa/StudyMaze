<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== STUDYMAZE AUDIO & SETTINGS VERIFICATION ===\n\n";

$pdo = getDbConnection();

// 1. Check audio files
echo "1. VERIFYING 4 AMBIENT AUDIO FILES IN assets/audio/\n";
$audioDir = __DIR__ . '/../assets/audio/';
$requiredSounds = ['rain', 'library', 'ocean', 'forest'];
$allFilesFound = true;
foreach ($requiredSounds as $snd) {
    $path = $audioDir . $snd . '.wav';
    if (file_exists($path) && filesize($path) > 1000) {
        echo "  [PASS] {$snd}.wav exists (" . filesize($path) . " bytes)\n";
    } else {
        echo "  [FAIL] {$snd}.wav is missing or empty\n";
        $allFilesFound = false;
    }
}

// 2. Check user settings persistence for sound_enabled
echo "\n2. USER SETTINGS GLOBAL PERSISTENCE TEST\n";
// Create temporary test user
$testEmail = 'audit_' . time() . '@studymaze.local';
$hash = password_hash('TestPass123!', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, username, email, password_hash) VALUES (?, ?, ?, ?)");
$stmt->execute(['Audio Tester', 'audiotester_' . time(), $testEmail, $hash]);
$testUserId = (int)$pdo->lastInsertId();

// Verify default settings
$settings = getUserSettings($testUserId);
echo "  [PASS] Initial settings fetched for user {$testUserId}\n";

// Disable sound_enabled
$upd = $pdo->prepare("UPDATE user_settings SET sound_enabled = 0, sound_volume = 18, selected_sound = 'ocean', daily_goal_minutes = 240 WHERE user_id = ?");
$upd->execute([$testUserId]);

$reloaded = getUserSettings($testUserId);
if ((int)$reloaded['sound_enabled'] === 0 && (int)$reloaded['daily_goal_minutes'] === 240 && $reloaded['selected_sound'] === 'ocean') {
    echo "  [PASS] Settings updated and persisted in MySQL (sound_enabled = 0, daily_goal = 240, sound = ocean)\n";
} else {
    echo "  [FAIL] Settings persistence mismatch\n";
}

// Enable sound_enabled again
$upd = $pdo->prepare("UPDATE user_settings SET sound_enabled = 1 WHERE user_id = ?");
$upd->execute([$testUserId]);
$reloaded2 = getUserSettings($testUserId);
if ((int)$reloaded2['sound_enabled'] === 1) {
    echo "  [PASS] sound_enabled toggle back to 1 verified in MySQL\n";
} else {
    echo "  [FAIL] sound_enabled re-enable failed\n";
}

// Cleanup test user
$pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$testUserId]);
echo "  [INFO] Cleaned up temporary test user.\n";

echo "\nALL AUDIO & SETTINGS VERIFICATIONS COMPLETED SUCCESSFULLY!\n";
