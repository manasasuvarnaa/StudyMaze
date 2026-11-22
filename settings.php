<?php
// settings.php - User Preferences & Account Settings Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$user = getCurrentUser();
$settings = getUserSettings($userId);

$themeLabels = [
    'default' => 'Midnight Orange',
    'ghibli' => 'Studio Ghibli',
    'crimson' => 'Crimson Night',
    'forest' => 'Forest Study',
    'midnight' => 'Lavender Dream'
];
$currentThemeName = $themeLabels[$settings['theme_name'] ?? 'default'] ?? 'Midnight Orange';

$pageTitle = 'Settings — StudyMaze';
$activePage = 'settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent" style="max-width: 780px;">
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Settings</h1>
      <p class="page-subtitle">Manage your account and study preferences.</p>
    </div>
  </div>

  <!-- 1. Profile Details Card -->
  <div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
      <span style="display: flex; align-items: center; gap: 8px;">
        <i data-lucide="user" style="color: var(--accent); width: 18px; height: 18px;"></i>
        <span>Profile</span>
      </span>
    </h2>
    <form id="profileForm">
      <div class="form-group">
        <label class="form-label" for="profileName">Full name</label>
        <input type="text" id="profileName" class="form-input" value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="profileEmail">Email</label>
        <input type="email" id="profileEmail" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <button type="submit" class="btn-primary" style="margin-top: 8px;">Save Profile</button>
    </form>
  </div>

  <!-- 2. Timer & Pomodoro Preferences Card -->
  <div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
      <span style="display: flex; align-items: center; gap: 8px;">
        <i data-lucide="clock" style="color: var(--accent); width: 18px; height: 18px;"></i>
        <span>Timer & Pomodoro</span>
      </span>
    </h2>
    <form id="timerSettingsForm">
      <div class="timer-settings-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
        <!-- Focus Duration Stepper -->
        <div>
          <div class="form-label">Focus duration</div>
          <div style="display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 4px 8px; background: var(--bg-card-subtle);">
            <button type="button" class="icon-btn stepper-btn" data-target="focusMin" data-delta="-5">-</button>
            <span style="font-weight: 700; font-size: 0.95rem;" id="focusMinLabel"><?= (int)$settings['focus_duration_minutes'] ?>m</span>
            <input type="hidden" id="focusMin" value="<?= (int)$settings['focus_duration_minutes'] ?>">
            <button type="button" class="icon-btn stepper-btn" data-target="focusMin" data-delta="5">+</button>
          </div>
        </div>

        <!-- Short Break Stepper -->
        <div>
          <div class="form-label">Short break</div>
          <div style="display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 4px 8px; background: var(--bg-card-subtle);">
            <button type="button" class="icon-btn stepper-btn" data-target="shortBreakMin" data-delta="-1">-</button>
            <span style="font-weight: 700; font-size: 0.95rem;" id="shortBreakMinLabel"><?= (int)$settings['short_break_minutes'] ?>m</span>
            <input type="hidden" id="shortBreakMin" value="<?= (int)$settings['short_break_minutes'] ?>">
            <button type="button" class="icon-btn stepper-btn" data-target="shortBreakMin" data-delta="1">+</button>
          </div>
        </div>

        <!-- Long Break Stepper -->
        <div>
          <div class="form-label">Long break</div>
          <div style="display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 4px 8px; background: var(--bg-card-subtle);">
            <button type="button" class="icon-btn stepper-btn" data-target="longBreakMin" data-delta="-5">-</button>
            <span style="font-weight: 700; font-size: 0.95rem;" id="longBreakMinLabel"><?= (int)$settings['long_break_minutes'] ?>m</span>
            <input type="hidden" id="longBreakMin" value="<?= (int)$settings['long_break_minutes'] ?>">
            <button type="button" class="icon-btn stepper-btn" data-target="longBreakMin" data-delta="5">+</button>
          </div>
        </div>
      </div>

      <!-- Daily Goal Setting -->
      <div class="form-group">
        <label class="form-label" for="dailyGoalInput">Daily study goal (minutes)</label>
        <input type="number" id="dailyGoalInput" class="form-input" min="15" max="1440" step="15" value="<?= (int)$settings['daily_goal_minutes'] ?>">
      </div>

      <!-- Toggles -->
      <div style="display: flex; flex-direction: column; gap: 16px; margin: 20px 0;">
        <label style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
          <div>
            <div style="font-weight: 600; font-size: 0.92rem;">Auto-start breaks</div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Automatically start break timer after focus ends</div>
          </div>
          <input type="checkbox" id="autoStartBreaks" style="accent-color: var(--accent); width: 18px; height: 18px;" <?= $settings['auto_start_breaks'] ? 'checked' : '' ?>>
        </label>

        <label style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
          <div>
            <div style="font-weight: 600; font-size: 0.92rem;">Timer notifications</div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Get audio chiming and alerts when sessions end</div>
          </div>
          <input type="checkbox" id="timerNotifications" style="accent-color: var(--accent); width: 18px; height: 18px;" <?= $settings['timer_notifications'] ? 'checked' : '' ?>>
        </label>

        <label style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
          <div>
            <div style="font-weight: 600; font-size: 0.92rem;">Ambient sounds</div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Enable background sounds during study</div>
          </div>
          <input type="checkbox" id="soundEnabled" style="accent-color: var(--accent); width: 18px; height: 18px;" <?= $settings['sound_enabled'] ? 'checked' : '' ?>>
        </label>
      </div>

      <button type="submit" class="btn-primary">Save Timer Preferences</button>
    </form>
  </div>

  <!-- 3. Appearance Card -->
  <div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
      <span style="display: flex; align-items: center; gap: 8px;">
        <i data-lucide="palette" style="color: var(--accent); width: 18px; height: 18px;"></i>
        <span>Appearance</span>
      </span>
    </h2>

    <!-- Light / Dark Mode Toggle Pill -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
      <div>
        <div style="font-weight: 600; font-size: 0.92rem;">Light / Dark mode</div>
        <div style="font-size: 0.8rem; color: var(--text-muted);">Toggle between light and dark appearance</div>
      </div>
      <div class="timer-modes" style="margin-bottom: 0;">
        <button type="button" class="mode-pill <?= $settings['appearance'] === 'light' ? 'active' : '' ?>" id="setLightModeBtn">
          <i data-lucide="sun"></i> Light
        </button>
        <button type="button" class="mode-pill <?= $settings['appearance'] === 'dark' ? 'active' : '' ?>" id="setDarkModeBtn">
          <i data-lucide="moon"></i> Dark
        </button>
      </div>
    </div>

    <!-- Change Theme Link -->
    <a href="themes.php" style="display: flex; align-items: center; justify-content: space-between; padding-top: 16px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span class="subject-dot" style="background-color: var(--accent); width: 14px; height: 14px;"></span>
        <div>
          <div style="font-weight: 600; font-size: 0.92rem;">Change theme</div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">(Current: <?= $currentThemeName ?>)</div>
        </div>
      </div>
      <i data-lucide="chevron-right" style="color: var(--text-muted); width: 18px; height: 18px;"></i>
    </a>
  </div>

  <!-- 4. Account Card -->
  <div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
      <span style="display: flex; align-items: center; gap: 8px;">
        <i data-lucide="shield" style="color: var(--accent); width: 18px; height: 18px;"></i>
        <span>Account</span>
      </span>
    </h2>

    <div style="display: flex; flex-direction: column; gap: 12px;">
      <button type="button" class="btn-secondary" id="openPasswordModalBtn" style="justify-content: flex-start; padding: 12px 16px;">
        <i data-lucide="key"></i>
        <span>Change Password</span>
      </button>

      <button type="button" class="btn-secondary" id="openDeleteAccountModalBtn" style="justify-content: flex-start; padding: 12px 16px; color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="trash-2"></i>
        <span>Delete Account</span>
      </button>
    </div>
  </div>

  <!-- Log Out Outlined Button -->
  <a href="logout.php" class="btn-secondary" style="width: 100%; padding: 12px; margin-top: 8px; justify-content: center; gap: 8px; font-weight: 600;">
    <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
    <span>Log Out</span>
  </a>
</main>

<!-- Change Password Modal -->
<div class="modal-backdrop" id="passwordModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Change Password</h3>
      <button type="button" class="icon-btn" id="closePassModalBtn">&times;</button>
    </div>
    <form id="passwordForm">
      <div class="form-group">
        <label class="form-label" for="currPassword">Current password</label>
        <input type="password" id="currPassword" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="newPassword">New password (min 8 chars)</label>
        <input type="password" id="newPassword" class="form-input" required minlength="8">
      </div>
      <div class="form-group">
        <label class="form-label" for="confirmNewPassword">Confirm new password</label>
        <input type="password" id="confirmNewPassword" class="form-input" required minlength="8">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="cancelPassModalBtn">Cancel</button>
        <button type="submit" class="btn-primary">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Account Modal -->
<div class="modal-backdrop" id="deleteModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title" style="color: var(--danger);">Delete Account</h3>
      <button type="button" class="icon-btn" id="closeDeleteModalBtn">&times;</button>
    </div>
    <form id="deleteAccountForm">
      <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 16px;">
        Warning: This action will permanently remove your account, subjects, tasks, and historical study analytics.
      </p>
      <div class="form-group">
        <label class="form-label" for="confirmDeletePassword">Enter your password to confirm:</label>
        <input type="password" id="confirmDeletePassword" class="form-input" required>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="cancelDeleteModalBtn">Cancel</button>
        <button type="submit" class="btn-primary" style="background-color: var(--danger);">Permanently Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ── Steppers ──────────────────────────────────────────────────────────────
  document.querySelectorAll('.stepper-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.target;
      const delta = parseInt(btn.dataset.delta, 10);
      const input = document.getElementById(targetId);
      const label = document.getElementById(targetId + 'Label');
      let val = parseInt(input.value, 10) + delta;
      if (val < 1) val = 1;
      if (val > 180) val = 180;
      input.value = val;
      label.textContent = `${val}m`;
    });
  });

  // Profile Form Submit
  document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('profileName').value.trim();
    const email = document.getElementById('profileEmail').value.trim();

    try {
      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({ action: 'update_profile', name, email })
      });
      const data = await res.json();
      if (data.success) {
        window.showToast('Profile updated successfully!', 'success');
      } else {
        window.showToast(data.error || 'Failed to update profile', 'error');
      }
    } catch (err) {
      console.error(err);
      window.showToast('Server error saving profile', 'error');
    }
  });

  // Notification permission request on toggle
  const timerNotifToggle = document.getElementById('timerNotifications');
  if (timerNotifToggle) {
    timerNotifToggle.addEventListener('change', () => {
      if (timerNotifToggle.checked && typeof Notification !== 'undefined' && Notification.permission === 'default') {
        Notification.requestPermission().then((perm) => {
          if (perm === 'granted') {
            window.showToast('Browser notifications enabled!', 'success');
          }
        });
      }
    });
  }

  // Timer Settings Form Submit
  document.getElementById('timerSettingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const focus = parseInt(document.getElementById('focusMin').value, 10);
    const shortBreak = parseInt(document.getElementById('shortBreakMin').value, 10);
    const longBreak = parseInt(document.getElementById('longBreakMin').value, 10);
    const dailyGoal = parseInt(document.getElementById('dailyGoalInput').value, 10);
    const autoBreaks = document.getElementById('autoStartBreaks').checked ? 1 : 0;
    const notifications = document.getElementById('timerNotifications').checked ? 1 : 0;
    const sounds = document.getElementById('soundEnabled').checked ? 1 : 0;

    try {
      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({
          action: 'update_timer_settings',
          focus_duration_minutes: focus,
          short_break_minutes: shortBreak,
          long_break_minutes: longBreak,
          daily_goal_minutes: dailyGoal,
          auto_start_breaks: autoBreaks,
          timer_notifications: notifications,
          sound_enabled: sounds
        })
      });
      const data = await res.json();
      if (data.success) {
        localStorage.setItem('studymaze_sound_enabled', sounds ? '1' : '0');
        window.showToast('Timer preferences saved successfully!', 'success');
      } else {
        window.showToast(data.error || 'Failed to save settings', 'error');
      }
    } catch (err) {
      console.error(err);
      window.showToast('Server error saving timer preferences', 'error');
    }
  });

  // Mode Buttons
  const setLightBtn = document.getElementById('setLightModeBtn');
  const setDarkBtn = document.getElementById('setDarkModeBtn');

  async function updateMode(mode) {
    document.documentElement.setAttribute('data-mode', mode);
    setLightBtn.classList.toggle('active', mode === 'light');
    setDarkBtn.classList.toggle('active', mode === 'dark');

    try {
      await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({ action: 'update_appearance', appearance: mode })
      });
      window.showToast(`Switched to ${mode} mode!`, 'info');
    } catch (e) {
      console.error(e);
    }
  }

  setLightBtn.addEventListener('click', () => updateMode('light'));
  setDarkBtn.addEventListener('click', () => updateMode('dark'));

  // Modals (Password & Delete)
  const passModal = document.getElementById('passwordModal');
  const deleteModal = document.getElementById('deleteModal');

  document.getElementById('openPasswordModalBtn').addEventListener('click', () => passModal.classList.add('show'));
  document.getElementById('closePassModalBtn').addEventListener('click', () => passModal.classList.remove('show'));
  document.getElementById('cancelPassModalBtn').addEventListener('click', () => passModal.classList.remove('show'));

  document.getElementById('openDeleteAccountModalBtn').addEventListener('click', () => deleteModal.classList.add('show'));
  document.getElementById('closeDeleteModalBtn').addEventListener('click', () => deleteModal.classList.remove('show'));
  document.getElementById('cancelDeleteModalBtn').addEventListener('click', () => deleteModal.classList.remove('show'));

  passModal.addEventListener('click', (e) => { if (e.target === passModal) passModal.classList.remove('show'); });
  deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) deleteModal.classList.remove('show'); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (passModal.classList.contains('show')) passModal.classList.remove('show');
      if (deleteModal.classList.contains('show')) deleteModal.classList.remove('show');
    }
  });

  // Password Update
  document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const curr = document.getElementById('currPassword').value;
    const next = document.getElementById('newPassword').value;
    const conf = document.getElementById('confirmNewPassword').value;

    if (next !== conf) {
      window.showToast('New passwords do not match.', 'error');
      return;
    }

    try {
      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({
          action: 'change_password',
          current_password: curr,
          new_password: next,
          confirm_password: conf
        })
      });
      const data = await res.json();
      if (data.success) {
        passModal.classList.remove('show');
        window.showToast('Password changed successfully!', 'success');
      } else {
        window.showToast(data.error || 'Failed to change password', 'error');
      }
    } catch (err) {
      console.error(err);
      window.showToast('Server error changing password', 'error');
    }
  });

  // Account Deletion
  document.getElementById('deleteAccountForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const pass = document.getElementById('confirmDeletePassword').value;

    try {
      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({ action: 'delete_account', password: pass })
      });
      const data = await res.json();
      if (data.success) {
        window.showToast('Your account has been deleted.', 'info');
        setTimeout(() => window.location.href = 'login.php', 1200);
      } else {
        window.showToast(data.error || 'Failed to delete account', 'error');
      }
    } catch (err) {
      console.error(err);
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
