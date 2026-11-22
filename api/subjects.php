<?php
// api/subjects.php - Subjects Management CRUD API
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

// Parse JSON payload if sent
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?? $_POST;

try {
    if ($method === 'GET') {
        // Fetch subjects with total study time and session counts
        $stmt = $pdo->prepare("
            SELECT 
                s.subject_id, 
                s.subject_name, 
                s.colour, 
                s.description, 
                s.created_at,
                COALESCE(SUM(CASE WHEN ss.status = 'completed' AND ss.session_type != 'break' THEN ss.duration_seconds ELSE 0 END), 0) AS total_seconds,
                COUNT(CASE WHEN ss.status = 'completed' AND ss.session_type != 'break' THEN 1 ELSE NULL END) AS session_count
            FROM subjects s
            LEFT JOIN study_sessions ss ON s.subject_id = ss.subject_id
            WHERE s.user_id = ?
            GROUP BY s.subject_id
            ORDER BY s.subject_name ASC
        ");
        $stmt->execute([$userId]);
        $subjects = $stmt->fetchAll();

        echo json_encode(['success' => true, 'subjects' => $subjects]);
        exit;
    }

    if ($method === 'POST') {
        validateCsrfOrDie();
        $action = $inputData['action'] ?? 'create';

        if ($action === 'create') {
            $name = trim($inputData['subject_name'] ?? '');
            $colour = trim($inputData['colour'] ?? '#ea580c');
            $desc = trim($inputData['description'] ?? '');

            if (empty($name)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Subject name is required.']);
                exit;
            }

            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $colour)) {
                $colour = '#ea580c';
            }

            $stmt = $pdo->prepare("INSERT INTO subjects (user_id, subject_name, colour, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $colour, $desc]);
            $newId = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Subject created successfully.',
                'subject_id' => $newId
            ]);
            exit;
        }

        if ($action === 'update') {
            $subjectId = (int)($inputData['subject_id'] ?? 0);
            $name = trim($inputData['subject_name'] ?? '');
            $colour = trim($inputData['colour'] ?? '#ea580c');
            $desc = trim($inputData['description'] ?? '');

            if ($subjectId <= 0 || empty($name)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Valid subject ID and name are required.']);
                exit;
            }

            // Verify ownership
            $chk = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_id = ? AND user_id = ?");
            $chk->execute([$subjectId, $userId]);
            if (!$chk->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Subject not found or access denied.']);
                exit;
            }

            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $colour)) {
                $colour = '#ea580c';
            }

            $upd = $pdo->prepare("UPDATE subjects SET subject_name = ?, colour = ?, description = ? WHERE subject_id = ? AND user_id = ?");
            $upd->execute([$name, $colour, $desc, $subjectId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Subject updated successfully.']);
            exit;
        }

        if ($action === 'delete') {
            $subjectId = (int)($inputData['subject_id'] ?? 0);
            if ($subjectId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Invalid subject ID.']);
                exit;
            }

            // Verify ownership and delete
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ? AND user_id = ?");
            $stmt->execute([$subjectId, $userId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Subject deleted successfully. Historical study sessions preserved.']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Subject not found or already deleted.']);
            }
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
    error_log("api/subjects.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred.']);
}
