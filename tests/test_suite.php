<?php
// tests/test_suite.php - Automated End-to-End Integration Test Suite
declare(strict_types=1);

$baseUrl = 'http://localhost/StudyMaze';
$cookieJar = __DIR__ . '/test_cookie_jar.txt';
if (file_exists($cookieJar)) {
    unlink($cookieJar);
}

function httpReq(string $url, string $method = 'GET', $data = null, array $headers = []): array {
    global $cookieJar;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif (is_string($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headerStr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    return [
        'status' => $info['http_code'],
        'headers' => $headerStr,
        'body' => $body
    ];
}

echo "=======================================================\n";
echo "   StudyMaze End-to-End Integration Test Suite        \n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $testName\n";
        $passCount++;
    } else {
        echo " [FAIL] $testName\n";
        $failCount++;
    }
}

// 1. Unauthenticated Dashboard Access -> Redirect to login.php
$res = httpReq("$baseUrl/dashboard.php", 'GET');
assertTest($res['status'] === 302 && strpos($res['headers'], 'Location: login.php') !== false, "Unauthenticated access redirects to login.php");

// 2. Fetch Login Page and CSRF token
$res = httpReq("$baseUrl/login.php", 'GET');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $res['body'], $matches);
$csrfToken = $matches[1] ?? '';
assertTest($res['status'] === 200 && !empty($csrfToken), "Login page renders with CSRF token");

// 3. Authenticate with Demonstration Account
$loginData = [
    'csrf_token' => $csrfToken,
    'login_id' => 'abhiksha@gmail.com',
    'password' => '123456789'
];
$res = httpReq("$baseUrl/login.php", 'POST', $loginData);
assertTest($res['status'] === 302 && strpos($res['headers'], 'Location: dashboard.php') !== false, "Demo account login successful (302 -> dashboard.php)");

// 4. Authenticated Dashboard Access
$res = httpReq("$baseUrl/dashboard.php", 'GET');
assertTest($res['status'] === 200 && strpos($res['body'], 'Abhiksha') !== false, "Dashboard loaded with Abhiksha profile name");
assertTest(strpos($res['body'], 'Daily Goal') !== false, "Dashboard displays Daily Goal bar");
assertTest(strpos($res['body'], 'Mathematics') !== false, "Dashboard displays seeded subjects");

// 5. API Analytics Check
$res = httpReq("$baseUrl/api/analytics.php?timeframe=weekly", 'GET');
$analytics = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $analytics['success'] === true, "Analytics API returns valid JSON");
assertTest(!empty($analytics['summary']) && $analytics['summary']['total_hours'] > 0, "Analytics total hours is non-zero ({$analytics['summary']['total_hours']}h)");
assertTest(!empty($analytics['by_subject']), "Analytics contains subject-wise distribution (" . count($analytics['by_subject']) . " subjects)");

// 6. API Subjects CRUD Check
$res = httpReq("$baseUrl/api/subjects.php", 'GET');
$subs = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $subs['success'] === true && count($subs['subjects']) >= 4, "Subjects API lists all seeded subjects (count >= 4)");

// Create a new test subject
$createSubData = json_encode([
    'action' => 'create',
    'subject_name' => 'Data Structures Test',
    'colour' => '#06b6d4',
    'description' => 'Automated test subject'
]);
$res = httpReq("$baseUrl/api/subjects.php", 'POST', $createSubData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$createdSub = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $createdSub['success'] === true, "Subject creation successful");
$testSubId = $createdSub['subject_id'] ?? 0;

// Update test subject
$updSubData = json_encode([
    'action' => 'update',
    'subject_id' => $testSubId,
    'subject_name' => 'Data Structures Renamed',
    'colour' => '#3b82f6',
    'description' => 'Updated notes'
]);
$res = httpReq("$baseUrl/api/subjects.php", 'POST', $updSubData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$updSub = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $updSub['success'] === true, "Subject update successful");

// 7. API Tasks CRUD Check
$createTaskData = json_encode([
    'action' => 'create',
    'task_name' => 'Binary Trees Assignment',
    'subject_id' => $testSubId,
    'priority' => 'high',
    'deadline' => date('Y-m-d H:i:s', strtotime('+3 days')),
    'description' => 'Practice balanced trees'
]);
$res = httpReq("$baseUrl/api/tasks.php", 'POST', $createTaskData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$createdTask = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $createdTask['success'] === true, "Task creation successful");
$testTaskId = $createdTask['task_id'] ?? 0;

// Toggle task status
$toggleTaskData = json_encode([
    'action' => 'toggle',
    'task_id' => $testTaskId,
    'status' => 'completed'
]);
$res = httpReq("$baseUrl/api/tasks.php", 'POST', $toggleTaskData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$toggleTask = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $toggleTask['status'] === 'completed', "Task status toggle to completed successful");

// Delete test task
$delTaskData = json_encode([
    'action' => 'delete',
    'task_id' => $testTaskId
]);
$res = httpReq("$baseUrl/api/tasks.php", 'POST', $delTaskData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$delTask = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $delTask['success'] === true, "Task deletion successful");

// Delete test subject
$delSubData = json_encode([
    'action' => 'delete',
    'subject_id' => $testSubId
]);
$res = httpReq("$baseUrl/api/subjects.php", 'POST', $delSubData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$delSub = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $delSub['success'] === true, "Subject deletion successful (history preserved)");

// 8. Study Timer Session Lifecycle (Start -> Active Recovery -> Pause -> Resume -> Complete)
$startSessionData = json_encode([
    'action' => 'start',
    'session_type' => 'focus',
    'planned_duration_seconds' => 1500,
    'session_label' => 'Automated Integration Focus'
]);
$res = httpReq("$baseUrl/api/sessions.php", 'POST', $startSessionData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$startSes = json_decode($res['body'], true);
assertTest($res['status'] === 200 && $startSes['success'] === true, "Timer session started in MySQL");
$testSessionId = $startSes['session_id'] ?? 0;

// Test Active Recovery
$res = httpReq("$baseUrl/api/sessions.php?action=active", 'GET');
$activeSes = json_decode($res['body'], true);
assertTest($activeSes['has_active'] === true && $activeSes['session']['session_id'] == $testSessionId, "Active timer session recovery verified");

// Test Pause
$pauseData = json_encode(['action' => 'pause', 'session_id' => $testSessionId]);
$res = httpReq("$baseUrl/api/sessions.php", 'POST', $pauseData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$pauseSes = json_decode($res['body'], true);
assertTest($pauseSes['success'] === true, "Timer session paused in MySQL");

// Test Resume
$resumeData = json_encode(['action' => 'resume', 'session_id' => $testSessionId]);
$res = httpReq("$baseUrl/api/sessions.php", 'POST', $resumeData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$resumeSes = json_decode($res['body'], true);
assertTest($resumeSes['success'] === true, "Timer session resumed in MySQL");

// Test Complete
$completeData = json_encode([
    'action' => 'complete',
    'session_id' => $testSessionId
]);
$res = httpReq("$baseUrl/api/sessions.php", 'POST', $completeData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$compSes = json_decode($res['body'], true);
assertTest($compSes['success'] === true && isset($compSes['duration_seconds']), "Timer session completed and persisted");

// 9. Theme Switching and Persistence
$themeData = json_encode([
    'action' => 'update_theme',
    'theme_name' => 'ghibli'
]);
$res = httpReq("$baseUrl/api/settings.php", 'POST', $themeData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);
$themeRes = json_decode($res['body'], true);
assertTest($themeRes['success'] === true && $themeRes['theme_name'] === 'ghibli', "Theme updated to Studio Ghibli in MySQL");

// Check themes page reflects ghibli
$res = httpReq("$baseUrl/themes.php", 'GET');
assertTest(strpos($res['body'], 'data-theme="ghibli"') !== false, "Themes page renders with Studio Ghibli theme applied");

// Revert theme to default
$revertData = json_encode(['action' => 'update_theme', 'theme_name' => 'default']);
httpReq("$baseUrl/api/settings.php", 'POST', $revertData, [
    'Content-Type: application/json',
    "X-CSRF-Token: $csrfToken"
]);

// 10. Check All Other Pages Render Successfully
$pages = ['timer.php', 'analytics.php', 'subjects.php', 'tasks.php', 'history.php', 'settings.php', 'themes.php'];
foreach ($pages as $p) {
    $res = httpReq("$baseUrl/$p", 'GET');
    assertTest($res['status'] === 200, "Page $p renders with HTTP 200 OK");
}

// 11. Test Logout
$res = httpReq("$baseUrl/logout.php", 'GET');
assertTest($res['status'] === 302 && strpos($res['headers'], 'Location: login.php') !== false, "Logout destroys session and redirects to login.php");

// Verify unauthenticated again
$res = httpReq("$baseUrl/dashboard.php", 'GET');
assertTest($res['status'] === 302, "Session cleared: dashboard inaccessible after logout");

if (file_exists($cookieJar)) {
    unlink($cookieJar);
}

echo "\n=======================================================\n";
echo " Final Test Results: $passCount Passed, $failCount Failed\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
