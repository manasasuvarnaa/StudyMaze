<?php
// api/settings.php - User Preferences and Settings API
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$userId = getCurrentUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?? $_POST;

try {
    if ($method === 'GET') {
        $settings = getUserSettings($userId);
        echo json_encode(['success' => true, 'settings' => $settings]);
        exit;
    }

    if ($method === 'POST') {
        validateCsrfOrDie();
        $action = $inputData['action'] ?? '';

        // 1. Update Profile (Name & Email)
        if ($action === 'update_profile') {
            $name = trim($inputData['name'] ?? '');
            $email = trim($inputData['email'] ?? '');

            if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Please provide a valid name and email address.']);
                exit;
            }

            // Check if email already in use by another user
            $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $chk->execute([$email, $userId]);
            if ($chk->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'This email is already in use by another account.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$name, $email, $userId]);

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
            exit;
        }

        // 2. Change Password
        if ($action === 'change_password') {
            $currentPassword = $inputData['current_password'] ?? '';
            $newPassword = $inputData['new_password'] ?? '';
            $confirmPassword = $inputData['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'All password fields are required.']);
                exit;
            }

            if (strlen($newPassword) < 8) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters long.']);
                exit;
            }

            if ($newPassword !== $confirmPassword) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
                exit;
            }

            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($currentPassword, $hash)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
                exit;
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $upd->execute([$newHash, $userId]);

            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
            exit;
        }

        // 3. Update Theme
        if ($action === 'update_theme') {
            $themeName = $inputData['theme_name'] ?? 'default';
            $validThemes = ['default', 'ghibli', 'crimson', 'forest', 'midnight'];
            if (!in_array($themeName, $validThemes)) {
                $themeName = 'default';
            }

            $accentMap = [
                'default' => '#ea580c',
                'ghibli' => '#4a7c59',
                'crimson' => '#dc2626',
                'forest' => '#15803d',
                'midnight' => '#7c3aed'
            ];
            $accent = $accentMap[$themeName] ?? '#ea580c';

            $stmt = $pdo->prepare("UPDATE user_settings SET theme_name = ?, accent_colour = ? WHERE user_id = ?");
            $stmt->execute([$themeName, $accent, $userId]);

            echo json_encode([
                'success' => true,
                'theme_name' => $themeName,
                'accent_colour' => $accent,
                'message' => 'Theme updated successfully.'
            ]);
            exit;
        }

        // 4. Update Appearance (Light / Dark)
        if ($action === 'update_appearance') {
            $appearance = ($inputData['appearance'] ?? 'light') === 'dark' ? 'dark' : 'light';
            $stmt = $pdo->prepare("UPDATE user_settings SET appearance = ? WHERE user_id = ?");
            $stmt->execute([$appearance, $userId]);

            echo json_encode(['success' => true, 'appearance' => $appearance]);
            exit;
        }

        // 5. Update Timer & Pomodoro Durations
        if ($action === 'update_timer_settings') {
            $focus       = max(1, min(180,  (int)($inputData['focus_duration_minutes'] ?? 25)));
            $shortBreak  = max(1, min(60,   (int)($inputData['short_break_minutes']    ?? 5)));
            $longBreak   = max(1, min(120,  (int)($inputData['long_break_minutes']     ?? 15)));
            $dailyGoal   = max(15, min(1440,(int)($inputData['daily_goal_minutes']     ?? 180)));
            $autoStart   = !empty($inputData['auto_start_breaks'])   ? 1 : 0;
            $notif       = !empty($inputData['timer_notifications'])  ? 1 : 0;
            $sounds      = !empty($inputData['sound_enabled'])        ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE user_settings SET
                focus_duration_minutes = ?,
                short_break_minutes    = ?,
                long_break_minutes     = ?,
                daily_goal_minutes     = ?,
                auto_start_breaks      = ?,
                timer_notifications    = ?,
                sound_enabled          = ?
                WHERE user_id          = ?");
            $stmt->execute([$focus, $shortBreak, $longBreak, $dailyGoal, $autoStart, $notif, $sounds, $userId]);

            echo json_encode([
                'success'       => true,
                'message'       => 'Timer settings saved.',
                'sound_enabled' => (bool)$sounds
            ]);
            exit;
        }

        // 6. Update Sound Preferences
        if ($action === 'update_sound_preferences') {
            $sound   = trim($inputData['selected_sound'] ?? 'rain');
            $volume  = max(0, min(100, (int)($inputData['sound_volume'] ?? 60)));
            $enabled = !empty($inputData['sound_enabled']) ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE user_settings SET selected_sound = ?, sound_volume = ?, sound_enabled = ? WHERE user_id = ?");
            $stmt->execute([$sound, $volume, $enabled, $userId]);

            echo json_encode(['success' => true, 'message' => 'Sound preferences saved.']);
            exit;
        }

        // 7. Delete Account
        if ($action === 'delete_account') {
            $password = $inputData['password'] ?? '';
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($password, $hash)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Incorrect password. Account deletion aborted.']);
                exit;
            }

            // Delete user cascades to subjects, tasks, sessions, settings, achievements
            $del = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $del->execute([$userId]);

            logoutUser();
            echo json_encode(['success' => true, 'message' => 'Account deleted successfully.']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

} catch (Exception $e) {
    http_response_code(500);
    error_log("api/settings.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to process settings request.']);
}
