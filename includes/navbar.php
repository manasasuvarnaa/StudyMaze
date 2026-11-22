<?php
// includes/navbar.php - Top Navigation Bar
declare(strict_types=1);

$activePage = $activePage ?? '';
$user = getCurrentUser();
$userName = $user ? htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') : 'User';
$username = $user ? htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') : 'user';
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<header class="navbar-container">
  <div class="navbar">

    <div class="navbar-brand-wrapper">
      <!-- Hamburger — visible only on mobile/tablet via CSS -->
      <button type="button" class="nav-hamburger-btn" id="navHamburgerBtn"
              aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobileSidebarNav">
        <i data-lucide="menu"></i>
      </button>

      <!-- Brand Logo -->
      <a href="index.php" class="navbar-brand" aria-label="StudyMaze Home" title="StudyMaze Home">
        <div class="brand-logo-icon">
          <span class="brand-bar-1"></span>
          <span class="brand-bar-2"></span>
        </div>
        <span class="brand-name">Study<span class="brand-maze">Maze</span></span>
      </a>
    </div>

    <!-- Desktop Horizontal Nav (hidden on small screens via CSS) -->
    <nav class="navbar-nav navbar-nav-desktop" aria-label="Main Navigation">
      <ul class="nav-links">
        <li>
          <a href="dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="timer.php" class="nav-link <?= $activePage === 'timer' ? 'active' : '' ?>" <?= $activePage === 'timer' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="timer"></i>
            <span>Timer</span>
          </a>
        </li>
        <li>
          <a href="analytics.php" class="nav-link <?= $activePage === 'analytics' ? 'active' : '' ?>" <?= $activePage === 'analytics' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="bar-chart-2"></i>
            <span>Analytics</span>
          </a>
        </li>
        <li>
          <a href="subjects.php" class="nav-link <?= $activePage === 'subjects' ? 'active' : '' ?>" <?= $activePage === 'subjects' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="book-open"></i>
            <span>Subjects</span>
          </a>
        </li>
        <li>
          <a href="tasks.php" class="nav-link <?= $activePage === 'tasks' ? 'active' : '' ?>" <?= $activePage === 'tasks' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="check-square"></i>
            <span>Tasks</span>
          </a>
        </li>
        <li>
          <a href="history.php" class="nav-link <?= $activePage === 'history' ? 'active' : '' ?>" <?= $activePage === 'history' ? 'aria-current="page"' : '' ?>>
            <i data-lucide="calendar"></i>
            <span>History</span>
          </a>
        </li>
      </ul>
    </nav>

    <!-- Right Side Controls -->
    <div class="nav-controls">
      <!-- Theme Selection Button -->
      <a href="themes.php" class="nav-btn <?= $activePage === 'themes' ? 'active' : '' ?>" title="Change Theme" aria-label="Change Theme">
        <i data-lucide="palette"></i>
      </a>

      <!-- Quick Light / Dark Appearance Toggle -->
      <button type="button" class="nav-btn" id="appearanceToggleBtn" title="Toggle Light / Dark Mode" aria-label="Toggle Light / Dark Mode">
        <i data-lucide="sun" class="sun-icon"></i>
        <i data-lucide="moon" class="moon-icon" style="display: none;"></i>
      </button>

      <!-- Profile Dropdown -->
      <div class="profile-dropdown-wrapper">
        <button type="button" class="profile-btn" id="profileDropdownBtn" aria-label="User Profile Menu" aria-haspopup="true" aria-expanded="false">
          <span class="profile-avatar-circle"><?= $userInitial ?></span>
          <span class="profile-name-text"><?= $userName ?></span>
          <i data-lucide="chevron-down" style="width: 14px; height: 14px;"></i>
        </button>

        <div class="profile-menu" id="profileMenu" role="menu" hidden aria-hidden="true">
          <div class="profile-menu-header">
            <div class="label">Signed in as</div>
            <div class="user-name"><?= $username ?></div>
          </div>
          
          <a href="settings.php#account" class="menu-item" role="menuitem">
            <span style="display: flex; align-items: center; gap: 8px;">
              <i data-lucide="settings" style="width: 16px; height: 16px;"></i> Settings
            </span>
          </a>

          <a href="logout.php" class="menu-item logout" role="menuitem">
            <span style="display: flex; align-items: center; gap: 8px;">
              <i data-lucide="log-out" style="width: 16px; height: 16px;"></i> Log Out
            </span>
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Mobile Sidebar Overlay (backdrop) -->
<div class="mobile-sidebar-overlay" id="mobileSidebarOverlay" aria-hidden="true"></div>

<!-- Mobile Sidebar Drawer -->
<nav class="mobile-sidebar" id="mobileSidebarNav" aria-label="Mobile Navigation" aria-hidden="true">
  <!-- Sidebar Header -->
  <div class="mobile-sidebar-header">
    <a href="index.php" class="navbar-brand" aria-label="StudyMaze Home">
      <div class="brand-logo-icon">
        <span class="brand-bar-1"></span>
        <span class="brand-bar-2"></span>
      </div>
      <span class="brand-name">Study<span class="brand-maze">Maze</span></span>
    </a>
    <button type="button" class="mobile-sidebar-close" id="mobileSidebarCloseBtn" aria-label="Close navigation menu">
      <i data-lucide="x"></i>
    </button>
  </div>

  <!-- Sidebar Nav Links -->
  <ul class="mobile-sidebar-links">
    <li>
      <a href="dashboard.php" class="mobile-sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="layout-dashboard"></i>
        <span>Dashboard</span>
      </a>
    </li>
    <li>
      <a href="timer.php" class="mobile-sidebar-link <?= $activePage === 'timer' ? 'active' : '' ?>" <?= $activePage === 'timer' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="timer"></i>
        <span>Timer</span>
      </a>
    </li>
    <li>
      <a href="analytics.php" class="mobile-sidebar-link <?= $activePage === 'analytics' ? 'active' : '' ?>" <?= $activePage === 'analytics' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="bar-chart-2"></i>
        <span>Analytics</span>
      </a>
    </li>
    <li>
      <a href="subjects.php" class="mobile-sidebar-link <?= $activePage === 'subjects' ? 'active' : '' ?>" <?= $activePage === 'subjects' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="book-open"></i>
        <span>Subjects</span>
      </a>
    </li>
    <li>
      <a href="tasks.php" class="mobile-sidebar-link <?= $activePage === 'tasks' ? 'active' : '' ?>" <?= $activePage === 'tasks' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="check-square"></i>
        <span>Tasks</span>
      </a>
    </li>
    <li>
      <a href="history.php" class="mobile-sidebar-link <?= $activePage === 'history' ? 'active' : '' ?>" <?= $activePage === 'history' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="calendar"></i>
        <span>History</span>
      </a>
    </li>
  </ul>

  <!-- Sidebar Footer: Settings & Logout -->
  <div class="mobile-sidebar-footer">
    <a href="settings.php#account" class="mobile-sidebar-link">
      <i data-lucide="settings"></i>
      <span>Settings</span>
    </a>
    <a href="logout.php" class="mobile-sidebar-link mobile-sidebar-logout">
      <i data-lucide="log-out"></i>
      <span>Log Out</span>
    </a>
  </div>
</nav>
