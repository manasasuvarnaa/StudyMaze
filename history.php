<?php
// history.php - Study Session History Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$pdo = getDbConnection();

// Fetch subjects for filter
$subStmt = $pdo->prepare("SELECT subject_id, subject_name, colour FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$subStmt->execute([$userId]);
$userSubjects = $subStmt->fetchAll();

// Total study time all-time (excluding breaks)
$totalSecStmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0) FROM study_sessions WHERE user_id = ? AND status = 'completed' AND session_type != 'break'");
$totalSecStmt->execute([$userId]);
$totalStudySeconds = (int)$totalSecStmt->fetchColumn();

function fmtHistSec(int $sec): string {
    $h = (int)floor($sec / 3600);
    $m = (int)round(($sec % 3600) / 60);
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

$pageTitle = 'History — StudyMaze';
$activePage = 'history';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent">
  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Study History</h1>
      <p class="page-subtitle">Review your completed study sessions.</p>
    </div>

    <!-- Total Time Badge -->
    <div class="history-total-card" style="background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 10px 18px; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-sm);">
      <i data-lucide="clock" style="color: var(--accent); width: 20px; height: 20px;"></i>
      <div>
        <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); line-height: 1;" id="historyTotalBadge">
          <?= fmtHistSec($totalStudySeconds) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted);">Total study time</div>
      </div>
    </div>
  </div>

  <!-- Filter Controls -->
  <div class="history-filters">
    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.88rem; font-weight: 600; color: var(--text-secondary);">
      <i data-lucide="filter" style="width: 16px; height: 16px;"></i>
      <span>Filters:</span>
    </div>

    <select class="filter-select" id="filterSubject" aria-label="Filter by subject">
      <option value="">All subjects</option>
      <?php foreach ($userSubjects as $sub): ?>
        <option value="<?= (int)$sub['subject_id'] ?>">
          <?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select class="filter-select" id="filterType" aria-label="Filter by session type">
      <option value="">All types</option>
      <option value="focus">Focus</option>
      <option value="pomodoro">Pomodoro</option>
      <option value="stopwatch">Stopwatch</option>
      <option value="break">Short Break</option>
    </select>
  </div>

  <!-- History List Grouped by Date -->
  <div id="historyGroupsContainer">
    <!-- Populated dynamically by JavaScript -->
    <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
      Loading study session history...
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('historyGroupsContainer');
  const subjectFilter = document.getElementById('filterSubject');
  const typeFilter = document.getElementById('filterType');
  const totalBadge = document.getElementById('historyTotalBadge');

  async function loadHistory() {
    const sub = subjectFilter.value;
    const type = typeFilter.value;

    let url = 'api/sessions.php?action=history';
    if (sub) url += `&subject_id=${sub}`;
    if (type) url += `&session_type=${type}`;

    try {
      const res = await fetch(url);
      const data = await res.json();
      if (!data.success) {
        container.innerHTML = '<div style="color: var(--danger); text-align: center; padding: 20px;">Failed to load history.</div>';
        return;
      }

      if (data.total_study_seconds !== undefined) {
        totalBadge.textContent = window.formatSeconds(data.total_study_seconds);
      }

      renderSessions(data.sessions);
    } catch (err) {
      console.error('History load error:', err);
      container.innerHTML = '<div style="color: var(--danger); text-align: center; padding: 20px;">Server error loading history.</div>';
    }
  }

  function renderSessions(sessions) {
    container.innerHTML = '';

    if (!sessions || sessions.length === 0) {
      container.innerHTML = `
        <div class="card" style="text-align: center; padding: 40px; color: var(--text-muted); margin-top: 16px;">
          No completed study sessions match your filter criteria.
        </div>
      `;
      return;
    }

    // Group sessions by date
    const groups = {};
    sessions.forEach((ses) => {
      const d = new Date(ses.started_at);
      const dateKey = d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }).toUpperCase();
      if (!groups[dateKey]) {
        groups[dateKey] = {
          label: dateKey,
          totalSec: 0,
          items: []
        };
      }
      if (ses.session_type !== 'break') {
        groups[dateKey].totalSec += parseInt(ses.duration_seconds, 10);
      }
      groups[dateKey].items.push(ses);
    });

    Object.values(groups).forEach((grp) => {
      const groupEl = document.createElement('div');

      const header = document.createElement('div');
      header.className = 'history-group-header';
      header.innerHTML = `
        <span>${grp.label}</span>
        <span style="font-weight: 600; color: var(--text-muted);">${window.formatSeconds(grp.totalSec)}</span>
      `;
      groupEl.appendChild(header);

      grp.items.forEach((item) => {
        const itemEl = document.createElement('div');
        itemEl.className = 'history-item';

        const startTime = new Date(item.started_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        const typeLabel = item.session_type === 'break' ? 'Short Break' : (item.session_type.charAt(0).toUpperCase() + item.session_type.slice(1));
        const color = item.subject_colour || 'var(--accent)';

        itemEl.innerHTML = `
          <div class="history-item-left">
            <span class="subject-dot" style="background-color: ${color};"></span>
            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-primary);">${item.subject_name || 'General Study'}</span>
          </div>
          <div class="history-item-right">
            <span class="history-tag">${typeLabel}</span>
            <span style="font-weight: 600; font-size: 0.92rem; color: var(--text-primary); min-width: 65px; text-align: right;">
              ${window.formatSeconds(parseInt(item.duration_seconds, 10))}
            </span>
            <span style="font-size: 0.85rem; color: var(--text-muted); min-width: 80px; text-align: right;">
              ${startTime}
            </span>
          </div>
        `;
        groupEl.appendChild(itemEl);
      });

      container.appendChild(groupEl);
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  subjectFilter.addEventListener('change', loadHistory);
  typeFilter.addEventListener('change', loadHistory);

  loadHistory();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
