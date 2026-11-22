<?php
// timer.php - Core Study Timer & Pomodoro Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$pdo = getDbConnection();

$settings = getUserSettings($userId);
$defaultFocusMin   = (int)($settings['focus_duration_minutes'] ?? 25);
$shortBreakMin     = (int)($settings['short_break_minutes'] ?? 5);
$longBreakMin      = (int)($settings['long_break_minutes'] ?? 15);
$pomodoroRounds    = (int)($settings['pomodoros_before_long_break'] ?? 4);
$autoStartBreaks   = (bool)($settings['auto_start_breaks'] ?? 1);
$soundEnabled      = (bool)($settings['sound_enabled'] ?? 1);
$timerNotifications= (bool)($settings['timer_notifications'] ?? 1);
$defaultSound      = $settings['selected_sound'] ?? 'rain';
$soundVolume       = max(5, min(100, (int)($settings['sound_volume'] ?? 60)));

// Fetch User's Subjects for the Selector Dropdown
$stmt = $pdo->prepare("SELECT subject_id, subject_name, colour FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$stmt->execute([$userId]);
$userSubjects = $stmt->fetchAll();

// Fetch Pending Tasks for Quick Tasks Drawer
$taskStmt = $pdo->prepare("SELECT task_id, task_name, priority FROM tasks WHERE user_id = ? AND status = 'pending' ORDER BY deadline ASC LIMIT 10");
$taskStmt->execute([$userId]);
$pendingTasks = $taskStmt->fetchAll();

// Random quote
$quoteStmt = $pdo->query("SELECT quote_text, author FROM quotes WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
$quote = $quoteStmt->fetch();
$quoteText = $quote ? $quote['quote_text'] : "The expert in anything was once a beginner.";

$pageTitle = 'Timer — StudyMaze';
$activePage = 'timer';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Exit Focus Mode Button (Strictly visible only when Focus Mode is activated) -->
<button type="button" class="btn-secondary exit-focus-btn" id="exitFocusBtn" hidden style="display: none !important;" aria-hidden="true" aria-label="Exit Focus Mode">
  <i data-lucide="minimize-2" aria-hidden="true"></i>
  <span>Exit Focus Mode</span>
</button>

<main class="main-content" id="mainContent">
  <h1 class="sr-only">Study Timer</h1>
  <div class="timer-container">
    <!-- Timer Mode Selection Tabs -->
    <div class="timer-modes" role="tablist">
      <button type="button" class="mode-pill active" data-mode="timer" role="tab" aria-selected="true">
        <i data-lucide="timer"></i>
        <span>Timer</span>
      </button>
      <button type="button" class="mode-pill" data-mode="clock" role="tab" aria-selected="false">
        <i data-lucide="clock"></i>
        <span>Clock</span>
      </button>
      <button type="button" class="mode-pill" data-mode="pomodoro" role="tab" aria-selected="false">
        <i data-lucide="target"></i>
        <span>Pomodoro</span>
      </button>
      <button type="button" class="mode-pill" data-mode="stopwatch" role="tab" aria-selected="false">
        <i data-lucide="watch"></i>
        <span>Stopwatch</span>
      </button>
    </div>

    <!-- Pomodoro Round & State Indicator (Pomodoro Mode Only) -->
    <div class="pomodoro-badge" id="pomodoroBadge" style="display: none;">
      <i data-lucide="target" style="width: 16px; height: 16px;"></i>
      <span id="pomodoroStateText">Focus Session — Round 1</span>
      <div class="pomodoro-dots" id="pomodoroDots">
        <span class="p-dot filled"></span>
        <span class="p-dot"></span>
        <span class="p-dot"></span>
        <span class="p-dot"></span>
      </div>
    </div>

    <!-- Circular Timer Display -->
    <div class="timer-dial-wrapper">
      <svg class="timer-svg" viewBox="0 0 320 320">
        <circle class="timer-circle-bg" cx="160" cy="160" r="145"></circle>
        <circle class="timer-circle-progress" id="timerProgressCircle" cx="160" cy="160" r="145" stroke-dasharray="911" stroke-dashoffset="0"></circle>
      </svg>
      <div class="timer-inner-content">
        <div class="timer-label" id="timerTopLabel">SET DURATION</div>
        <div class="timer-digits" id="timerDigits">25:00</div>
      </div>
    </div>

    <!-- Quick Duration Pills -->
    <div class="duration-pills" id="durationPills">
      <button type="button" class="duration-pill" data-minutes="15">15m</button>
      <button type="button" class="duration-pill active" data-minutes="25">25m</button>
      <button type="button" class="duration-pill" data-minutes="45">45m</button>
      <button type="button" class="duration-pill" data-minutes="60">1h</button>
      <button type="button" class="duration-pill" data-minutes="120">2h</button>
      <button type="button" class="duration-pill" id="customDurationBtn" data-minutes="custom">Custom</button>
    </div>

    <!-- Pomodoro Duration Pills (Hidden by default, shown in Pomodoro Mode) -->
    <div class="duration-pills" id="pomodoroDurationPills" style="display: none;">
      <button type="button" class="duration-pill active" data-pom-min="25">25m</button>
      <button type="button" class="duration-pill" data-pom-min="50">50m</button>
      <button type="button" class="duration-pill" id="customPomodoroBtn" data-pom-min="custom">Custom</button>
    </div>

    <!-- Subject Selector -->
    <div style="margin-bottom: 24px;">
      <select class="timer-subject-select" id="timerSubjectSelect" aria-label="Select study subject">
        <option value="">No subject selected</option>
        <?php foreach ($userSubjects as $sub): ?>
          <option value="<?= (int)$sub['subject_id'] ?>" data-color="<?= htmlspecialchars($sub['colour'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Main Timer Control Buttons -->
    <div class="timer-actions">
      <button type="button" class="btn-timer-primary" id="timerPrimaryBtn" title="Start Timer" aria-label="Start Timer">
        <i data-lucide="play" id="timerPrimaryIcon"></i>
      </button>
      <button type="button" class="btn-timer-secondary" id="timerResetBtn" title="Reset Timer" aria-label="Reset Timer">
        <i data-lucide="rotate-ccw"></i>
      </button>
      <button type="button" class="btn-timer-secondary" id="timerStopSaveBtn" title="Stop & Save Session" aria-label="Stop & Save Session" style="display: none;">
        <i data-lucide="square"></i>
      </button>
    </div>

    <!-- Secondary Tools: Sounds, Focus Mode, Tasks -->
    <div class="timer-tools">
      <button type="button" class="tool-btn" id="toggleSoundsBtn" <?= !$soundEnabled ? 'style="display: none !important;" hidden' : '' ?>>
        <i data-lucide="music-2"></i>
        <span>Sounds</span>
      </button>
      <button type="button" class="tool-btn" id="enterFocusBtn">
        <i data-lucide="maximize-2"></i>
        <span>Focus Mode</span>
      </button>
      <button type="button" class="tool-btn" id="toggleTasksBtn">
        <i data-lucide="check-square"></i>
        <span>Tasks</span>
      </button>
    </div>

    <!-- Ambient Sounds Drawer -->
    <div class="sounds-drawer" id="soundsDrawer" <?= !$soundEnabled ? 'style="display: none !important;" hidden' : '' ?>>
      <div class="sounds-header">
        <div class="sounds-title">Ambient Sounds</div>
        <div class="sounds-volume-ctrl">
          <button type="button" class="icon-btn" id="muteSoundBtn" title="Mute/Unmute">
            <i data-lucide="volume-2" id="volumeIcon" style="width: 16px; height: 16px;"></i>
          </button>
          <span>Volume</span>
          <input type="range" min="0" max="100" value="<?= $soundVolume ?>" class="volume-slider" id="soundVolumeSlider" title="Adjust volume" aria-label="Adjust volume">
          <span id="volumeValueLabel"><?= $soundVolume ?>%</span>
        </div>
      </div>
      <div class="sounds-grid">
        <button type="button" class="sound-btn" data-sound="rain">
          <i data-lucide="cloud-rain"></i>
          <span>Rain</span>
        </button>
        <button type="button" class="sound-btn" data-sound="library">
          <i data-lucide="book-open"></i>
          <span>Library</span>
        </button>
        <button type="button" class="sound-btn" data-sound="ocean">
          <i data-lucide="waves"></i>
          <span>Ocean</span>
        </button>
        <button type="button" class="sound-btn" data-sound="forest">
          <i data-lucide="trees"></i>
          <span>Forest</span>
        </button>
      </div>
      <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 10px; text-align: center;">
        Click a sound to play · Click again to stop · Volume capped at 30% for focus
      </p>
    </div>

    <!-- Quick Tasks Drawer (Slide down) -->
    <div class="sounds-drawer" id="tasksDrawer" style="text-align: left;">
      <div class="sounds-header">
        <div class="sounds-title">Active Tasks</div>
        <a href="tasks.php" class="link-accent" aria-label="Manage all study tasks">Manage All →</a>
      </div>
      <div class="task-list" id="quickTaskList">
        <?php if (empty($pendingTasks)): ?>
          <div style="text-align: center; color: var(--text-muted); padding: 12px 0;">
            No pending tasks right now.
          </div>
        <?php else: ?>
          <?php foreach ($pendingTasks as $pt): ?>
            <div class="task-card" style="padding: 10px 14px;" data-id="<?= (int)$pt['task_id'] ?>">
              <div class="task-left">
                <div class="task-checkbox quick-task-check" data-id="<?= (int)$pt['task_id'] ?>" title="Mark completed">
                  <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                </div>
                <span class="task-title" style="font-size: 0.9rem;"><?= htmlspecialchars($pt['task_name'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <span class="priority-badge <?= htmlspecialchars($pt['priority'], ENT_QUOTES, 'UTF-8') ?>">P</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Motivational Quote Container -->
    <div class="quote-container" style="margin-top: 36px; padding: 0 16px;">
      <p id="motivationalQuote" style="font-style: italic; color: var(--text-muted); font-size: 0.92rem;">
        "<?= htmlspecialchars($quoteText, ENT_QUOTES, 'UTF-8') ?>"
      </p>
      <button type="button" class="icon-btn" id="refreshQuoteBtn" title="New Quote" style="margin: 8px auto 0;">
        <i data-lucide="rotate-cw" style="width: 14px; height: 14px;"></i>
      </button>
    </div>
  </div>
</main>

<!-- Injected Configuration Variables for Timer Client -->
<script>
  window.studyMazeConfig = {
    userId:             <?= $userId ?>,
    defaultFocusMin:    <?= $defaultFocusMin ?>,
    shortBreakMin:      <?= $shortBreakMin ?>,
    longBreakMin:       <?= $longBreakMin ?>,
    pomodoroRounds:     <?= $pomodoroRounds ?>,
    autoStartBreaks:    <?= $autoStartBreaks ? 'true' : 'false' ?>,
    soundEnabled:       <?= $soundEnabled ? 'true' : 'false' ?>,
    timerNotifications: <?= $timerNotifications ? 'true' : 'false' ?>,
    defaultSound:       <?= json_encode($defaultSound) ?>,
    soundVolume:        <?= $soundVolume ?>
  };
</script>

<script src="assets/js/timer.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
