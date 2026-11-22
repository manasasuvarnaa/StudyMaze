/**
 * assets/js/app.js - StudyMaze Core Client JavaScript
 */

// CSRF Token Helper
window.getCsrfToken = function() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
};

// Typing Context Detection (Never trigger shortcuts while editing text)
window.isTypingContext = function(target) {
  if (!target) return false;
  const tag = (target.tagName || '').toLowerCase();
  if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
  if (target.isContentEditable) return true;
  if (target.closest('.modal-card') || target.closest('[role="dialog"]')) return true;
  return false;
};

// Toast Notifications System
window.showToast = function(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
  toast.style.cssText = `
    background: var(--bg-card);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-left: 4px solid ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--accent)'};
    padding: 12px 18px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-size: 0.9rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    pointer-events: auto;
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.2s ease, transform 0.2s ease;
    min-width: 250px;
    max-width: 380px;
  `;

  let iconName = 'info';
  if (type === 'success') iconName = 'check-circle';
  if (type === 'error') iconName = 'alert-triangle';

  toast.innerHTML = `<i data-lucide="${iconName}" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0; color: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--accent)'};"></i><span>${message}</span>`;
  container.appendChild(toast);

  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 250);
  }, duration);
};

// Appearance Mode (Light / Dark) Toggle and Persistence
function initAppearanceToggle() {
  const toggleBtn = document.getElementById('appearanceToggleBtn');
  if (!toggleBtn) return;

  const html = document.documentElement;
  const sunIcon = toggleBtn.querySelector('.sun-icon');
  const moonIcon = toggleBtn.querySelector('.moon-icon');

  function updateIcons() {
    const isDark = html.getAttribute('data-mode') === 'dark';
    if (sunIcon && moonIcon) {
      if (isDark) {
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'inline-block';
      } else {
        sunIcon.style.display = 'inline-block';
        moonIcon.style.display = 'none';
      }
    }
  }

  updateIcons();

  async function toggleMode() {
    const currentMode = html.getAttribute('data-mode') || 'light';
    const newMode = currentMode === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-mode', newMode);
    updateIcons();

    try {
      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({
          action: 'update_appearance',
          appearance: newMode
        })
      });
      const data = await res.json();
      if (!data.success) {
        console.warn('Could not save appearance preference:', data.error);
      }
    } catch (err) {
      console.warn('Network error updating appearance:', err);
    }
  }

  toggleBtn.addEventListener('click', toggleMode);
  toggleBtn.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      toggleMode();
    }
  });
}

// User Profile Dropdown Menu with Keyboard Accessibility
function initProfileDropdown() {
  const btn = document.getElementById('profileDropdownBtn');
  const menu = document.getElementById('profileMenu');
  if (!btn || !menu) return;

  function toggleDropdown(show) {
    const willShow = show !== undefined ? show : !menu.classList.contains('show');
    menu.classList.toggle('show', willShow);
    btn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    if (willShow) {
      menu.removeAttribute('hidden');
      menu.setAttribute('aria-hidden', 'false');
    } else {
      menu.setAttribute('hidden', '');
      menu.setAttribute('aria-hidden', 'true');
    }
  }

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleDropdown();
  });

  btn.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
      e.preventDefault();
      toggleDropdown(true);
      const firstItem = menu.querySelector('a, button');
      if (firstItem) firstItem.focus();
    }
  });

  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
      toggleDropdown(false);
    }
  });

  // Global Escape handler for dropdowns and drawers
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (menu.classList.contains('show')) {
        toggleDropdown(false);
        btn.focus();
      }
    }
  });
}

// Accessible Custom Modal Confirmation System
window.showConfirmModal = function(message, confirmLabel = 'Confirm', cancelLabel = 'Cancel') {
  return new Promise((resolve) => {
    const existing = document.getElementById('smGlobalConfirmModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'smGlobalConfirmModal';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.style.cssText = `
      position: fixed; inset: 0; z-index: 99999;
      display: flex; align-items: center; justify-content: center;
      background: rgba(0, 0, 0, 0.5);
      animation: smFadeIn 0.15s ease;
    `;

    overlay.innerHTML = `
      <div style="
        background: var(--bg-card, #ffffff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        padding: 24px 24px 20px;
        max-width: 380px;
        width: 90%;
        animation: smSlideUp 0.18s ease;
        font-family: inherit;
      ">
        <p style="
          color: var(--text-primary, #111827);
          font-size: 0.95rem;
          line-height: 1.5;
          margin: 0 0 20px;
          font-weight: 500;
        ">${message}</p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
          <button id="smModalCancel" type="button" style="
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color, #e5e7eb);
            background: var(--bg-secondary, #f3f4f6);
            color: var(--text-muted, #6b7280);
            font-size: 0.875rem;
            cursor: pointer;
            font-weight: 500;
          ">${cancelLabel}</button>
          <button id="smModalConfirm" type="button" style="
            padding: 8px 18px;
            border-radius: 8px;
            border: none;
            background: var(--accent, #ea580c);
            color: #ffffff;
            font-size: 0.875rem;
            cursor: pointer;
            font-weight: 600;
          ">${confirmLabel}</button>
        </div>
      </div>
    `;

    if (!document.getElementById('smGlobalModalStyles')) {
      const style = document.createElement('style');
      style.id = 'smGlobalModalStyles';
      style.textContent = `
        @keyframes smFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes smSlideUp { from { transform: translateY(12px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(overlay);

    const confirmBtn = overlay.querySelector('#smModalConfirm');
    const cancelBtn = overlay.querySelector('#smModalCancel');

    function cleanup(result) {
      overlay.remove();
      resolve(result);
    }

    confirmBtn.addEventListener('click', () => cleanup(true));
    cancelBtn.addEventListener('click', () => cleanup(false));
    overlay.addEventListener('click', (e) => { if (e.target === overlay) cleanup(false); });

    document.addEventListener('keydown', function escHandler(e) {
      if (e.key === 'Escape') {
        document.removeEventListener('keydown', escHandler);
        cleanup(false);
      }
    });

    setTimeout(() => confirmBtn.focus(), 50);
  });
};

// Utility Formatting
window.formatSeconds = function(seconds) {
  seconds = Math.max(0, Math.floor(seconds));
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0) {
    return `${h}h ${m}m`;
  }
  return `${m}m ${s > 0 && m < 5 ? s + 's' : ''}`.trim();
};

// Mobile Sidebar Drawer Controller
function initMobileSidebar() {
  const hamburgerBtn = document.getElementById('navHamburgerBtn');
  const sidebar = document.getElementById('mobileSidebarNav');
  const overlay = document.getElementById('mobileSidebarOverlay');
  const closeBtn = document.getElementById('mobileSidebarCloseBtn');
  if (!hamburgerBtn || !sidebar || !overlay) return;

  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    document.body.classList.add('sidebar-open');
    hamburgerBtn.setAttribute('aria-expanded', 'true');
    sidebar.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');
    if (closeBtn) closeBtn.focus();
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    hamburgerBtn.setAttribute('aria-expanded', 'false');
    sidebar.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    hamburgerBtn.focus();
  }

  hamburgerBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeSidebar();
    });
  }

  overlay.addEventListener('click', closeSidebar);

  // Close when clicking any nav link inside sidebar
  sidebar.querySelectorAll('.mobile-sidebar-link').forEach((link) => {
    link.addEventListener('click', () => {
      closeSidebar();
    });
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });
}

// Global Page Lifecycle State Hygiene
document.addEventListener('DOMContentLoaded', () => {
  // If not on timer page, ensure Focus Mode is NEVER lingering
  const isTimerPage = window.location.pathname.indexOf('timer.php') !== -1;
  if (!isTimerPage) {
    document.body.classList.remove('focus-mode-active');
    sessionStorage.removeItem('studymaze_focus_mode');
    const strayExitBtn = document.getElementById('exitFocusBtn');
    if (strayExitBtn) strayExitBtn.remove();
  }

  initAppearanceToggle();
  initProfileDropdown();
  initMobileSidebar();
});
