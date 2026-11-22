<?php
// dashboard.php - Main Dashboard
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$pdo = getDbConnection();

// Fetch User Settings
$settings = getUserSettings($userId);
$dailyGoalMinutes = (int)($settings['daily_goal_minutes'] ?? 180);

// Today's Study Time (focus, pomodoro, stopwatch; exclude break)
$todayStmt = $pdo->prepare("
    SELECT COALESCE(SUM(duration_seconds), 0)
    FROM study_sessions
    WHERE user_id = ? 
      AND status = 'completed' 
      AND session_type != 'break'
      AND DATE(started_at) = CURDATE()
");
$todayStmt->execute([$userId]);
$todaySeconds = (int)$todayStmt->fetchColumn();
$todayMinutes = (int)round($todaySeconds / 60);

// Pomodoros Today
$pomStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM study_sessions
    WHERE user_id = ? 
      AND status = 'completed' 
      AND session_type = 'pomodoro'
      AND DATE(started_at) = CURDATE()
");
$pomStmt->execute([$userId]);
$pomodorosToday = (int)$pomStmt->fetchColumn();

// Study Streak
$streakDays = calculateUserStreak($pdo, $userId);

// This Week's Total Study Time
$weekStmt = $pdo->prepare("
    SELECT COALESCE(SUM(duration_seconds), 0)
    FROM study_sessions
    WHERE user_id = ? 
      AND status = 'completed' 
      AND session_type != 'break'
      AND started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
");
$weekStmt->execute([$userId]);
$weekSeconds = (int)$weekStmt->fetchColumn();

// Calculate best day this week dynamically
$bestDayStmt = $pdo->prepare("
    SELECT DAYNAME(started_at) as day_name, SUM(duration_seconds) as total_sec
    FROM study_sessions
    WHERE user_id = ? 
      AND status = 'completed' 
      AND session_type != 'break'
      AND started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(started_at), DAYNAME(started_at)
    ORDER BY total_sec DESC
    LIMIT 1
");
$bestDayStmt->execute([$userId]);
$bestDayRow = $bestDayStmt->fetch();
$bestDayName = $bestDayRow ? $bestDayRow['day_name'] : 'None yet';

// Daily Goal Calculation
$goalPercent = min(100, $dailyGoalMinutes > 0 ? (int)round(($todayMinutes / $dailyGoalMinutes) * 100) : 0);
$remainingMinutes = max(0, $dailyGoalMinutes - $todayMinutes);

// Format helper
function fmtHoursMins(int $seconds): string {
    $h = (int)floor($seconds / 3600);
    $m = (int)round(($seconds % 3600) / 60);
    if ($h > 0) {
        return "{$h}h {$m}m";
    }
    return "{$m}m";
}

// Fetch Random Quote
$quoteStmt = $pdo->query("SELECT quote_text, author FROM quotes WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
$quote = $quoteStmt->fetch();
$quoteText = $quote ? $quote['quote_text'] : "An investment in knowledge pays the best interest.";

// Recent 5 Sessions
$recentStmt = $pdo->prepare("
    SELECT 
        ss.session_id,
        ss.session_label,
        ss.session_type,
        ss.duration_seconds,
        ss.started_at,
        COALESCE(s.subject_name, 'General Study') AS subject_name,
        COALESCE(s.colour, '#ea580c') AS subject_colour
    FROM study_sessions ss
    LEFT JOIN subjects s ON ss.subject_id = s.subject_id
    WHERE ss.user_id = ? AND ss.status = 'completed' AND ss.session_type != 'break'
    ORDER BY ss.started_at DESC
    LIMIT 4
");
$recentStmt->execute([$userId]);
$recentSessions = $recentStmt->fetchAll();

// User Subjects
$subStmt = $pdo->prepare("
    SELECT 
        s.subject_id,
        s.subject_name,
        s.colour,
        COALESCE(SUM(CASE WHEN ss.status = 'completed' AND ss.session_type != 'break' THEN ss.duration_seconds ELSE 0 END), 0) AS total_seconds,
        COUNT(CASE WHEN ss.status = 'completed' AND ss.session_type != 'break' THEN 1 ELSE NULL END) AS session_count
    FROM subjects s
    LEFT JOIN study_sessions ss ON s.subject_id = ss.subject_id
    WHERE s.user_id = ?
    GROUP BY s.subject_id
    ORDER BY total_seconds DESC
    LIMIT 4
");
$subStmt->execute([$userId]);
$subjects = $subStmt->fetchAll();

// Dynamic Greeting based on time of day
$hour = (int)date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$pageTitle = 'Dashboard — StudyMaze';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent">
  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-left">
      <div class="date-badge"><?= date('l, F j') ?></div>
      <h1 class="page-title"><?= $greeting ?> </h1>
      <p class="quote-banner-text">"<?= htmlspecialchars($quoteText, ENT_QUOTES, 'UTF-8') ?>"</p>
    </div>
  </div>

  <!-- Ready to Study Banner -->
  <div class="ready-banner">
    <div>
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">Ready to study?</h2>
      <p>Start a focused session and track your progress.</p>
    </div>
    <a href="timer.php" class="btn-primary" style="padding: 10px 22px;">
      <i data-lucide="play" style="width: 18px; height: 18px; fill: currentColor;"></i>
      <span>Start Session</span>
    </a>
  </div>

  <!-- 4 Metrics Grid -->
  <div class="metrics-grid">
    <!-- 1. Today's Study Time -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="clock"></i>
        <span>Today's study time</span>
      </div>
      <div class="metric-value"><?= fmtHoursMins($todaySeconds) ?></div>
      <div class="metric-subtext"><?= $goalPercent ?>% of daily goal</div>
    </div>

    <!-- 2. Pomodoros Today -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="target"></i>
        <span>Pomodoros today</span>
      </div>
      <div class="metric-value"><?= $pomodorosToday ?></div>
      <div class="metric-subtext">sessions completed</div>
    </div>

    <!-- 3. Study Streak -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="flame"></i>
        <span>Study streak</span>
      </div>
      <div class="metric-value"><?= $streakDays ?> <?= $streakDays === 1 ? 'day' : 'days' ?></div>
      <div class="metric-subtext"><?= $streakDays > 0 ? 'keep it going!' : 'start today!' ?></div>
    </div>

    <!-- 4. This Week -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="trending-up"></i>
        <span>This week</span>
      </div>
      <div class="metric-value"><?= fmtHoursMins($weekSeconds) ?></div>
      <div class="metric-subtext">past 7 days active</div>
    </div>
  </div>

  <!-- Daily Goal Card -->
  <div class="goal-card">
    <div class="goal-info">
      <span>Daily Goal <span style="font-weight: 500; color: var(--text-secondary);"><?= fmtHoursMins($todaySeconds) ?> / <?= fmtHoursMins($dailyGoalMinutes * 60) ?></span></span>
      <span class="goal-percentage"><?= $goalPercent ?>%</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill" style="width: <?= $goalPercent ?>%;"></div>
    </div>
    <div class="goal-subtext">
      <?php if ($remainingMinutes > 0): ?>
        <?= fmtHoursMins($remainingMinutes * 60) ?> remaining to hit your goal
      <?php else: ?>
        🎉 Daily goal achieved! Awesome focus today.
      <?php endif; ?>
    </div>
  </div>

  <!-- 2-Column Section: This Week Chart & Recent Sessions -->
  <div class="dashboard-row">
    <!-- Left: This Week Activity -->
    <div class="card">
      <h2 class="card-title">
        <span>This Week</span>
        <a href="analytics.php" class="link-accent" aria-label="View detailed weekly study analytics">View analytics →</a>
      </h2>
      <div style="position: relative; height: 180px; margin-top: 14px;">
        <canvas id="dashboardWeekChart"></canvas>
      </div>
      <div id="chartFooterStats" style="display: flex; gap: 24px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-secondary);">
        <div>Total: <strong id="statTotalHours" style="color: var(--text-primary);"><?= round($weekSeconds / 3600, 1) ?>h</strong></div>
        <div>Average: <strong id="statAvgHours" style="color: var(--text-primary);"><?= round(($weekSeconds / 7) / 3600, 1) ?>h</strong></div>
        <div>Best day: <strong id="statBestDay" style="color: var(--text-primary);"><?= htmlspecialchars($bestDayName, ENT_QUOTES, 'UTF-8') ?></strong></div>
      </div>
    </div>

    <!-- Right: Recent Sessions -->
    <div class="card">
      <h2 class="card-title">
        <span>Recent Sessions</span>
        <a href="history.php" class="link-accent" aria-label="View all study sessions in history">View all →</a>
      </h2>
      <div class="session-list">
        <?php if (empty($recentSessions)): ?>
          <div style="text-align: center; padding: 24px 0; color: var(--text-muted);">
            No study sessions recorded yet. Start your first session!
          </div>
        <?php else: ?>
          <?php foreach ($recentSessions as $ses): ?>
            <div class="session-item">
              <div class="session-item-left">
                <span class="subject-dot" style="background-color: <?= htmlspecialchars($ses['subject_colour'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                <div>
                  <div class="session-title"><?= htmlspecialchars($ses['subject_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="session-time"><?= date('M j, g:i A', strtotime($ses['started_at'])) ?></div>
                </div>
              </div>
              <div class="session-duration"><?= fmtHoursMins((int)$ses['duration_seconds']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Your Subjects Section -->
  <div>
    <h2 class="card-title" style="margin-bottom: 4px;">
      <span style="font-size: 1.25rem;">Your Subjects</span>
      <a href="subjects.php" class="link-accent" aria-label="Manage your study subjects">Manage →</a>
    </h2>
    <div class="subjects-grid">
      <?php if (empty($subjects)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 28px;">
          No subjects created yet. Click Manage to add subjects!
        </div>
      <?php else: ?>
        <?php foreach ($subjects as $sub): ?>
          <div class="subject-card">
            <div class="subject-card-header">
              <div class="subject-card-title">
                <span class="subject-dot" style="background-color: <?= htmlspecialchars($sub['colour'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                <span><?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
            <div class="subject-card-stats">
              <div class="subject-stat-hours"><?= fmtHoursMins((int)$sub['total_seconds']) ?></div>
              <div class="subject-stat-sessions"><?= (int)$sub['session_count'] ?> sessions</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Chart.js for Dashboard Weekly Chart -->
<script src="assets/js/chart.min.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const res = await fetch('api/analytics.php?timeframe=weekly');
    const data = await res.json();
    if (data.success && data.chart) {
      const ctx = document.getElementById('dashboardWeekChart').getContext('2d');
      function getThemeRgb() {
        const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#ea580c';
        if (accent.startsWith('#')) {
          let hex = accent.slice(1);
          if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
          const num = parseInt(hex, 16);
          return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
        }
        const match = accent.match(/\d+/g);
        if (match && match.length >= 3) {
          return [parseInt(match[0], 10), parseInt(match[1], 10), parseInt(match[2], 10)];
        }
        return [234, 88, 12];
      }

      const [r, g, b] = getThemeRgb();
      const accent = `rgb(${r}, ${g}, ${b})`;
      const textMuted = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#9ca3af';

      const bgColors = data.chart.labels.map((_, i) => {
        const val = parseFloat(data.chart.values[i]) || 0;
        if (i === data.chart.active_index) {
          return accent;
        }
        if (val > 0) {
          return `rgba(${r}, ${g}, ${b}, 0.55)`;
        }
        return `rgba(${r}, ${g}, ${b}, 0.15)`;
      });

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.chart.labels,
          datasets: [{
            data: data.chart.values,
            backgroundColor: bgColors,
            hoverBackgroundColor: accent,
            borderRadius: 6,
            borderSkipped: false,
            barThickness: 24
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => ` ${ctx.raw}h study time`
              }
            }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: textMuted, font: { size: 12 } }
            },
            y: {
              display: false,
              beginAtZero: true
            }
          }
        }
      });

      if (data.highlights) {
        if (data.highlights.most_productive_day) {
          document.getElementById('statBestDay').textContent = data.highlights.most_productive_day;
        }
      }
    }
  } catch (e) {
    console.error('Failed to load dashboard chart:', e);
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
