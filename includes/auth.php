<?php
// includes/auth.php - Session, Authentication & User Helpers
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function getCurrentUserId(): ?int {
    return isLoggedIn() ? (int)$_SESSION['user_id'] : null;
}

function getCurrentUser(): ?array {
    $userId = getCurrentUserId();
    if (!$userId) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['user_id'] === $userId) {
        return $cachedUser;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT user_id, name, username, email, email_verified_at, created_at FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $cachedUser = $user;
            return $user;
        }
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
    }

    return null;
}

function requireLogin(): void {
    if (!isLoggedIn() || !getCurrentUser()) {
        header("Location: login.php");
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn() && getCurrentUser()) {
        header("Location: dashboard.php");
        exit;
    }
}

function loginUser(int $userId): void {
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in_time'] = time();
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

function getUserSettings(int $userId): array {
    $defaultSettings = [
        'theme_name' => 'default',
        'appearance' => 'light',
        'accent_colour' => '#ea580c',
        'daily_goal_minutes' => 180,
        'focus_duration_minutes' => 25,
        'short_break_minutes' => 5,
        'long_break_minutes' => 15,
        'pomodoros_before_long_break' => 4,
        'auto_start_breaks' => 1,
        'sound_enabled' => 1,
        'selected_sound' => 'rain',
        'sound_volume' => 70,
        'timer_notifications' => 1
    ];

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) {
            return array_merge($defaultSettings, $row);
        } else {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_settings (user_id) VALUES (?)");
            $ins->execute([$userId]);
            return $defaultSettings;
        }
    } catch (Exception $e) {
        error_log("Error in getUserSettings: " . $e->getMessage());
        return $defaultSettings;
    }
}

function calculateUserStreak(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(started_at) as study_date
        FROM study_sessions
        WHERE user_id = ? AND status = 'completed' AND session_type != 'break'
        ORDER BY study_date DESC
    ");
    $stmt->execute([$userId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) {
        return 0;
    }

    $streak = 0;
    $today = new DateTime('today');
    $yesterday = (new DateTime('today'))->modify('-1 day');

    $firstDate = new DateTime($dates[0]);
    if ($firstDate != $today && $firstDate != $yesterday) {
        return 0;
    }

    $currentCheck = $firstDate;
    foreach ($dates as $dateStr) {
        $d = new DateTime($dateStr);
        if ($d == $currentCheck) {
            $streak++;
            $currentCheck = (clone $currentCheck)->modify('-1 day');
        } else {
            break;
        }
    }

    return $streak;
}

function checkAndUnlockAchievements(PDO $pdo, int $userId): array {
    $newlyUnlocked = [];

    // 1. Total completed focus sessions
    $sesStmt = $pdo->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id = ? AND status = 'completed' AND session_type != 'break'");
    $sesStmt->execute([$userId]);
    $totalSessions = (int)$sesStmt->fetchColumn();

    // 2. Total study minutes
    $minStmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0) FROM study_sessions WHERE user_id = ? AND status = 'completed' AND session_type != 'break'");
    $minStmt->execute([$userId]);
    $totalMinutes = (int)round((int)$minStmt->fetchColumn() / 60);

    // 3. Streak days
    $streakDays = calculateUserStreak($pdo, $userId);

    // Fetch locked achievements
    $achStmt = $pdo->prepare("
        SELECT a.achievement_id, a.achievement_key, a.title, a.criteria_type, a.criteria_value
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.achievement_id = ua.achievement_id AND ua.user_id = ?
        WHERE ua.achievement_id IS NULL
    ");
    $achStmt->execute([$userId]);
    $lockedAchievements = $achStmt->fetchAll();

    $ins = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())");

    foreach ($lockedAchievements as $ach) {
        $qualifies = false;
        if ($ach['criteria_type'] === 'sessions' && $totalSessions >= (int)$ach['criteria_value']) {
            $qualifies = true;
        } elseif ($ach['criteria_type'] === 'study_minutes' && $totalMinutes >= (int)$ach['criteria_value']) {
            $qualifies = true;
        } elseif ($ach['criteria_type'] === 'streak_days' && $streakDays >= (int)$ach['criteria_value']) {
            $qualifies = true;
        }

        if ($qualifies) {
            $ins->execute([$userId, $ach['achievement_id']]);
            $newlyUnlocked[] = $ach['title'];
        }
    }

    return $newlyUnlocked;
}
