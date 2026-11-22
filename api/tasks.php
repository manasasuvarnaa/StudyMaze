<?php
// api/tasks.php - Tasks Management CRUD API
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
        $status = $_GET['status'] ?? 'all';
        $sql = "
            SELECT 
                t.task_id, 
                t.subject_id, 
                t.task_name, 
                t.description, 
                t.priority, 
                t.status, 
                t.deadline, 
                t.completed_at, 
                t.created_at,
                s.subject_name,
                s.colour AS subject_colour
            FROM tasks t
            LEFT JOIN subjects s ON t.subject_id = s.subject_id
            WHERE t.user_id = ?
        ";
        $params = [$userId];

        if ($status === 'pending') {
            $sql .= " AND t.status = 'pending'";
        } elseif ($status === 'completed') {
            $sql .= " AND t.status = 'completed'";
        }

        $sql .= " ORDER BY (t.status = 'completed') ASC, (t.deadline IS NULL) ASC, t.deadline ASC, t.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        echo json_encode(['success' => true, 'tasks' => $tasks]);
        exit;
    }

    if ($method === 'POST') {
        validateCsrfOrDie();
        $action = $inputData['action'] ?? 'create';

        if ($action === 'create') {
            $title = trim($inputData['task_name'] ?? '');
            $subjectId = !empty($inputData['subject_id']) ? (int)$inputData['subject_id'] : null;
            $desc = trim($inputData['description'] ?? '');
            $priority = in_array($inputData['priority'] ?? '', ['low', 'medium', 'high']) ? $inputData['priority'] : 'medium';
            $deadline = !empty($inputData['deadline']) ? date('Y-m-d H:i:s', strtotime($inputData['deadline'])) : null;

            if (empty($title)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Task title is required.']);
                exit;
            }

            // Verify subject ownership if assigned
            if ($subjectId) {
                $chk = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_id = ? AND user_id = ?");
                $chk->execute([$subjectId, $userId]);
                if (!$chk->fetch()) {
                    $subjectId = null; // Ignore invalid subject
                }
            }

            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, subject_id, task_name, description, priority, status, deadline)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$userId, $subjectId, $title, $desc, $priority, $deadline]);
            $taskId = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Task created successfully.',
                'task_id' => $taskId
            ]);
            exit;
        }

        if ($action === 'update') {
            $taskId = (int)($inputData['task_id'] ?? 0);
            $title = trim($inputData['task_name'] ?? '');
            $subjectId = !empty($inputData['subject_id']) ? (int)$inputData['subject_id'] : null;
            $desc = trim($inputData['description'] ?? '');
            $priority = in_array($inputData['priority'] ?? '', ['low', 'medium', 'high']) ? $inputData['priority'] : 'medium';
            $deadline = !empty($inputData['deadline']) ? date('Y-m-d H:i:s', strtotime($inputData['deadline'])) : null;

            if ($taskId <= 0 || empty($title)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Task ID and title are required.']);
                exit;
            }

            // Check ownership
            $chk = $pdo->prepare("SELECT task_id FROM tasks WHERE task_id = ? AND user_id = ?");
            $chk->execute([$taskId, $userId]);
            if (!$chk->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Task not found or access denied.']);
                exit;
            }

            // Verify subject ownership if assigned
            if ($subjectId) {
                $chkSub = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_id = ? AND user_id = ?");
                $chkSub->execute([$subjectId, $userId]);
                if (!$chkSub->fetch()) {
                    $subjectId = null;
                }
            }

            $upd = $pdo->prepare("UPDATE tasks SET subject_id = ?, task_name = ?, description = ?, priority = ?, deadline = ? WHERE task_id = ? AND user_id = ?");
            $upd->execute([$subjectId, $title, $desc, $priority, $deadline, $taskId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Task updated successfully.']);
            exit;
        }

        if ($action === 'toggle') {
            $taskId = (int)($inputData['task_id'] ?? 0);
            $newStatus = ($inputData['status'] ?? '') === 'completed' ? 'completed' : 'pending';
            $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE task_id = ? AND user_id = ?");
            $stmt->execute([$newStatus, $completedAt, $taskId, $userId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'status' => $newStatus, 'completed_at' => $completedAt]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Task not found.']);
            }
            exit;
        }

        if ($action === 'delete') {
            $taskId = (int)($inputData['task_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ? AND user_id = ?");
            $stmt->execute([$taskId, $userId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Task not found or already deleted.']);
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
    error_log("api/tasks.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred.']);
}
