<?php
// subjects.php - Subject CRUD Management Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$pdo = getDbConnection();

// Fetch subjects with study time and session counts
$stmt = $pdo->prepare("
    SELECT 
        s.subject_id, 
        s.subject_name, 
        s.colour, 
        s.description, 
        COALESCE(SUM(CASE WHEN ss.status = 'completed' AND ss.session_type != 'break' THEN ss.duration_seconds ELSE 0 END), 0) AS total_seconds,
        COUNT(CASE WHEN ss.status = 'completed' AND ss.session_type != 'break' THEN 1 ELSE NULL END) AS session_count
    FROM subjects s
    LEFT JOIN study_sessions ss ON s.subject_id = ss.subject_id
    WHERE s.user_id = ?
    GROUP BY s.subject_id
    ORDER BY s.subject_name ASC
");
$stmt->execute([$userId]);
$subjects = $stmt->fetchAll();

function fmtSubTime(int $sec): string {
    $h = (int)floor($sec / 3600);
    $m = (int)round(($sec % 3600) / 60);
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

$pageTitle = 'Subjects — StudyMaze';
$activePage = 'subjects';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent">
  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Subjects</h1>
      <p class="page-subtitle">Organize your study topics and track time per subject.</p>
    </div>
    <button type="button" class="btn-primary" id="openAddSubjectBtn">
      <i data-lucide="plus" style="width: 18px; height: 18px;"></i>
      <span>Add Subject</span>
    </button>
  </div>

  <!-- Subjects Grid -->
  <div class="subjects-grid" id="subjectsGrid">
    <?php foreach ($subjects as $sub): ?>
      <div class="subject-card" id="subCard-<?= (int)$sub['subject_id'] ?>" data-id="<?= (int)$sub['subject_id'] ?>" data-name="<?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?>" data-color="<?= htmlspecialchars($sub['colour'], ENT_QUOTES, 'UTF-8') ?>" data-desc="<?= htmlspecialchars($sub['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="subject-card-header">
          <div class="subject-card-title">
            <span class="subject-dot" style="background-color: <?= htmlspecialchars($sub['colour'], ENT_QUOTES, 'UTF-8') ?>;"></span>
            <span><?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="subject-actions">
            <button type="button" class="icon-btn edit-sub-btn" title="Edit Subject">
              <i data-lucide="pencil" style="width: 15px; height: 15px;"></i>
            </button>
            <button type="button" class="icon-btn delete delete-sub-btn" title="Delete Subject">
              <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
            </button>
          </div>
        </div>
        <div class="subject-card-stats">
          <div class="subject-stat-hours"><?= fmtSubTime((int)$sub['total_seconds']) ?></div>
          <div class="subject-stat-sessions"><?= (int)$sub['session_count'] ?> sessions</div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Dashed Add Subject Card -->
    <button type="button" class="subject-card subject-card-add" id="cardAddSubjectBtn">
      <i data-lucide="plus" style="width: 24px; height: 24px;"></i>
      <span>Add Subject</span>
    </button>
  </div>
</main>

<!-- Add / Edit Subject Modal -->
<div class="modal-backdrop" id="subjectModal">
  <div class="modal-card">
    <div class="modal-header">
      <h2 class="modal-title" id="subjectModalTitle">New Subject</h2>
      <button type="button" class="icon-btn" id="closeSubjectModalBtn">&times;</button>
    </div>
    <form id="subjectForm">
      <input type="hidden" id="modalSubjectId" value="">
      
      <div class="form-group">
        <label class="form-label" for="modalSubjectName">Subject name</label>
        <input type="text" id="modalSubjectName" class="form-input" placeholder="e.g. Mathematics" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label">Color</label>
        <div class="color-picker-palette" id="colorPalette">
          <!-- Color Swatches -->
          <div class="color-option selected" data-color="#ea580c" style="background-color: #ea580c;"></div>
          <div class="color-option" data-color="#f59e0b" style="background-color: #f59e0b;"></div>
          <div class="color-option" data-color="#10b981" style="background-color: #10b981;"></div>
          <div class="color-option" data-color="#14b8a6" style="background-color: #14b8a6;"></div>
          <div class="color-option" data-color="#06b6d4" style="background-color: #06b6d4;"></div>
          <div class="color-option" data-color="#3b82f6" style="background-color: #3b82f6;"></div>
          <div class="color-option" data-color="#6366f1" style="background-color: #6366f1;"></div>
          <div class="color-option" data-color="#8b5cf6" style="background-color: #8b5cf6;"></div>
          <div class="color-option" data-color="#a855f7" style="background-color: #a855f7;"></div>
          <div class="color-option" data-color="#ec4899" style="background-color: #ec4899;"></div>
          <div class="color-option" data-color="#ef4444" style="background-color: #ef4444;"></div>
          <div class="color-option" data-color="#84cc16" style="background-color: #84cc16;"></div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="modalSubjectDesc">Description (optional)</label>
        <input type="text" id="modalSubjectDesc" class="form-input" placeholder="Topic notes or textbook reference">
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="cancelSubjectModalBtn">Cancel</button>
        <button type="submit" class="btn-primary" id="saveSubjectSubmitBtn">
          <i data-lucide="check" style="width: 16px; height: 16px;"></i>
          <span id="subjectSubmitBtnText">Create</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('subjectModal');
  const modalTitle = document.getElementById('subjectModalTitle');
  const subjectIdInput = document.getElementById('modalSubjectId');
  const subjectNameInput = document.getElementById('modalSubjectName');
  const subjectDescInput = document.getElementById('modalSubjectDesc');
  const submitBtnText = document.getElementById('subjectSubmitBtnText');
  const openAddBtn = document.getElementById('openAddSubjectBtn');
  const cardAddBtn = document.getElementById('cardAddSubjectBtn');
  const closeBtn = document.getElementById('closeSubjectModalBtn');
  const cancelBtn = document.getElementById('cancelSubjectModalBtn');
  const form = document.getElementById('subjectForm');
  const colorOptions = document.querySelectorAll('.color-option');

  let selectedColour = '#ea580c';

  function selectColor(hex) {
    selectedColour = hex;
    colorOptions.forEach((opt) => {
      opt.classList.toggle('selected', opt.dataset.color.toLowerCase() === hex.toLowerCase());
    });
  }

  colorOptions.forEach((opt) => {
    opt.addEventListener('click', () => selectColor(opt.dataset.color));
  });

  function openModal(isEdit = false, id = '', name = '', color = '#ea580c', desc = '') {
    modal.classList.add('show');
    if (isEdit) {
      modalTitle.textContent = 'Edit Subject';
      subjectIdInput.value = id;
      subjectNameInput.value = name;
      subjectDescInput.value = desc;
      selectColor(color);
      submitBtnText.textContent = 'Save';
    } else {
      modalTitle.textContent = 'New Subject';
      subjectIdInput.value = '';
      subjectNameInput.value = '';
      subjectDescInput.value = '';
      selectColor('#ea580c');
      submitBtnText.textContent = 'Create';
    }
    subjectNameInput.focus();
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function closeModal() {
    modal.classList.remove('show');
  }

  openAddBtn.addEventListener('click', () => openModal(false));
  cardAddBtn.addEventListener('click', () => openModal(false));
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('show')) {
      closeModal();
    }
  });

  colorOptions.forEach((opt) => {
    opt.setAttribute('tabindex', '0');
    opt.setAttribute('role', 'button');
    opt.setAttribute('aria-label', `Color ${opt.dataset.color}`);
    opt.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectColor(opt.dataset.color);
      }
    });
  });

  // Edit Button Handlers
  document.querySelectorAll('.edit-sub-btn').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const card = btn.closest('.subject-card');
      openModal(true, card.dataset.id, card.dataset.name, card.dataset.color, card.dataset.desc);
    });
  });

  // Delete Button Handlers
  document.querySelectorAll('.delete-sub-btn').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const card = btn.closest('.subject-card');
      const id = card.dataset.id;
      const name = card.dataset.name;

      const confirmed = await window.showConfirmModal(`Are you sure you want to delete "${name}"? Historical study sessions will be preserved.`, "Delete Subject", "Keep");
      if (!confirmed) return;

      try {
        const res = await fetch('api/subjects.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.getCsrfToken()
          },
          body: JSON.stringify({ action: 'delete', subject_id: id })
        });
        const data = await res.json();
        if (data.success) {
          card.remove();
          window.showToast('Subject deleted. Study history preserved.', 'info');
        } else {
          window.showToast(data.error || 'Failed to delete subject', 'error');
        }
      } catch (err) {
        console.error('Delete error:', err);
      }
    });
  });

  // Save / Update Subject Form Submit
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = subjectIdInput.value;
    const name = subjectNameInput.value.trim();
    const desc = subjectDescInput.value.trim();
    const action = id ? 'update' : 'create';

    if (!name) return;

    try {
      const res = await fetch('api/subjects.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({
          action: action,
          subject_id: id || undefined,
          subject_name: name,
          colour: selectedColour,
          description: desc
        })
      });
      const data = await res.json();
      if (data.success) {
        closeModal();
        window.showToast(data.message || 'Subject saved successfully!', 'success');
        setTimeout(() => location.reload(), 300);
      } else {
        window.showToast(data.error || 'Failed to save subject', 'error');
      }
    } catch (err) {
      console.error('Save subject error:', err);
      window.showToast('Server error while saving subject', 'error');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
