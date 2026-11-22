<?php
// themes.php - Theme Selection & Instant Customization Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$settings = getUserSettings($userId);
$currentTheme = $settings['theme_name'] ?? 'default';
$currentMode = $settings['appearance'] ?? 'light';

$themesList = [
    [
        'key' => 'default',
        'title' => 'Midnight Orange',
        'subtitle' => 'Premium, minimal, elegant',
        'accent' => '#ea580c',
        'swatches' => ['#111827', '#ea580c', '#ffffff', '#f3f4f6'],
        'lightBg' => '#f8f9fa',
        'lightText' => '#111827',
        'darkBg' => '#0c0e14',
        'darkText' => '#f9fafb'
    ],
    [
        'key' => 'ghibli',
        'title' => 'Studio Ghibli',
        'subtitle' => 'Soft, cozy, nature-inspired',
        'accent' => '#4a7c59',
        'swatches' => ['#2d3732', '#4a7c59', '#ede6d8', '#f7f4ec'],
        'lightBg' => '#f7f4ec',
        'lightText' => '#2d3732',
        'darkBg' => '#151d18',
        'darkText' => '#eef5f0'
    ],
    [
        'key' => 'crimson',
        'title' => 'Crimson Night',
        'subtitle' => 'Bold, passionate, intense',
        'accent' => '#dc2626',
        'swatches' => ['#1f1414', '#dc2626', '#f8ecec', '#ffffff'],
        'lightBg' => '#faf5f5',
        'lightText' => '#1f1414',
        'darkBg' => '#150f11',
        'darkText' => '#fdf2f4'
    ],
    [
        'key' => 'forest',
        'title' => 'Forest Study',
        'subtitle' => 'Calm, earthy, grounded',
        'accent' => '#15803d',
        'swatches' => ['#17241b', '#15803d', '#e4eee7', '#ffffff'],
        'lightBg' => '#f1f6f3',
        'lightText' => '#17241b',
        'darkBg' => '#0d1510',
        'darkText' => '#edf7ef'
    ],
    [
        'key' => 'midnight',
        'title' => 'Lavender Dream',
        'subtitle' => 'Dreamy, ethereal, soft',
        'accent' => '#7c3aed',
        'swatches' => ['#1a1744', '#7c3aed', '#eae7f6', '#ffffff'],
        'lightBg' => '#f5f4fb',
        'lightText' => '#1a1744',
        'darkBg' => '#0d0f1f',
        'darkText' => '#f5f3ff'
    ]
];

$pageTitle = 'Themes — StudyMaze';
$activePage = 'themes';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent">
  <!-- Page Header with Light/Dark Mode Switcher -->
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Themes</h1>
      <p class="page-subtitle">Personalize your study environment. Changes apply instantly.</p>
    </div>

    <!-- Appearance Mode Toggle Pills -->
    <div class="timer-modes" style="margin-bottom: 0;">
      <button type="button" class="mode-pill <?= $currentMode === 'light' ? 'active' : '' ?>" id="themeLightBtn">
        <i data-lucide="sun"></i> Light
      </button>
      <button type="button" class="mode-pill <?= $currentMode === 'dark' ? 'active' : '' ?>" id="themeDarkBtn">
        <i data-lucide="moon"></i> Dark
      </button>
    </div>
  </div>

  <!-- 5 Theme Cards Grid -->
  <div class="themes-grid" id="themesGrid">
    <?php foreach ($themesList as $t): ?>
      <?php $isActive = ($t['key'] === $currentTheme); ?>
      <div class="theme-card <?= $isActive ? 'active' : '' ?>" data-theme="<?= htmlspecialchars($t['key'], ENT_QUOTES, 'UTF-8') ?>" role="button" tabindex="0">
        <!-- Checkmark Badge when active -->
        <div class="theme-check-badge">
          <i data-lucide="check" style="width: 14px; height: 14px; stroke-width: 3;"></i>
        </div>

        <!-- Miniature Timer Mockup inside card -->
        <div class="theme-preview-box">
          <!-- Light preview side -->
          <div class="theme-preview-side" style="background-color: <?= $t['lightBg'] ?>; color: <?= $t['lightText'] ?>;">
            <div class="theme-preview-time">25:00</div>
            <div class="theme-preview-bar" style="background-color: <?= $t['accent'] ?>;"></div>
            <div class="theme-preview-label">Light</div>
          </div>
          <!-- Dark preview side -->
          <div class="theme-preview-side" style="background-color: <?= $t['darkBg'] ?>; color: <?= $t['darkText'] ?>;">
            <div class="theme-preview-time">25:00</div>
            <div class="theme-preview-bar" style="background-color: <?= $t['accent'] ?>;"></div>
            <div class="theme-preview-label">Dark</div>
          </div>
        </div>

        <!-- Theme Info -->
        <div class="theme-info-title"><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="theme-info-desc"><?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>

        <!-- Color Swatches Circles -->
        <div class="theme-swatches">
          <?php foreach ($t['swatches'] as $c): ?>
            <span class="swatch-circle" style="background-color: <?= $c ?>;" title="<?= $c ?>"></span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.theme-card');
  const lightBtn = document.getElementById('themeLightBtn');
  const darkBtn = document.getElementById('themeDarkBtn');

  // Select Theme Click & Keyboard Activation
  cards.forEach((card) => {
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        card.click();
      }
    });

    card.addEventListener('click', async () => {
      const themeKey = card.dataset.theme;

      // Update UI active card
      cards.forEach((c) => c.classList.remove('active'));
      card.classList.add('active');

      // Immediate DOM Theme update
      document.documentElement.setAttribute('data-theme', themeKey);

      // Persist to Server MySQL
      try {
        const res = await fetch('api/settings.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.getCsrfToken()
          },
          body: JSON.stringify({
            action: 'update_theme',
            theme_name: themeKey
          })
        });
        const data = await res.json();
        if (data.success) {
          window.showToast(`Theme changed to ${card.querySelector('.theme-info-title').textContent}!`, 'success');
        }
      } catch (err) {
        console.error('Theme persist error:', err);
      }
    });
  });

  // Appearance Mode Toggle
  async function setMode(mode) {
    document.documentElement.setAttribute('data-mode', mode);
    lightBtn.classList.toggle('active', mode === 'light');
    darkBtn.classList.toggle('active', mode === 'dark');

    try {
      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({
          action: 'update_appearance',
          appearance: mode
        })
      });
      const data = await res.json();
      if (data.success) {
        window.showToast(`Switched to ${mode} mode!`, 'info');
      }
    } catch (e) {
      console.error(e);
    }
  }

  lightBtn.addEventListener('click', () => setMode('light'));
  darkBtn.addEventListener('click', () => setMode('dark'));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
