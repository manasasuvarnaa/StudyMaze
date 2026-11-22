<?php
// includes/header.php - Global HTML Head and Theme Initialization
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

$currentUser = getCurrentUser();
$userSettings = $currentUser ? getUserSettings((int)$currentUser['user_id']) : [
    'theme_name' => 'default',
    'appearance' => 'light',
    'accent_colour' => '#ea580c'
];

$activeTheme = $userSettings['theme_name'] ?? 'default';
$activeMode = $userSettings['appearance'] ?? 'light';
$pageTitle = $pageTitle ?? 'StudyMaze — Find your focus. Build your flow.';
$metaDescription = $metaDescription ?? 'StudyMaze is an aesthetic, distraction-free study and productivity platform with timers, Pomodoro, subject tracking, and analytics.';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($activeTheme, ENT_QUOTES, 'UTF-8') ?>" data-mode="<?= htmlspecialchars($activeMode, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <meta name="theme-color" content="#ea580c">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?><?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/StudyMaze/', '?'), ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='3' width='5' height='18' rx='2.5' fill='%23ea580c'/%3E%3Crect x='11' y='7' width='5' height='14' rx='2.5' fill='%23ea580c'/%3E%3C/svg%3E">
  
  <!-- CSS Stylesheets (with automatic cache busting) -->
  <link rel="stylesheet" href="assets/css/themes.css?v=<?= filemtime(__DIR__ . '/../assets/css/themes.css') ?>">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
  <link rel="stylesheet" href="assets/css/responsive.css?v=<?= filemtime(__DIR__ . '/../assets/css/responsive.css') ?>">

  <!-- Lucide Icons (Deferred for maximum render performance) -->
  <script src="assets/js/lucide.min.js" defer></script>
</head>
<body>
  <!-- Accessible Skip-to-Content Link -->
  <a href="#mainContent" class="skip-link">
    Skip to main content
  </a>
