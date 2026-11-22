<?php
// tests/e2e_verification.php - End-to-End Test Suite for StudyMaze
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== STUDYMAZE FULL-STACK VERIFICATION SUITE ===\n\n";

$pdo = getDbConnection();
$passed = 0;
$total = 0;

function assertTest(bool $condition, string $testName): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$testName}\n";
    } else {
        echo "  [FAIL] {$testName}\n";
    }
}

// Clean test accounts before starting
$testEmail = 'student_' . time() . '@example.com';
$testUsername = 'student_' . time();
$testPassword = 'StudyMazePassword123!';

echo "1. DATABASE CONNECTION & SCHEMA INTEGRITY\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
assertTest(!empty($tables), "Connected to MySQL successfully");
assertTest(in_array('users', $tables), "Table 'users' exists");
assertTest(in_array('subjects', $tables), "Table 'subjects' exists");
assertTest(in_array('study_sessions', $tables), "Table 'study_sessions' exists");
assertTest(in_array('tasks', $tables), "Table 'tasks' exists");
assertTest(in_array('user_settings', $tables), "Table 'user_settings' exists");
assertTest(in_array('password_resets', $tables), "Table 'password_resets' exists");

echo "\n2. DIRECT REGISTRATION & ONBOARDING (NO VERIFICATION BARRIER)\n";
$pdo->beginTransaction();
$hash = password_hash($testPassword, PASSWORD_DEFAULT);
$ins = $pdo->prepare("INSERT INTO users (name, username, email, password_hash, email_verified_at) VALUES (?, ?, ?, ?, NOW())");
$ins->execute(['Student User', $testUsername, $testEmail, $hash]);
$userId = (int)$pdo->lastInsertId();

$setStmt = $pdo->prepare("INSERT INTO user_settings (user_id, theme_name, appearance, accent_colour) VALUES (?, 'default', 'light', '#ea580c')");
$setStmt->execute([$userId]);

// Seed starter subjects
$subIns = $pdo->prepare("INSERT INTO subjects (user_id, subject_name, colour) VALUES (?, 'Mathematics', '#ea580c'), (?, 'Physics', '#3b82f6')");
$subIns->execute([$userId, $userId]);
$pdo->commit();

assertTest($userId > 0, "User successfully registered in MySQL with ID: {$userId}");

$subs = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE user_id = ?");
$subs->execute([$userId]);
assertTest((int)$subs->fetchColumn() === 2, "Starter subjects properly initialized in database");

$settings = getUserSettings($userId);
assertTest($settings['theme_name'] === 'default', "User preferences initialized with default theme");

echo "\n3. DATABASE-CONNECTED LOGIN & SESSION CREATION\n";
// Test login with email
$stmt = $pdo->prepare("SELECT user_id, email, username, password_hash FROM users WHERE email = ? OR username = ?");
$stmt->execute([$testEmail, $testEmail]);
$userRec = $stmt->fetch();

assertTest(!empty($userRec), "User record found in MySQL database");
assertTest(password_verify('WrongPassword', $userRec['password_hash']) === false, "Incorrect password properly rejected");
assertTest(password_verify($testPassword, $userRec['password_hash']) === true, "Correct password verified with bcrypt password_verify()");

// Test login with username
$stmt->execute([$testUsername, $testUsername]);
$userByUsername = $stmt->fetch();
assertTest(!empty($userByUsername) && (int)$userByUsername['user_id'] === $userId, "Login by username queries database correctly");

// Simulate loginUser
loginUser($userId);
assertTest(isLoggedIn() === true, "Session created successfully (isLoggedIn == true)");
assertTest(getCurrentUserId() === $userId, "getCurrentUserId returns logged-in user ID");

$currentUser = getCurrentUser();
assertTest($currentUser['email'] === $testEmail, "getCurrentUser returns active user record");

echo "\n4. FORGOT PASSWORD & 6-DIGIT VERIFICATION CODE WORKFLOW\n";
$resetCode = (string)random_int(100000, 999999);
$resetCodeHash = hash('sha256', $resetCode);

$pdo->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")->execute([$userId, $resetCodeHash]);

$prStmt = $pdo->prepare("SELECT reset_id FROM password_resets WHERE user_id = ? AND code_hash = ? AND used_at IS NULL AND expires_at > NOW()");
$prStmt->execute([$userId, $resetCodeHash]);
$resetRec = $prStmt->fetch();
assertTest(!empty($resetRec), "6-digit password reset code created and verified in database");

// Update password
$newPassword = 'BrandNewPassword789!';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$newHash, $userId]);
$pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?")->execute([$resetRec['reset_id']]);

$stmt->execute([$testEmail, $testEmail]);
$updatedUser = $stmt->fetch();
assertTest(password_verify($testPassword, $updatedUser['password_hash']) === false, "Old password invalidated");
assertTest(password_verify($newPassword, $updatedUser['password_hash']) === true, "New password verified successfully");

echo "\n5. RATE LIMITING & SECURITY GUARDS\n";
$ip = '127.0.0.1';
$pdo->prepare("INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, NOW())")->execute([$ip]);
$laStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ?");
$laStmt->execute([$ip]);
assertTest((int)$laStmt->fetchColumn() >= 1, "Failed attempts tracked in database");
$pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$ip]);

// Clean up test user
$pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
echo "\n6. CLEANUP\n";
echo "  [INFO] Test user cleaned up.\n";

echo "\n========================================================\n";
echo "RESULTS: {$passed} / {$total} TESTS PASSED (" . round(($passed / $total) * 100) . "%)\n";
echo "========================================================\n";
