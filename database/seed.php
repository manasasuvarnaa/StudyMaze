<?php
// database/seed.php - Repeatable Database Seeder with Multi-Month Study Records
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = getDbConnection();

    // 1. Ensure tables exist
    $sqlFile = __DIR__ . '/database.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
    }

    // 2. Demonstration User: Abhiksha
    $demoName = 'Abhiksha';
    $demoUsername = 'abhiksha';
    $demoEmail = 'abhiksha@gmail.com';
    $demoPassword = '123456789'; // Updated to 123456789 as requested
    $passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);

    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$demoUsername, $demoEmail]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $userId = (int)$existingUser['user_id'];
        $upd = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, password_hash = ? WHERE user_id = ?");
        $upd->execute([$demoName, $demoUsername, $demoEmail, $passwordHash, $userId]);
    } else {
        $ins = $pdo->prepare("INSERT INTO users (name, username, email, password_hash) VALUES (?, ?, ?, ?)");
        $ins->execute([$demoName, $demoUsername, $demoEmail, $passwordHash]);
        $userId = (int)$pdo->lastInsertId();
    }

    // 3. User Settings
    $stmt = $pdo->prepare("SELECT setting_id FROM user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        $setStmt = $pdo->prepare("INSERT INTO user_settings 
            (user_id, theme_name, appearance, accent_colour, daily_goal_minutes, focus_duration_minutes, short_break_minutes, long_break_minutes, pomodoros_before_long_break, auto_start_breaks, sound_enabled, selected_sound, sound_volume, timer_notifications)
            VALUES (?, 'default', 'light', '#ea580c', 180, 25, 5, 15, 4, 1, 1, 'rain', 70, 1)");
        $setStmt->execute([$userId]);
    } else {
        $updSet = $pdo->prepare("UPDATE user_settings SET theme_name = 'default', appearance = 'light', daily_goal_minutes = 180 WHERE user_id = ?");
        $updSet->execute([$userId]);
    }

    // 4. Subjects: OOPS, Mathematics, Web Technologies, DBMS
    $subjectsToSeed = [
        ['name' => 'Mathematics', 'colour' => '#ea580c', 'desc' => 'Calculus, Linear Algebra, Probability & Numerical Methods'],
        ['name' => 'OOPS', 'colour' => '#3b82f6', 'desc' => 'Object Oriented Programming, Java/C++, Polymorphism & Design Patterns'],
        ['name' => 'Web Technologies', 'colour' => '#10b981', 'desc' => 'HTML5, CSS3, JavaScript, PHP, MySQL & REST APIs'],
        ['name' => 'DBMS', 'colour' => '#8b5cf6', 'desc' => 'Relational Model, SQL Queries, Normalization & Indexing']
    ];

    $subjectMap = [];
    foreach ($subjectsToSeed as $sub) {
        $chk = $pdo->prepare("SELECT subject_id FROM subjects WHERE user_id = ? AND subject_name = ?");
        $chk->execute([$userId, $sub['name']]);
        $row = $chk->fetch();
        if ($row) {
            $subjectMap[$sub['name']] = (int)$row['subject_id'];
            $updSub = $pdo->prepare("UPDATE subjects SET colour = ?, description = ? WHERE subject_id = ?");
            $updSub->execute([$sub['colour'], $sub['desc'], $row['subject_id']]);
        } else {
            $insSub = $pdo->prepare("INSERT INTO subjects (user_id, subject_name, colour, description) VALUES (?, ?, ?, ?)");
            $insSub->execute([$userId, $sub['name'], $sub['colour'], $sub['desc']]);
            $subjectMap[$sub['name']] = (int)$pdo->lastInsertId();
        }
    }

    // 5. Tasks
    $tasksToSeed = [
        [
            'title' => 'Solve Multivariable Calculus Exercise 5.3',
            'subject' => 'Mathematics',
            'desc' => 'Partial derivatives and double integrals problem set',
            'priority' => 'high',
            'status' => 'pending',
            'deadline' => date('Y-m-d 18:00:00', strtotime('+2 days'))
        ],
        [
            'title' => 'Implement Observer & Factory Patterns in Java',
            'subject' => 'OOPS',
            'desc' => 'Write clean class diagrams and unit tests',
            'priority' => 'high',
            'status' => 'pending',
            'deadline' => date('Y-m-d 20:00:00', strtotime('+1 day'))
        ],
        [
            'title' => 'Finalize StudyMaze PHP API & Session Handlers',
            'subject' => 'Web Technologies',
            'desc' => 'Ensure robust PDO prepared statements and CSRF verification',
            'priority' => 'high',
            'status' => 'pending',
            'deadline' => date('Y-m-d 17:00:00', strtotime('+3 days'))
        ],
        [
            'title' => 'Practice 3NF and BCNF Normalization Problems',
            'subject' => 'DBMS',
            'desc' => 'Decompose relation schemas and identify candidate keys',
            'priority' => 'medium',
            'status' => 'pending',
            'deadline' => date('Y-m-d 19:00:00', strtotime('+4 days'))
        ],
        [
            'title' => 'Revise Eigenvalues and Matrix Diagonalization',
            'subject' => 'Mathematics',
            'desc' => 'Chapter 4 Linear Algebra review for upcoming quiz',
            'priority' => 'medium',
            'status' => 'pending',
            'deadline' => date('Y-m-d 16:00:00', strtotime('+5 days'))
        ],
        [
            'title' => 'Design Schema for University Database Project',
            'subject' => 'DBMS',
            'desc' => 'Draw ER diagram and map foreign key constraints',
            'priority' => 'high',
            'status' => 'pending',
            'deadline' => date('Y-m-d 21:00:00', strtotime('+6 days'))
        ],
        [
            'title' => 'Build Responsive Navigation & Dark Mode CSS',
            'subject' => 'Web Technologies',
            'desc' => 'CSS custom properties and mobile layout adaptivity',
            'priority' => 'medium',
            'status' => 'completed',
            'deadline' => date('Y-m-d 12:00:00', strtotime('-1 day'))
        ],
        [
            'title' => 'Complete Abstract Classes and Interface Lab',
            'subject' => 'OOPS',
            'desc' => 'Lab exercise 3 code submission',
            'priority' => 'low',
            'status' => 'completed',
            'deadline' => date('Y-m-d 15:00:00', strtotime('-3 days'))
        ]
    ];

    // Clear old tasks for user to have a clean, coherent task list
    $pdo->prepare("DELETE FROM tasks WHERE user_id = ?")->execute([$userId]);

    $insTask = $pdo->prepare("INSERT INTO tasks (user_id, subject_id, task_name, description, priority, status, deadline, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($tasksToSeed as $tsk) {
        $subId = $subjectMap[$tsk['subject']] ?? null;
        $completedAt = ($tsk['status'] === 'completed') ? date('Y-m-d H:i:s', strtotime('-1 day')) : null;
        $insTask->execute([
            $userId,
            $subId,
            $tsk['title'],
            $tsk['desc'],
            $tsk['priority'],
            $tsk['status'],
            $tsk['deadline'],
            $completedAt
        ]);
    }

    // 6. Months of Study Sessions Records (July, August, September, October 2026)
    // Clear previous sessions for user so we generate a rich, consistent multi-month dataset
    $pdo->prepare("DELETE FROM study_sessions WHERE user_id = ?")->execute([$userId]);

    $insSession = $pdo->prepare("INSERT INTO study_sessions 
        (user_id, subject_id, session_label, session_type, status, started_at, ended_at, planned_duration_seconds, duration_seconds, accumulated_seconds)
        VALUES (?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?)");

    $multiMonthPlan = [];

    // Helper to generate sessions
    // Oct 2026 (Current Month)
    // Oct 20 (Today): ~2h 5m study time + 5 pomodoros
    $todayBase = strtotime('today 00:00:00');
    $multiMonthPlan[] = ['Mathematics', 'Calculus Problem Set 5', 'focus', 4800, $todayBase + 10 * 3600]; // 1h 20m at 10 AM
    $multiMonthPlan[] = ['Web Technologies', 'PHP Session Security', 'focus', 2700, $todayBase + 14 * 3600]; // 45m at 2 PM
    $multiMonthPlan[] = ['OOPS', 'Design Patterns Drill', 'pomodoro', 1500, $todayBase + 16 * 3600]; // 25m at 4 PM
    $multiMonthPlan[] = ['DBMS', 'SQL Indexing Practicals', 'pomodoro', 1500, $todayBase + 17 * 3600]; // 25m at 5 PM
    $multiMonthPlan[] = ['Web Technologies', 'REST API Testing', 'pomodoro', 1500, $todayBase + 18 * 3600]; // 25m at 6 PM
    $multiMonthPlan[] = ['Mathematics', 'Quick Formula Review', 'pomodoro', 1500, $todayBase + 19 * 3600]; // 25m at 7 PM
    $multiMonthPlan[] = ['OOPS', 'Polymorphism Recap', 'pomodoro', 1500, $todayBase + 20 * 3600]; // 25m at 8 PM

    // Oct 19 (Yesterday)
    $yBase = $todayBase - 86400;
    $multiMonthPlan[] = ['Web Technologies', 'CSS Grid & Flexbox layouts', 'focus', 3000, $yBase + 11 * 3600]; // 50m
    $multiMonthPlan[] = ['DBMS', 'Relational Algebra', 'focus', 2400, $yBase + 15 * 3600]; // 40m
    $multiMonthPlan[] = ['Mathematics', 'Linear Equations system', 'focus', 2700, $yBase + 18 * 3600]; // 45m

    // Oct 18 (Day -2)
    $d2Base = $todayBase - 86400 * 2;
    $multiMonthPlan[] = ['OOPS', 'Abstract Factory Pattern', 'focus', 3600, $d2Base + 10 * 3600]; // 1h
    $multiMonthPlan[] = ['Mathematics', 'Vectors and Matrices', 'focus', 2700, $d2Base + 14 * 3600]; // 45m
    $multiMonthPlan[] = ['Web Technologies', 'JavaScript Event Loop', 'pomodoro', 1500, $d2Base + 17 * 3600]; // 25m

    // Oct 17 (Day -3) - Saturday Marathon
    $d3Base = $todayBase - 86400 * 3;
    $multiMonthPlan[] = ['Mathematics', 'Calculus Marathon', 'focus', 5400, $d3Base + 9 * 3600]; // 1h 30m
    $multiMonthPlan[] = ['DBMS', 'Normalization 1NF to BCNF', 'focus', 4800, $d3Base + 13 * 3600]; // 1h 20m
    $multiMonthPlan[] = ['Web Technologies', 'Fullstack project building', 'focus', 7200, $d3Base + 16 * 3600]; // 2h 0m
    $multiMonthPlan[] = ['OOPS', 'Exception Handling', 'focus', 3600, $d3Base + 20 * 3600]; // 1h 0m

    // Oct 16 (Day -4)
    $d4Base = $todayBase - 86400 * 4;
    $multiMonthPlan[] = ['Web Technologies', 'DOM Manipulation and Web APIs', 'focus', 4500, $d4Base + 11 * 3600]; // 1h 15m
    $multiMonthPlan[] = ['OOPS', 'Inheritance & Subclassing', 'focus', 3600, $d4Base + 15 * 3600]; // 1h

    // Oct 15 (Day -5)
    $d5Base = $todayBase - 86400 * 5;
    $multiMonthPlan[] = ['DBMS', 'Complex Joins and Subqueries', 'focus', 4200, $d5Base + 10 * 3600]; // 1h 10m
    $multiMonthPlan[] = ['Mathematics', 'Integration by parts', 'focus', 3000, $d5Base + 14 * 3600]; // 50m

    // Oct 14 (Day -6) - Completes 7-day streak
    $d6Base = $todayBase - 86400 * 6;
    $multiMonthPlan[] = ['OOPS', 'Java Collections Framework', 'focus', 3600, $d6Base + 11 * 3600]; // 1h
    $multiMonthPlan[] = ['Web Technologies', 'PHP PDO Prepared Statements', 'focus', 3000, $d6Base + 16 * 3600]; // 50m

    // Earlier October (Oct 1 to Oct 13)
    $octDates = [
        ['day' => 12, 'sub' => 'Mathematics', 'label' => 'Eigenvalues and Eigenvectors', 'type' => 'focus', 'dur' => 4500],
        ['day' => 12, 'sub' => 'DBMS', 'label' => 'Transaction ACID Properties', 'type' => 'pomodoro', 'dur' => 1500],
        ['day' => 11, 'sub' => 'Web Technologies', 'label' => 'CSS Animations and Transitions', 'type' => 'focus', 'dur' => 3600],
        ['day' => 10, 'sub' => 'OOPS', 'label' => 'Multithreading and Concurrency', 'type' => 'focus', 'dur' => 5400],
        ['day' => 9,  'sub' => 'Mathematics', 'label' => 'Taylor Series Expansion', 'type' => 'focus', 'dur' => 3600],
        ['day' => 8,  'sub' => 'DBMS', 'label' => 'B-Tree & Hash Indexing', 'type' => 'focus', 'dur' => 4200],
        ['day' => 7,  'sub' => 'Web Technologies', 'label' => 'Fetch API & JSON handling', 'type' => 'focus', 'dur' => 3600],
        ['day' => 6,  'sub' => 'OOPS', 'label' => 'Generics and Type Erasure', 'type' => 'focus', 'dur' => 3000],
        ['day' => 5,  'sub' => 'Mathematics', 'label' => 'Differential Equations Review', 'type' => 'focus', 'dur' => 4800],
        ['day' => 4,  'sub' => 'DBMS', 'label' => 'Entity Relationship Diagrams', 'type' => 'focus', 'dur' => 3600],
        ['day' => 3,  'sub' => 'Web Technologies', 'label' => 'Responsive Design Media Queries', 'type' => 'focus', 'dur' => 4200],
        ['day' => 2,  'sub' => 'OOPS', 'label' => 'Encapsulation and Data Hiding', 'type' => 'focus', 'dur' => 2700],
        ['day' => 1,  'sub' => 'Mathematics', 'label' => 'Limit and Continuity Theorems', 'type' => 'focus', 'dur' => 3600]
    ];
    foreach ($octDates as $od) {
        $ts = strtotime(sprintf('2026-10-%02d 14:00:00', $od['day']));
        $multiMonthPlan[] = [$od['sub'], $od['label'], $od['type'], $od['dur'], $ts];
    }

    // September 2026 (Month 9)
    $septDates = [
        ['day' => 30, 'sub' => 'Web Technologies', 'label' => 'HTML5 Canvas & Multimedia', 'type' => 'focus', 'dur' => 3600],
        ['day' => 29, 'sub' => 'DBMS', 'label' => 'Query Optimization Plans', 'type' => 'focus', 'dur' => 4200],
        ['day' => 28, 'sub' => 'Mathematics', 'label' => 'Probability Distributions', 'type' => 'focus', 'dur' => 5400],
        ['day' => 27, 'sub' => 'OOPS', 'label' => 'SOLID Principles Overview', 'type' => 'focus', 'dur' => 3600],
        ['day' => 25, 'sub' => 'Web Technologies', 'label' => 'PHP Superglobals & Sessions', 'type' => 'focus', 'dur' => 4800],
        ['day' => 24, 'sub' => 'Mathematics', 'label' => 'Bayes Theorem Problems', 'type' => 'focus', 'dur' => 3600],
        ['day' => 22, 'sub' => 'DBMS', 'label' => 'Stored Procedures & Triggers', 'type' => 'focus', 'dur' => 4500],
        ['day' => 20, 'sub' => 'OOPS', 'label' => 'Singleton and Prototype Patterns', 'type' => 'focus', 'dur' => 3600],
        ['day' => 19, 'sub' => 'Mathematics', 'label' => 'Gaussian Elimination', 'type' => 'focus', 'dur' => 4200],
        ['day' => 18, 'sub' => 'Web Technologies', 'label' => 'Form Validation Best Practices', 'type' => 'focus', 'dur' => 3600],
        ['day' => 16, 'sub' => 'DBMS', 'label' => 'View Creation and Index Tuning', 'type' => 'focus', 'dur' => 3000],
        ['day' => 15, 'sub' => 'Mathematics', 'label' => 'Midterm Review Calculus', 'type' => 'focus', 'dur' => 6000], // 1h 40m
        ['day' => 14, 'sub' => 'OOPS', 'label' => 'Abstract Classes Deep Dive', 'type' => 'focus', 'dur' => 3600],
        ['day' => 12, 'sub' => 'Web Technologies', 'label' => 'CSS Flexbox Guide', 'type' => 'focus', 'dur' => 2700],
        ['day' => 10, 'sub' => 'DBMS', 'label' => 'Functional Dependencies', 'type' => 'focus', 'dur' => 4200],
        ['day' => 8,  'sub' => 'Mathematics', 'label' => 'Matrix Inverses and Determinants', 'type' => 'focus', 'dur' => 3600],
        ['day' => 6,  'sub' => 'OOPS', 'label' => 'Inheritance vs Composition', 'type' => 'focus', 'dur' => 3600],
        ['day' => 4,  'sub' => 'Web Technologies', 'label' => 'Semantic HTML Elements', 'type' => 'focus', 'dur' => 3000],
        ['day' => 2,  'sub' => 'DBMS', 'label' => 'SQL DDL vs DML commands', 'type' => 'focus', 'dur' => 3600]
    ];
    foreach ($septDates as $sd) {
        $ts = strtotime(sprintf('2026-09-%02d 15:00:00', $sd['day']));
        $multiMonthPlan[] = [$sd['sub'], $sd['label'], $sd['type'], $sd['dur'], $ts];
    }

    // August 2026 (Month 8)
    $augDates = [
        ['day' => 31, 'sub' => 'Mathematics', 'label' => 'Fourier Series Basics', 'type' => 'focus', 'dur' => 4200],
        ['day' => 29, 'sub' => 'OOPS', 'label' => 'Polymorphism Lab Practice', 'type' => 'focus', 'dur' => 3600],
        ['day' => 27, 'sub' => 'Web Technologies', 'label' => 'JavaScript ES6 Syntax', 'type' => 'focus', 'dur' => 4800],
        ['day' => 25, 'sub' => 'DBMS', 'label' => 'Foreign Key Cascading Rules', 'type' => 'focus', 'dur' => 3600],
        ['day' => 23, 'sub' => 'Mathematics', 'label' => 'Complex Numbers and Roots', 'type' => 'focus', 'dur' => 4500],
        ['day' => 20, 'sub' => 'OOPS', 'label' => 'Method Overloading vs Overriding', 'type' => 'focus', 'dur' => 3600],
        ['day' => 18, 'sub' => 'Web Technologies', 'label' => 'DOM Event Listeners & Bubbling', 'type' => 'focus', 'dur' => 4200],
        ['day' => 16, 'sub' => 'DBMS', 'label' => 'Aggregate Functions (GROUP BY, HAVING)', 'type' => 'focus', 'dur' => 3600],
        ['day' => 14, 'sub' => 'Mathematics', 'label' => 'Integration Techniques Drill', 'type' => 'focus', 'dur' => 5400],
        ['day' => 11, 'sub' => 'OOPS', 'label' => 'Class Constructors and Destructors', 'type' => 'focus', 'dur' => 3000],
        ['day' => 8,  'sub' => 'Web Technologies', 'label' => 'CSS Box Model and Positioning', 'type' => 'focus', 'dur' => 3600],
        ['day' => 5,  'sub' => 'DBMS', 'label' => 'Database Architecture 3-Tier', 'type' => 'focus', 'dur' => 3600],
        ['day' => 2,  'sub' => 'Mathematics', 'label' => 'Differential Calculus Fundamentals', 'type' => 'focus', 'dur' => 4200]
    ];
    foreach ($augDates as $ad) {
        $ts = strtotime(sprintf('2026-08-%02d 11:00:00', $ad['day']));
        $multiMonthPlan[] = [$ad['sub'], $ad['label'], $ad['type'], $ad['dur'], $ts];
    }

    // July 2026 (Month 7)
    $julyDates = [
        ['day' => 30, 'sub' => 'Web Technologies', 'label' => 'Web Servers and HTTP Protocol', 'type' => 'focus', 'dur' => 3600],
        ['day' => 28, 'sub' => 'DBMS', 'label' => 'Intro to Relational Databases', 'type' => 'focus', 'dur' => 3600],
        ['day' => 25, 'sub' => 'OOPS', 'label' => 'OOP Paradigms and Objects', 'type' => 'focus', 'dur' => 4200],
        ['day' => 22, 'sub' => 'Mathematics', 'label' => 'Calculus Refresher and Limits', 'type' => 'focus', 'dur' => 4800],
        ['day' => 19, 'sub' => 'Web Technologies', 'label' => 'HTML Fundamentals', 'type' => 'focus', 'dur' => 3000],
        ['day' => 16, 'sub' => 'OOPS', 'label' => 'Setting up Java Development Kit', 'type' => 'focus', 'dur' => 3600],
        ['day' => 12, 'sub' => 'Mathematics', 'label' => 'Functions and Graphing', 'type' => 'focus', 'dur' => 3600],
        ['day' => 8,  'sub' => 'DBMS', 'label' => 'DBMS vs File Systems', 'type' => 'focus', 'dur' => 3000]
    ];
    foreach ($julyDates as $jd) {
        $ts = strtotime(sprintf('2026-07-%02d 10:00:00', $jd['day']));
        $multiMonthPlan[] = [$jd['sub'], $jd['label'], $jd['type'], $jd['dur'], $ts];
    }

    // Insert all multi-month study sessions
    foreach ($multiMonthPlan as $sp) {
        $subId = $subjectMap[$sp[0]] ?? null;
        $startedAt = date('Y-m-d H:i:s', $sp[4]);
        $endedAt = date('Y-m-d H:i:s', $sp[4] + $sp[3]);
        $insSession->execute([
            $userId,
            $subId,
            $sp[1],
            $sp[2],
            $startedAt,
            $endedAt,
            $sp[3],
            $sp[3],
            $sp[3]
        ]);
    }

    // 7. Unlock All Milestones / Achievements for Abhiksha
    $achieveStmt = $pdo->query("SELECT achievement_id, achievement_key FROM achievements");
    $allAchievements = $achieveStmt->fetchAll();
    $insAch = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, ?)");

    foreach ($allAchievements as $ach) {
        $insAch->execute([$userId, $ach['achievement_id'], date('Y-m-d H:i:s', strtotime('-10 days'))]);
    }

    echo "=========================================================\n";
    echo " StudyMaze Database Seeded Successfully!                \n";
    echo "=========================================================\n";
    echo " User Account: {$demoName}\n";
    echo " Username:     {$demoUsername}\n";
    echo " Email:        {$demoEmail}\n";
    echo " Password:     {$demoPassword}\n";
    echo " Subjects:     Mathematics, OOPS, Web Technologies, DBMS\n";
    echo " Sessions:     " . count($multiMonthPlan) . " sessions across July, August, September, October 2026\n";
    echo "=========================================================\n";

} catch (Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
