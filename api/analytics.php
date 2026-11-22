<?php
// api/analytics.php - Real Database Study Analytics API
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
$timeframe = $_GET['timeframe'] ?? 'weekly';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$now = new DateTime();

// Determine date boundary based on timeframe
if ($timeframe === 'daily') {
    $periodStart = (clone $now)->setTime(0, 0, 0);
    $periodEnd = (clone $now)->setTime(23, 59, 59);
} elseif ($timeframe === 'weekly') {
    // Current week starting Monday
    $periodStart = (clone $now)->modify('this week monday')->setTime(0, 0, 0);
    $periodEnd = (clone $periodStart)->modify('+6 days')->setTime(23, 59, 59);
} elseif ($timeframe === 'monthly') {
    $periodStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
    $periodEnd = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
} elseif ($timeframe === 'yearly') {
    $periodStart = (clone $now)->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
    $periodEnd = (clone $now)->setDate((int)$now->format('Y'), 12, 31)->setTime(23, 59, 59);
} elseif ($timeframe === 'custom' && $startDate && $endDate) {
    $periodStart = new DateTime($startDate . ' 00:00:00');
    $periodEnd = new DateTime($endDate . ' 23:59:59');
} else {
    // Default to weekly
    $timeframe = 'weekly';
    $periodStart = (clone $now)->modify('this week monday')->setTime(0, 0, 0);
    $periodEnd = (clone $periodStart)->modify('+6 days')->setTime(23, 59, 59);
}

$startStr = $periodStart->format('Y-m-d H:i:s');
$endStr = $periodEnd->format('Y-m-d H:i:s');

try {
    // 1. Summary Metrics for the selected timeframe
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(duration_seconds), 0) AS total_seconds,
            COUNT(*) AS completed_sessions,
            COALESCE(MAX(duration_seconds), 0) AS longest_session_seconds
        FROM study_sessions
        WHERE user_id = ? 
          AND status = 'completed' 
          AND session_type != 'break'
          AND started_at BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startStr, $endStr]);
    $summary = $stmt->fetch();

    $totalSeconds = (int)$summary['total_seconds'];
    $completedSessions = (int)$summary['completed_sessions'];
    $longestSessionSeconds = (int)$summary['longest_session_seconds'];
    $avgSeconds = $completedSessions > 0 ? (int)round($totalSeconds / $completedSessions) : 0;

    $userSettings = getUserSettings($userId);
    $dailyGoalMin = (int)($userSettings['daily_goal_minutes'] ?? 180);

    // 2. Trend Chart Data
    $trendLabels = [];
    $trendValues = []; // in hours
    $activeBarIndex = -1;

    if ($timeframe === 'weekly') {
        $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dailyTotals = array_fill(0, 7, 0);
        $currentDayIndex = (int)$now->format('N') - 1; // 0 for Monday, 6 for Sunday

        $trendStmt = $pdo->prepare("
            SELECT 
                DAYOFWEEK(started_at) AS dow, 
                SUM(duration_seconds) AS day_seconds
            FROM study_sessions
            WHERE user_id = ? 
              AND status = 'completed' 
              AND session_type != 'break'
              AND started_at BETWEEN ? AND ?
            GROUP BY DAYOFWEEK(started_at)
        ");
        $trendStmt->execute([$userId, $startStr, $endStr]);
        $rows = $trendStmt->fetchAll();

        foreach ($rows as $r) {
            // MySQL DAYOFWEEK: 1 = Sunday, 2 = Monday, ... 7 = Saturday
            $dow = (int)$r['dow'];
            $idx = ($dow === 1) ? 6 : ($dow - 2);
            $dailyTotals[$idx] = round((int)$r['day_seconds'] / 3600, 1);
        }

        $trendLabels = $daysOfWeek;
        $trendValues = $dailyTotals;
        $activeBarIndex = $currentDayIndex;
    } elseif ($timeframe === 'daily') {
        // 24 hours
        $trendLabels = ['12a','2a','4a','6a','8a','10a','12p','2p','4p','6p','8p','10p'];
        $hourlyTotals = array_fill(0, 12, 0.0);
        
        $trendStmt = $pdo->prepare("
            SELECT HOUR(started_at) AS hr, SUM(duration_seconds) AS hr_seconds
            FROM study_sessions
            WHERE user_id = ? 
              AND status = 'completed' 
              AND session_type != 'break'
              AND started_at BETWEEN ? AND ?
            GROUP BY HOUR(started_at)
        ");
        $trendStmt->execute([$userId, $startStr, $endStr]);
        foreach ($trendStmt->fetchAll() as $r) {
            $h = (int)$r['hr'];
            $bucket = (int)floor($h / 2);
            if (isset($hourlyTotals[$bucket])) {
                $hourlyTotals[$bucket] += round((int)$r['hr_seconds'] / 3600, 1);
            }
        }
        $trendValues = $hourlyTotals;
        $activeBarIndex = (int)floor((int)$now->format('G') / 2);
    } elseif ($timeframe === 'monthly') {
        // Weeks of month or 4 buckets
        $trendLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        $trendValues = [0.0, 0.0, 0.0, 0.0];
        $trendStmt = $pdo->prepare("
            SELECT DAY(started_at) AS dom, SUM(duration_seconds) AS dom_seconds
            FROM study_sessions
            WHERE user_id = ? 
              AND status = 'completed' 
              AND session_type != 'break'
              AND started_at BETWEEN ? AND ?
            GROUP BY DAY(started_at)
        ");
        $trendStmt->execute([$userId, $startStr, $endStr]);
        foreach ($trendStmt->fetchAll() as $r) {
            $dom = (int)$r['dom'];
            $idx = min(3, (int)floor(($dom - 1) / 7));
            $trendValues[$idx] += round((int)$r['dom_seconds'] / 3600, 1);
        }
        $activeBarIndex = min(3, (int)floor(((int)$now->format('j') - 1) / 7));
    } elseif ($timeframe === 'yearly') {
        $trendLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $trendValues = array_fill(0, 12, 0.0);
        $trendStmt = $pdo->prepare("
            SELECT MONTH(started_at) AS m, SUM(duration_seconds) AS m_seconds
            FROM study_sessions
            WHERE user_id = ? 
              AND status = 'completed' 
              AND session_type != 'break'
              AND started_at BETWEEN ? AND ?
            GROUP BY MONTH(started_at)
        ");
        $trendStmt->execute([$userId, $startStr, $endStr]);
        foreach ($trendStmt->fetchAll() as $r) {
            $m = (int)$r['m'] - 1;
            if (isset($trendValues[$m])) {
                $trendValues[$m] = round((int)$r['m_seconds'] / 3600, 1);
            }
        }
        $activeBarIndex = (int)$now->format('n') - 1;
    } else {
        // Custom
        $trendLabels = ['Period'];
        $trendValues = [round($totalSeconds / 3600, 1)];
        $activeBarIndex = 0;
    }

    // 3. Subject Breakdown
    $subStmt = $pdo->prepare("
        SELECT 
            COALESCE(s.subject_name, 'Unassigned') AS subject_name,
            COALESCE(s.colour, '#9ca3af') AS colour,
            SUM(ss.duration_seconds) AS duration_seconds,
            COUNT(*) AS sessions_count
        FROM study_sessions ss
        LEFT JOIN subjects s ON ss.subject_id = s.subject_id
        WHERE ss.user_id = ? 
          AND ss.status = 'completed' 
          AND ss.session_type != 'break'
          AND ss.started_at BETWEEN ? AND ?
        GROUP BY s.subject_id, s.subject_name, s.colour
        ORDER BY duration_seconds DESC
    ");
    $subStmt->execute([$userId, $startStr, $endStr]);
    $subjectBreakdown = $subStmt->fetchAll();

    foreach ($subjectBreakdown as &$sub) {
        $subSec = (int)$sub['duration_seconds'];
        $sub['hours'] = round($subSec / 3600, 1);
        $sub['percentage'] = $totalSeconds > 0 ? round(($subSec / $totalSeconds) * 100) : 0;
    }
    unset($sub);

    // 4. Study Activity (Last 28 Days Heatmap)
    $activityStmt = $pdo->prepare("
        SELECT DATE(started_at) AS study_date, SUM(duration_seconds) AS sec
        FROM study_sessions
        WHERE user_id = ? 
          AND status = 'completed' 
          AND session_type != 'break'
          AND started_at >= DATE_SUB(CURDATE(), INTERVAL 27 DAY)
        GROUP BY DATE(started_at)
    ");
    $activityStmt->execute([$userId]);
    $activityMap = [];
    foreach ($activityStmt->fetchAll() as $act) {
        $activityMap[$act['study_date']] = (int)$act['sec'];
    }

    $activityGrid = [];
    for ($i = 27; $i >= 0; $i--) {
        $d = (new DateTime())->modify("-{$i} days")->format('Y-m-d');
        $sec = $activityMap[$d] ?? 0;
        $level = 0;
        if ($sec > 0 && $sec < 1800) $level = 1;
        elseif ($sec >= 1800 && $sec < 5400) $level = 2;
        elseif ($sec >= 5400 && $sec < 10800) $level = 3;
        elseif ($sec >= 10800) $level = 4;

        $activityGrid[] = [
            'date' => $d,
            'seconds' => $sec,
            'hours' => round($sec / 3600, 1),
            'level' => $level
        ];
    }

    // 5. Highlights Data
    // Most studied subject
    $mostStudiedSubject = !empty($subjectBreakdown) ? $subjectBreakdown[0]['subject_name'] : 'None';
    $mostStudiedHours = !empty($subjectBreakdown) ? $subjectBreakdown[0]['hours'] : 0;

    // Pomodoros completed this month
    $pomStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM study_sessions 
        WHERE user_id = ? 
          AND status = 'completed' 
          AND session_type = 'pomodoro'
          AND MONTH(started_at) = MONTH(CURDATE())
          AND YEAR(started_at) = YEAR(CURDATE())
    ");
    $pomStmt->execute([$userId]);
    $pomodorosThisMonth = (int)$pomStmt->fetchColumn();

    // Current and longest streak
    $currentStreak = calculateUserStreak($pdo, $userId);
    $longestStreak = $currentStreak;

    // Most productive day of week
    $dowStmt = $pdo->prepare("
        SELECT 
            DAYNAME(started_at) AS day_name, 
            AVG(duration_seconds) AS avg_sec,
            COUNT(*) AS cnt
        FROM study_sessions
        WHERE user_id = ? AND status = 'completed' AND session_type != 'break'
        GROUP BY DAYOFWEEK(started_at), DAYNAME(started_at)
        ORDER BY avg_sec DESC
        LIMIT 1
    ");
    $dowStmt->execute([$userId]);
    $bestDayRow = $dowStmt->fetch();
    $mostProductiveDay = $bestDayRow ? $bestDayRow['day_name'] : 'None yet';
    $mostProductiveAvg = $bestDayRow ? round((int)$bestDayRow['avg_sec'] / 3600, 1) : 0;

    // Sessions this month
    $monthSessionsStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM study_sessions 
        WHERE user_id = ? 
          AND status = 'completed' 
          AND session_type != 'break'
          AND MONTH(started_at) = MONTH(CURDATE())
          AND YEAR(started_at) = YEAR(CURDATE())
    ");
    $monthSessionsStmt->execute([$userId]);
    $sessionsThisMonth = (int)$monthSessionsStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'timeframe' => $timeframe,
        'summary' => [
            'total_seconds' => $totalSeconds,
            'total_hours' => round($totalSeconds / 3600, 1),
            'completed_sessions' => $completedSessions,
            'average_seconds' => $avgSeconds,
            'average_hours' => round($avgSeconds / 3600, 1),
            'longest_session_seconds' => $longestSessionSeconds,
            'longest_session_hours' => round($longestSessionSeconds / 3600, 1),
            'current_streak_days' => $currentStreak,
            'daily_goal_minutes' => $dailyGoalMin
        ],
        'chart' => [
            'labels' => $trendLabels,
            'values' => $trendValues,
            'active_index' => $activeBarIndex
        ],
        'by_subject' => $subjectBreakdown,
        'activity_grid' => $activityGrid,
        'highlights' => [
            'most_studied_subject' => $mostStudiedSubject,
            'most_studied_hours' => $mostStudiedHours,
            'pomodoros_completed' => $pomodorosThisMonth,
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'most_productive_day' => $mostProductiveDay,
            'most_productive_avg' => $mostProductiveAvg,
            'sessions_this_month' => $sessionsThisMonth
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("api/analytics.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to calculate analytics.']);
}
