<?php
// api/sessions.php - Study Sessions Lifecycle and Recovery API
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
        $action = $_GET['action'] ?? 'active';

        // 1. Get current active session (running or paused)
        if ($action === 'active') {
            $stmt = $pdo->prepare("
                SELECT 
                    ss.session_id, 
                    ss.subject_id, 
                    ss.session_label, 
                    ss.session_type, 
                    ss.status, 
                    ss.started_at, 
                    ss.last_resumed_at, 
                    ss.planned_duration_seconds, 
                    ss.accumulated_seconds,
                    s.subject_name,
                    s.colour AS subject_colour
                FROM study_sessions ss
                LEFT JOIN subjects s ON ss.subject_id = s.subject_id
                WHERE ss.user_id = ? AND ss.status IN ('running', 'paused')
                ORDER BY ss.session_id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $session = $stmt->fetch();

            if (!$session) {
                echo json_encode(['success' => true, 'has_active' => false]);
                exit;
            }

            // Calculate current real elapsed seconds
            $currentElapsed = (int)$session['accumulated_seconds'];
            if ($session['status'] === 'running' && !empty($session['last_resumed_at'])) {
                $lastResumed = strtotime($session['last_resumed_at']);
                $diff = max(0, time() - $lastResumed);
                $currentElapsed += $diff;
            }

            $session['current_elapsed_seconds'] = $currentElapsed;
            echo json_encode(['success' => true, 'has_active' => true, 'session' => $session]);
            exit;
        }

        // 2. Get completed session history
        if ($action === 'history') {
            $subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
            $sessionType = !empty($_GET['session_type']) && in_array($_GET['session_type'], ['focus', 'pomodoro', 'stopwatch', 'break']) ? $_GET['session_type'] : null;
            $startDate = !empty($_GET['start_date']) ? $_GET['start_date'] . ' 00:00:00' : null;
            $endDate = !empty($_GET['end_date']) ? $_GET['end_date'] . ' 23:59:59' : null;

            $sql = "
                SELECT 
                    ss.session_id,
                    ss.subject_id,
                    ss.session_label,
                    ss.session_type,
                    ss.status,
                    ss.started_at,
                    ss.ended_at,
                    ss.duration_seconds,
                    s.subject_name,
                    s.colour AS subject_colour
                FROM study_sessions ss
                LEFT JOIN subjects s ON ss.subject_id = s.subject_id
                WHERE ss.user_id = ? AND ss.status = 'completed'
            ";
            $params = [$userId];

            if ($subjectId) {
                $sql .= " AND ss.subject_id = ?";
                $params[] = $subjectId;
            }
            if ($sessionType) {
                $sql .= " AND ss.session_type = ?";
                $params[] = $sessionType;
            }
            if ($startDate) {
                $sql .= " AND ss.started_at >= ?";
                $params[] = $startDate;
            }
            if ($endDate) {
                $sql .= " AND ss.started_at <= ?";
                $params[] = $endDate;
            }

            $sql .= " ORDER BY ss.started_at DESC LIMIT 200";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll();

            // Total study seconds (excluding breaks)
            $totalSecStmt = $pdo->prepare("
                SELECT COALESCE(SUM(duration_seconds), 0)
                FROM study_sessions
                WHERE user_id = ? AND status = 'completed' AND session_type != 'break'
            ");
            $totalSecStmt->execute([$userId]);
            $totalSeconds = (int)$totalSecStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'sessions' => $sessions,
                'total_study_seconds' => $totalSeconds
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit;
    }

    if ($method === 'POST') {
        validateCsrfOrDie();
        $action = $inputData['action'] ?? '';

        // START A SESSION
        if ($action === 'start') {
            $type = in_array($inputData['session_type'] ?? '', ['focus', 'pomodoro', 'stopwatch', 'break']) 
                ? $inputData['session_type'] 
                : 'focus';
            $plannedSec = !empty($inputData['planned_duration_seconds']) ? (int)$inputData['planned_duration_seconds'] : null;
            $subjectId = !empty($inputData['subject_id']) ? (int)$inputData['subject_id'] : null;
            $label = !empty($inputData['session_label']) ? trim($inputData['session_label']) : null;

            // Verify subject ownership
            if ($subjectId) {
                $chk = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_id = ? AND user_id = ?");
                $chk->execute([$subjectId, $userId]);
                if (!$chk->fetch()) {
                    $subjectId = null;
                }
            }

            // Cancel any old running/paused sessions for this user to prevent orphan active sessions
            $cancelStmt = $pdo->prepare("UPDATE study_sessions SET status = 'cancelled', ended_at = NOW() WHERE user_id = ? AND status IN ('running', 'paused')");
            $cancelStmt->execute([$userId]);

            $now = date('Y-m-d H:i:s');
            $ins = $pdo->prepare("INSERT INTO study_sessions 
                (user_id, subject_id, session_label, session_type, status, started_at, last_resumed_at, planned_duration_seconds, duration_seconds, accumulated_seconds)
                VALUES (?, ?, ?, ?, 'running', ?, ?, ?, 0, 0)");
            $ins->execute([$userId, $subjectId, $label, $type, $now, $now, $plannedSec]);
            $sessionId = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'started_at' => $now
            ]);
            exit;
        }

        // PAUSE A SESSION
        if ($action === 'pause') {
            $sessionId = (int)($inputData['session_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT session_id, last_resumed_at, accumulated_seconds FROM study_sessions WHERE session_id = ? AND user_id = ? AND status = 'running'");
            $stmt->execute([$sessionId, $userId]);
            $session = $stmt->fetch();

            if (!$session) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Active running session not found.']);
                exit;
            }

            $additional = 0;
            if (!empty($session['last_resumed_at'])) {
                $additional = max(0, time() - strtotime($session['last_resumed_at']));
            }
            $newAccumulated = (int)$session['accumulated_seconds'] + $additional;

            $upd = $pdo->prepare("UPDATE study_sessions SET status = 'paused', accumulated_seconds = ?, last_resumed_at = NULL WHERE session_id = ?");
            $upd->execute([$newAccumulated, $sessionId]);

            echo json_encode(['success' => true, 'accumulated_seconds' => $newAccumulated]);
            exit;
        }

        // RESUME A SESSION
        if ($action === 'resume') {
            $sessionId = (int)($inputData['session_id'] ?? 0);
            $now = date('Y-m-d H:i:s');
            $upd = $pdo->prepare("UPDATE study_sessions SET status = 'running', last_resumed_at = ? WHERE session_id = ? AND user_id = ? AND status = 'paused'");
            $upd->execute([$now, $sessionId, $userId]);

            if ($upd->rowCount() > 0) {
                echo json_encode(['success' => true, 'last_resumed_at' => $now]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Paused session not found.']);
            }
            exit;
        }

        // COMPLETE A SESSION
        if ($action === 'complete') {
            $sessionId = (int)($inputData['session_id'] ?? 0);
            $overrideSeconds = isset($inputData['final_duration_seconds']) ? (int)$inputData['final_duration_seconds'] : null;

            $stmt = $pdo->prepare("SELECT session_id, status, last_resumed_at, accumulated_seconds, session_type FROM study_sessions WHERE session_id = ? AND user_id = ?");
            $stmt->execute([$sessionId, $userId]);
            $session = $stmt->fetch();

            if (!$session) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Session not found.']);
                exit;
            }

            if ($session['status'] === 'completed') {
                echo json_encode(['success' => true, 'already_completed' => true]);
                exit;
            }

            $finalDuration = (int)$session['accumulated_seconds'];
            if ($session['status'] === 'running' && !empty($session['last_resumed_at'])) {
                $finalDuration += max(0, time() - strtotime($session['last_resumed_at']));
            }

            // If client supplied accurate timer duration (e.g. countdown completed exactly at planned time)
            if ($overrideSeconds !== null && $overrideSeconds > 0) {
                // Ensure sanity check: override can't be impossibly large
                if ($overrideSeconds <= $finalDuration + 60) {
                    $finalDuration = $overrideSeconds;
                }
            }

            $now = date('Y-m-d H:i:s');
            $upd = $pdo->prepare("UPDATE study_sessions SET status = 'completed', ended_at = ?, duration_seconds = ?, accumulated_seconds = ?, last_resumed_at = NULL WHERE session_id = ?");
            $upd->execute([$now, $finalDuration, $finalDuration, $sessionId]);

            // Check achievements triggers!
            $unlocked = [];
            if ($session['session_type'] !== 'break') {
                $unlocked = checkAndUnlockAchievements($pdo, $userId);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Session completed successfully.',
                'duration_seconds' => $finalDuration,
                'unlocked_achievements' => $unlocked
            ]);
            exit;
        }

        // CANCEL A SESSION
        if ($action === 'cancel') {
            $sessionId = (int)($inputData['session_id'] ?? 0);
            $now = date('Y-m-d H:i:s');
            $upd = $pdo->prepare("UPDATE study_sessions SET status = 'cancelled', ended_at = ? WHERE session_id = ? AND user_id = ?");
            $upd->execute([$now, $sessionId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Session cancelled.']);
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
    error_log("api/sessions.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred.']);
}


