<?php
// tasks.php - Academic Task Management Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)getCurrentUserId();
$pdo = getDbConnection();

$settings = getUserSettings($userId);
$dailyGoalMinutes = (int)($settings['daily_goal_minutes'] ?? 180);

// Today's Study Time for Goal Bar
$todayStmt = $pdo->prepare("
    SELECT COALESCE(SUM(duration_seconds), 0)
    FROM study_sessions
    WHERE user_id = ? AND status = 'completed' AND session_type != 'break' AND DATE(started_at) = CURDATE()
");
$todayStmt->execute([$userId]);
$todaySec = (int)$todayStmt->fetchColumn();
$todayMin = (int)round($todaySec / 60);
$goalPercent = min(100, $dailyGoalMinutes > 0 ? (int)round(($todayMin / $dailyGoalMinutes) * 100) : 0);

function fmtTaskTime(int $sec): string {
    $h = (int)floor($sec / 3600);
    $m = (int)round(($sec % 3600) / 60);
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

// Fetch Tasks
$taskStmt = $pdo->prepare("
    SELECT 
        t.task_id, 
        t.subject_id, 
        t.task_name, 
        t.description, 
        t.priority, 
        t.status, 
        t.deadline, 
        s.subject_name,
        s.colour AS subject_colour
    FROM tasks t
    LEFT JOIN subjects s ON t.subject_id = s.subject_id
    WHERE t.user_id = ?
    ORDER BY (t.status = 'completed') ASC, (t.deadline IS NULL) ASC, t.deadline ASC, t.created_at DESC
");
$taskStmt->execute([$userId]);
$allTasks = $taskStmt->fetchAll();

$pendingTasks = array_filter($allTasks, fn($t) => $t['status'] === 'pending');
$completedTasks = array_filter($allTasks, fn($t) => $t['status'] === 'completed');

// Fetch user subjects for dropdown
$subStmt = $pdo->prepare("SELECT subject_id, subject_name, colour FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$subStmt->execute([$userId]);
$userSubjects = $subStmt->fetchAll();

$pageTitle = 'Tasks — StudyMaze';
$activePage = 'tasks';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content" id="mainContent">
  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">Tasks</h1>
      <p class="page-subtitle">Plan and track your academic work.</p>
    </div>
    <button type="button" class="btn-primary" id="openAddTaskBtn">
      <i data-lucide="plus" style="width: 18px; height: 18px;"></i>
      <span>Add Task</span>
    </button>
  </div>

  <!-- Daily Study Goal Bar Card -->
  <div class="goal-card">
    <div class="goal-info">
      <span>Daily Study Goal <span style="font-weight: 500; color: var(--text-muted);"><?= fmtTaskTime($todaySec) ?> / <?= fmtTaskTime($dailyGoalMinutes * 60) ?></span></span>
      <span class="goal-percentage"><?= $goalPercent ?>%</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill" style="width: <?= $goalPercent ?>%;"></div>
    </div>
  </div>

  <!-- PENDING TASKS SECTION -->
  <div class="task-section-title">PENDING — <?= count($pendingTasks) ?></div>
  <div class="task-list" id="pendingTaskList">
    <?php if (empty($pendingTasks)): ?>
      <div class="card" style="text-align: center; color: var(--text-muted); padding: 24px;">
        No pending tasks. Great job!
      </div>
    <?php else: ?>
      <?php foreach ($pendingTasks as $tsk): ?>
        <div class="task-card" data-id="<?= (int)$tsk['task_id'] ?>" data-name="<?= htmlspecialchars($tsk['task_name'], ENT_QUOTES, 'UTF-8') ?>" data-subject="<?= (int)($tsk['subject_id'] ?? 0) ?>" data-priority="<?= htmlspecialchars($tsk['priority'], ENT_QUOTES, 'UTF-8') ?>" data-deadline="<?= htmlspecialchars($tsk['deadline'] ? date('Y-m-d', strtotime($tsk['deadline'])) : '', ENT_QUOTES, 'UTF-8') ?>" data-desc="<?= htmlspecialchars($tsk['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <div class="task-left">
            <button type="button" class="task-checkbox toggle-task-btn" title="Mark as completed">
              <i data-lucide="check" style="width: 13px; height: 13px;"></i>
            </button>
            <div>
              <div class="task-title"><?= htmlspecialchars($tsk['task_name'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="task-meta">
                <?php if (!empty($tsk['subject_name'])): ?>
                  <span style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="subject-dot" style="background-color: <?= htmlspecialchars($tsk['subject_colour'] ?? '#ea580c', ENT_QUOTES, 'UTF-8') ?>; width: 8px; height: 8px;"></span>
                    <span><?= htmlspecialchars($tsk['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
                  </span>
                <?php endif; ?>
                <?php if (!empty($tsk['deadline'])): ?>
                  <span style="display: inline-flex; align-items: center; gap: 4px;">
                    <i data-lucide="calendar" style="width: 13px; height: 13px;"></i>
                    <span><?= date('Y-m-d', strtotime($tsk['deadline'])) ?></span>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="task-right">
            <span class="priority-badge <?= htmlspecialchars($tsk['priority'], ENT_QUOTES, 'UTF-8') ?>">P</span>
            <button type="button" class="icon-btn edit-task-btn" title="Edit Task">
              <i data-lucide="pencil" style="width: 15px; height: 15px;"></i>
            </button>
            <button type="button" class="icon-btn delete delete-task-btn" title="Delete Task">
              <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- COMPLETED TASKS SECTION -->
  <div class="task-section-title">COMPLETED — <?= count($completedTasks) ?></div>
  <div class="task-list" id="completedTaskList">
    <?php if (empty($completedTasks)): ?>
      <div class="card" style="text-align: center; color: var(--text-muted); padding: 20px;">
        No completed tasks yet.
      </div>
    <?php else: ?>
      <?php foreach ($completedTasks as $tsk): ?>
        <div class="task-card completed" data-id="<?= (int)$tsk['task_id'] ?>">
          <div class="task-left">
            <button type="button" class="task-checkbox toggle-task-btn" title="Reopen task">
              <i data-lucide="check" style="width: 13px; height: 13px;"></i>
            </button>
            <span class="task-title"><?= htmlspecialchars($tsk['task_name'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="task-right">
            <button type="button" class="icon-btn delete delete-task-btn" title="Delete Task">
              <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<!-- Add / Edit Task Modal -->
<div class="modal-backdrop" id="taskModal">
  <div class="modal-card">
    <div class="modal-header">
      <h2 class="modal-title" id="taskModalTitle">New Task</h2>
      <button type="button" class="icon-btn" id="closeTaskModalBtn">&times;</button>
    </div>
    <form id="taskForm">
      <input type="hidden" id="modalTaskId" value="">

      <div class="form-group">
        <label class="form-label" for="modalTaskTitle">Task title</label>
        <input type="text" id="modalTaskTitle" class="form-input" placeholder="e.g. Complete Problem Set" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="modalTaskSubject">Subject</label>
        <select id="modalTaskSubject" class="form-select">
          <option value="">No subject</option>
          <?php foreach ($userSubjects as $sub): ?>
            <option value="<?= (int)$sub['subject_id'] ?>">
              <?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Priority Pills -->
      <div class="form-group">
        <label class="form-label">Priority</label>
        <div style="display: flex; gap: 8px;">
          <button type="button" class="duration-pill priority-pill" data-priority="low">
            <i data-lucide="flag" style="width: 14px; height: 14px;"></i> Low
          </button>
          <button type="button" class="duration-pill priority-pill active" data-priority="medium">
            <i data-lucide="flag" style="width: 14px; height: 14px;"></i> Medium
          </button>
          <button type="button" class="duration-pill priority-pill" data-priority="high">
            <i data-lucide="flag" style="width: 14px; height: 14px;"></i> High
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="modalTaskDeadline">Deadline</label>
        <input type="date" id="modalTaskDeadline" class="form-input" value="<?= date('Y-m-d', strtotime('+2 days')) ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="modalTaskDesc">Notes / Description (optional)</label>
        <input type="text" id="modalTaskDesc" class="form-input" placeholder="Additional details or links">
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="cancelTaskModalBtn">Cancel</button>
        <button type="submit" class="btn-primary">
          <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
          <span id="taskSubmitBtnText">Add Task</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('taskModal');
  const modalTitle = document.getElementById('taskModalTitle');
  const taskIdInput = document.getElementById('modalTaskId');
  const taskTitleInput = document.getElementById('modalTaskTitle');
  const taskSubInput = document.getElementById('modalTaskSubject');
  const taskDeadlineInput = document.getElementById('modalTaskDeadline');
  const taskDescInput = document.getElementById('modalTaskDesc');
  const submitBtnText = document.getElementById('taskSubmitBtnText');
  const openAddBtn = document.getElementById('openAddTaskBtn');
  const closeBtn = document.getElementById('closeTaskModalBtn');
  const cancelBtn = document.getElementById('cancelTaskModalBtn');
  const form = document.getElementById('taskForm');
  const priorityPills = document.querySelectorAll('.priority-pill');

  let selectedPriority = 'medium';

  function setPriority(p) {
    selectedPriority = p;
    priorityPills.forEach((pill) => {
      pill.classList.toggle('active', pill.dataset.priority === p);
    });
  }

  priorityPills.forEach((pill) => {
    pill.addEventListener('click', () => setPriority(pill.dataset.priority));
  });

  function openModal(isEdit = false, id = '', title = '', subId = '', prio = 'medium', deadline = '', desc = '') {
    modal.classList.add('show');
    if (isEdit) {
      modalTitle.textContent = 'Edit Task';
      taskIdInput.value = id;
      taskTitleInput.value = title;
      taskSubInput.value = subId || '';
      taskDeadlineInput.value = deadline;
      taskDescInput.value = desc;
      setPriority(prio);
      submitBtnText.textContent = 'Save';
    } else {
      modalTitle.textContent = 'New Task';
      taskIdInput.value = '';
      taskTitleInput.value = '';
      taskSubInput.value = '';
      taskDeadlineInput.value = new Date(Date.now() + 86400000 * 2).toISOString().split('T')[0];
      taskDescInput.value = '';
      setPriority('medium');
      submitBtnText.textContent = 'Add Task';
    }
    taskTitleInput.focus();
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function closeModal() {
    modal.classList.remove('show');
  }

  openAddBtn.addEventListener('click', () => openModal(false));
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('show')) {
      closeModal();
    }
  });

  // Toggle Task Completion
  document.querySelectorAll('.toggle-task-btn').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const card = btn.closest('.task-card');
      const id = card.dataset.id;
      const isCompleted = card.classList.contains('completed');
      const newStatus = isCompleted ? 'pending' : 'completed';

      try {
        const res = await fetch('api/tasks.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.getCsrfToken()
          },
          body: JSON.stringify({
            action: 'toggle',
            task_id: id,
            status: newStatus
          })
        });
        const data = await res.json();
        if (data.success) {
          window.showToast(`Task marked as ${newStatus}!`, 'success');
          setTimeout(() => location.reload(), 250);
        }
      } catch (err) {
        console.error('Toggle error:', err);
      }
    });
  });

  // Edit Task Button
  document.querySelectorAll('.edit-task-btn').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const card = btn.closest('.task-card');
      openModal(
        true,
        card.dataset.id,
        card.dataset.name,
        card.dataset.subject,
        card.dataset.priority,
        card.dataset.deadline,
        card.dataset.desc
      );
    });
  });

  // Delete Task Button
  document.querySelectorAll('.delete-task-btn').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const card = btn.closest('.task-card');
      const id = card.dataset.id;

      const confirmed = await window.showConfirmModal('Are you sure you want to delete this task?', 'Delete Task', 'Cancel');
      if (!confirmed) return;

      try {
        const res = await fetch('api/tasks.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.getCsrfToken()
          },
          body: JSON.stringify({ action: 'delete', task_id: id })
        });
        const data = await res.json();
        if (data.success) {
          card.remove();
          window.showToast('Task deleted successfully.', 'info');
        }
      } catch (err) {
        console.error('Delete task error:', err);
      }
    });
  });

  // Form Submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = taskIdInput.value;
    const title = taskTitleInput.value.trim();
    const subId = taskSubInput.value;
    const deadline = taskDeadlineInput.value;
    const desc = taskDescInput.value.trim();
    const action = id ? 'update' : 'create';

    if (!title) return;

    try {
      const res = await fetch('api/tasks.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getCsrfToken()
        },
        body: JSON.stringify({
          action: action,
          task_id: id || undefined,
          task_name: title,
          subject_id: subId || null,
          priority: selectedPriority,
          deadline: deadline || null,
          description: desc
        })
      });
      const data = await res.json();
      if (data.success) {
        closeModal();
        window.showToast(data.message || 'Task saved successfully!', 'success');
        setTimeout(() => location.reload(), 300);
      } else {
        window.showToast(data.error || 'Failed to save task', 'error');
      }
    } catch (err) {
      console.error('Task save error:', err);
      window.showToast('Server error while saving task', 'error');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
