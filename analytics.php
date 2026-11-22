<?php
// analytics.php - Real Database Study Analytics & Insights
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();

$pageTitle = 'Analytics — StudyMaze';
$activePage = 'analytics';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent">
  <!-- Analytics Page Header with Timeframe Filter Pills -->
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Study Analytics</h1>
      <p class="page-subtitle">Track your productivity and study patterns.</p>
    </div>

    <!-- Timeframe Selection Pills -->
    <div class="timer-modes" id="analyticsFilterPills" style="margin-bottom: 0;">
      <button type="button" class="mode-pill" data-tf="daily">Daily</button>
      <button type="button" class="mode-pill active" data-tf="weekly">Weekly</button>
      <button type="button" class="mode-pill" data-tf="monthly">Monthly</button>
      <button type="button" class="mode-pill" data-tf="yearly">Yearly</button>
      <button type="button" class="mode-pill" data-tf="custom" id="customFilterBtn">Custom</button>
    </div>
  </div>

  <!-- Custom Date Modal -->
  <div class="modal-backdrop" id="customDateModal">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">Select Date Range</h3>
        <button type="button" class="icon-btn" id="closeCustomModalBtn">&times;</button>
      </div>
      <div class="form-group">
        <label class="form-label" for="customStartDate">Start Date</label>
        <input type="date" id="customStartDate" class="form-input" value="<?= date('Y-m-01') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="customEndDate">End Date</label>
        <input type="date" id="customEndDate" class="form-input" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="cancelCustomModalBtn">Cancel</button>
        <button type="button" class="btn-primary" id="applyCustomDateBtn">Apply Filter</button>
      </div>
    </div>
  </div>

  <!-- 4 Summary Metrics Cards -->
  <div class="metrics-grid">
    <!-- 1. Total Study Time -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="clock"></i>
        <span>Total study time</span>
      </div>
      <div class="metric-value" id="metricTotalTime">0.0h</div>
      <div class="metric-subtext" id="metricSessionsCount">0 sessions completed</div>
    </div>

    <!-- 2. Average Daily -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="trending-up"></i>
        <span>Average daily</span>
      </div>
      <div class="metric-value" id="metricAvgDaily">0.0h</div>
      <div class="metric-subtext">per session average</div>
    </div>

    <!-- 3. Longest Session -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="award"></i>
        <span>Longest session</span>
      </div>
      <div class="metric-value" id="metricLongest">0.0h</div>
      <div class="metric-subtext">single focus block</div>
    </div>

    <!-- 4. Current Streak -->
    <div class="metric-card">
      <div class="metric-header">
        <i data-lucide="flame"></i>
        <span>Current streak</span>
      </div>
      <div class="metric-value" id="metricStreak">0 days</div>
      <div class="metric-subtext">consistent habit</div>
    </div>
  </div>

  <!-- Charts Row 1: Study Time Bar Chart & By Subject -->
  <div class="dashboard-row" style="margin-bottom: 24px;">
    <!-- Study Time Chart -->
    <div class="card">
      <h2 class="card-title">
        <span id="chartPeriodTitle">Study Time — This Week</span>
      </h2>
      <div style="position: relative; height: 260px; margin-top: 18px;">
        <canvas id="analyticsMainChart"></canvas>
      </div>
    </div>

    <!-- By Subject Progress Bars -->
    <div class="card">
      <h2 class="card-title">
        <span>By Subject</span>
      </h2>
      <div id="subjectBreakdownContainer" style="display: flex; flex-direction: column; gap: 16px; margin-top: 20px;">
        <!-- Rendered dynamically -->
      </div>
    </div>
  </div>

  <!-- Charts Row 2: Study Activity Heatmap & Highlights -->
  <div class="dashboard-row">
    <!-- Study Activity Heatmap -->
    <div class="card">
      <h2 class="card-title">
        <span>Study Activity</span>
      </h2>
      <p style="font-size: 0.82rem; color: var(--text-muted); margin: 6px 0 16px;">
        Daily focus intensity over the past 4 weeks
      </p>
      <div id="activityGrid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;">
        <!-- Filled dynamically -->
      </div>
      <div style="display: flex; align-items: center; justify-content: flex-end; gap: 6px; margin-top: 14px; font-size: 0.75rem; color: var(--text-muted);" id="heatmapLegend">
        <span>Less</span>
        <span style="width: 12px; height: 12px; border-radius: 2px; background: var(--bg-card-subtle);"></span>
        <span id="legendLvl1" style="width: 12px; height: 12px; border-radius: 2px; background: var(--accent-light);"></span>
        <span id="legendLvl2" style="width: 12px; height: 12px; border-radius: 2px; background: var(--accent-glow);"></span>
        <span id="legendLvl3" style="width: 12px; height: 12px; border-radius: 2px; background: var(--accent);"></span>
        <span>More</span>
      </div>
    </div>

    <!-- Highlights Card -->
    <div class="card">
      <h2 class="card-title">
        <span>Highlights</span>
      </h2>
      <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
          <span style="font-size: 0.88rem; color: var(--text-secondary);">Most studied subject</span>
          <div style="text-align: right;">
            <div style="font-weight: 700; color: var(--text-primary);" id="hlMostSubject">Mathematics</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);" id="hlMostSubjectHours">18.5h total</div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
          <span style="font-size: 0.88rem; color: var(--text-secondary);">Pomodoros completed</span>
          <div style="text-align: right;">
            <div style="font-weight: 700; color: var(--text-primary);" id="hlPomodoros">148</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">this month</div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
          <span style="font-size: 0.88rem; color: var(--text-secondary);">Longest streak</span>
          <div style="text-align: right;">
            <div style="font-weight: 700; color: var(--text-primary);" id="hlStreak">14 days</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">all-time record</div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
          <span style="font-size: 0.88rem; color: var(--text-secondary);">Most productive day</span>
          <div style="text-align: right;">
            <div style="font-weight: 700; color: var(--text-primary);" id="hlProductiveDay">Saturday</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);" id="hlProductiveAvg">avg 6.2h</div>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: baseline;">
          <span style="font-size: 0.88rem; color: var(--text-secondary);">Sessions this month</span>
          <div style="text-align: right;">
            <div style="font-weight: 700; color: var(--text-primary);" id="hlMonthSessions">62</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">consistent study habit</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Chart.js -->
<script src="assets/js/chart.min.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  let mainChartInstance = null;
  let currentTimeframe = 'weekly';
  let customStart = null;
  let customEnd = null;

  const filterPills = document.querySelectorAll('#analyticsFilterPills .mode-pill');
  const customModal = document.getElementById('customDateModal');
  const customFilterBtn = document.getElementById('customFilterBtn');
  const closeCustomModalBtn = document.getElementById('closeCustomModalBtn');
  const cancelCustomModalBtn = document.getElementById('cancelCustomModalBtn');
  const applyCustomDateBtn = document.getElementById('applyCustomDateBtn');

  async function loadAnalytics(tf, start = null, end = null) {
    let url = `api/analytics.php?timeframe=${tf}`;
    if (tf === 'custom' && start && end) {
      url += `&start_date=${start}&end_date=${end}`;
    }

    try {
      const res = await fetch(url);
      const data = await res.json();
      if (!data.success) {
        console.error('Analytics load error:', data.error);
        return;
      }

      renderSummary(data.summary);
      renderChart(data.chart, tf);
      renderSubjects(data.by_subject);
      renderActivity(data.activity_grid);
      renderHighlights(data.highlights);
    } catch (err) {
      console.error('Failed to load analytics:', err);
    }
  }

  function renderSummary(summary) {
    if (!summary) return;
    document.getElementById('metricTotalTime').textContent = `${summary.total_hours}h`;
    document.getElementById('metricSessionsCount').textContent = `${summary.completed_sessions} sessions completed`;
    document.getElementById('metricAvgDaily').textContent = `${summary.average_hours}h`;
    document.getElementById('metricLongest').textContent = `${summary.longest_session_hours}h`;
    document.getElementById('metricStreak').textContent = `${summary.current_streak_days} days`;
  }

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

  function renderChart(chartData, tf) {
    if (!chartData) return;

    const titles = {
      daily: 'Study Time — Today (Hours)',
      weekly: 'Study Time — This Week',
      monthly: 'Study Time — This Month',
      yearly: 'Study Time — This Year',
      custom: 'Study Time — Custom Range'
    };
    document.getElementById('chartPeriodTitle').textContent = titles[tf] || 'Study Time';

    const ctx = document.getElementById('analyticsMainChart').getContext('2d');
    const [r, g, b] = getThemeRgb();
    const accentColor = `rgb(${r}, ${g}, ${b})`;
    const textMuted = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#9ca3af';
    const isDark = document.documentElement.getAttribute('data-mode') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';

    const bgColors = chartData.labels.map((_, i) => {
      const val = parseFloat(chartData.values[i]) || 0;
      if (i === chartData.active_index) {
        return accentColor;
      }
      if (val > 0) {
        return `rgba(${r}, ${g}, ${b}, 0.55)`;
      }
      return `rgba(${r}, ${g}, ${b}, 0.14)`;
    });

    if (mainChartInstance) {
      mainChartInstance.destroy();
    }

    mainChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: chartData.labels,
        datasets: [{
          data: chartData.values,
          backgroundColor: bgColors,
          hoverBackgroundColor: accentColor,
          borderRadius: 6,
          borderSkipped: false,
          barThickness: 28
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
            grid: { color: gridColor },
            ticks: {
              color: textMuted,
              callback: (val) => `${val}h`
            },
            beginAtZero: true
          }
        }
      }
    });
  }

  function renderSubjects(subjects) {
    const container = document.getElementById('subjectBreakdownContainer');
    container.innerHTML = '';

    if (!subjects || subjects.length === 0) {
      container.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 20px;">No subject data for this timeframe.</div>';
      return;
    }

    const [r, g, b] = getThemeRgb();
    const themeAccent = `rgb(${r}, ${g}, ${b})`;

    subjects.forEach((sub) => {
      const subColor = (sub.colour && sub.colour !== '#9ca3af' && sub.colour !== '#ea580c')
        ? sub.colour
        : themeAccent;

      const row = document.createElement('div');
      row.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.88rem;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <span class="subject-dot" style="background-color: ${subColor};"></span>
            <span style="font-weight: 600; color: var(--text-primary);">${sub.subject_name}</span>
          </div>
          <span style="font-weight: 600; color: var(--text-muted);">${sub.hours}h</span>
        </div>
        <div class="progress-track" style="height: 6px;">
          <div class="progress-fill" style="width: ${sub.percentage}%; background-color: ${subColor};"></div>
        </div>
      `;
      container.appendChild(row);
    });
  }

  function renderActivity(grid) {
    const container = document.getElementById('activityGrid');
    container.innerHTML = '';
    if (!grid) return;

    const [r, g, b] = getThemeRgb();
    const themeAccent = `rgb(${r}, ${g}, ${b})`;

    // Synchronize legend swatches to theme colors
    const lvl1 = document.getElementById('legendLvl1');
    const lvl2 = document.getElementById('legendLvl2');
    const lvl3 = document.getElementById('legendLvl3');
    if (lvl1) lvl1.style.background = `rgba(${r}, ${g}, ${b}, 0.25)`;
    if (lvl2) lvl2.style.background = `rgba(${r}, ${g}, ${b}, 0.60)`;
    if (lvl3) lvl3.style.background = themeAccent;

    // Create or reuse the floating activity tooltip (matches chart.js style)
    let tooltip = document.getElementById('activityGridTooltip');
    if (!tooltip) {
      tooltip = document.createElement('div');
      tooltip.id = 'activityGridTooltip';
      tooltip.style.cssText = `
        position: fixed;
        pointer-events: none;
        z-index: 10000;
        background: #1f2937;
        color: #ffffff;
        padding: 6px 10px;
        border-radius: 6px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
        font-family: inherit;
        display: none;
        opacity: 0;
        transition: opacity 0.12s ease;
        white-space: nowrap;
      `;
      document.body.appendChild(tooltip);

      window.addEventListener('scroll', () => {
        tooltip.style.display = 'none';
        tooltip.style.opacity = '0';
      }, { passive: true });
    }

    grid.forEach((day) => {
      const square = document.createElement('div');
      square.setAttribute('tabindex', '0');
      square.setAttribute('role', 'img');

      let blockColor;
      let swatchColor;
      if (day.level === 0) {
        blockColor = 'var(--bg-card-subtle)';
        swatchColor = 'var(--text-muted, #9ca3af)';
      } else if (day.level === 1) {
        blockColor = `rgba(${r}, ${g}, ${b}, 0.25)`;
        swatchColor = blockColor;
      } else if (day.level === 2) {
        blockColor = `rgba(${r}, ${g}, ${b}, 0.50)`;
        swatchColor = blockColor;
      } else if (day.level === 3) {
        blockColor = `rgba(${r}, ${g}, ${b}, 0.75)`;
        swatchColor = blockColor;
      } else {
        blockColor = themeAccent;
        swatchColor = blockColor;
      }

      square.style.cssText = `
        height: 28px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: transform 0.15s ease;
        background-color: ${blockColor};
      `;

      // Format date for tooltip and accessibility: e.g. "Wed, Oct 28"
      const [y, m, d] = day.date.split('-').map(Number);
      const dateObj = new Date(y, m - 1, d);
      const dateStr = dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
      square.setAttribute('aria-label', `${dateStr}: ${day.hours}h study time`);

      function showTooltip() {
        tooltip.innerHTML = `
          <div style="font-weight: 700; font-size: 0.82rem; margin-bottom: 3px; color: #ffffff; line-height: 1.2;">${dateStr}</div>
          <div style="display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: #f3f4f6; line-height: 1.2;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background: ${swatchColor}; border: 1px solid rgba(255,255,255,0.25); flex-shrink: 0;"></span>
            <span>${day.hours}h study time</span>
          </div>
        `;
        tooltip.style.display = 'block';
        tooltip.style.opacity = '1';

        const rect = square.getBoundingClientRect();
        const ttRect = tooltip.getBoundingClientRect();

        let left = rect.left + rect.width / 2;
        let top = rect.top - 8;

        // Viewport bounds handling
        if (left - ttRect.width / 2 < 8) {
          left = 8 + ttRect.width / 2;
        } else if (left + ttRect.width / 2 > window.innerWidth - 8) {
          left = window.innerWidth - 8 - ttRect.width / 2;
        }

        if (top - ttRect.height < 8) {
          top = rect.bottom + 8;
          tooltip.style.transform = 'translate(-50%, 0)';
        } else {
          tooltip.style.transform = 'translate(-50%, -100%)';
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
      }

      function hideTooltip() {
        tooltip.style.opacity = '0';
        tooltip.style.display = 'none';
      }

      square.addEventListener('mouseenter', () => {
        square.style.transform = 'scale(1.15)';
        showTooltip();
      });
      square.addEventListener('mouseleave', () => {
        square.style.transform = 'scale(1)';
        hideTooltip();
      });
      square.addEventListener('focus', () => {
        square.style.transform = 'scale(1.15)';
        showTooltip();
      });
      square.addEventListener('blur', () => {
        square.style.transform = 'scale(1)';
        hideTooltip();
      });

      container.appendChild(square);
    });
  }

  function renderHighlights(hl) {
    if (!hl) return;
    document.getElementById('hlMostSubject').textContent = hl.most_studied_subject;
    document.getElementById('hlMostSubjectHours').textContent = `${hl.most_studied_hours}h total`;
    document.getElementById('hlPomodoros').textContent = hl.pomodoros_completed;
    document.getElementById('hlStreak').textContent = `${hl.longest_streak} days`;
    document.getElementById('hlProductiveDay').textContent = hl.most_productive_day;
    document.getElementById('hlProductiveAvg').textContent = `avg ${hl.most_productive_avg}h`;
    document.getElementById('hlMonthSessions').textContent = hl.sessions_this_month;
  }

  // Filter Pill Listeners
  filterPills.forEach((pill) => {
    pill.addEventListener('click', () => {
      const tf = pill.dataset.tf;
      if (tf === 'custom') {
        customModal.classList.add('show');
        return;
      }

      filterPills.forEach((p) => p.classList.remove('active'));
      pill.classList.add('active');
      currentTimeframe = tf;
      loadAnalytics(tf);
    });
  });

  // Custom Modal Handlers
  function hideCustomModal() {
    customModal.classList.remove('show');
  }

  closeCustomModalBtn.addEventListener('click', hideCustomModal);
  cancelCustomModalBtn.addEventListener('click', hideCustomModal);
  customModal.addEventListener('click', (e) => { if (e.target === customModal) hideCustomModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && customModal.classList.contains('show')) {
      hideCustomModal();
    }
  });

  applyCustomDateBtn.addEventListener('click', () => {
    const start = document.getElementById('customStartDate').value;
    const end = document.getElementById('customEndDate').value;
    if (!start || !end) {
      window.showToast('Please select both start and end dates.', 'error');
      return;
    }
    filterPills.forEach((p) => p.classList.remove('active'));
    customFilterBtn.classList.add('active');
    currentTimeframe = 'custom';
    hideCustomModal();
    loadAnalytics('custom', start, end);
  });

  // Initial Load
  loadAnalytics('weekly');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
