<?php
// register.php - User Registration Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireGuest();

$error = '';
$name = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Derive username if not explicitly supplied
        if (empty($username) && !empty($email)) {
            $parts = explode('@', $email);
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]);
        }

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $error = 'Full name must be between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one letter and one number.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo = getDbConnection();

                // Check for duplicates
                $stmt = $pdo->prepare("SELECT user_id, email, username FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                $existing = $stmt->fetch();

                if ($existing) {
                    if (strcasecmp($existing['email'], $email) === 0) {
                        $error = 'An account with that email address already exists. Please sign in.';
                    } else {
                        $error = 'That username is already taken. Please choose another.';
                    }
                } else {
                    $pdo->beginTransaction();

                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("INSERT INTO users (name, username, email, password_hash, email_verified_at) VALUES (?, ?, ?, ?, NOW())");
                    $ins->execute([$name, $username, $email, $hash]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Insert default user settings
                    $setStmt = $pdo->prepare("INSERT INTO user_settings 
                        (user_id, theme_name, appearance, accent_colour, daily_goal_minutes, focus_duration_minutes, short_break_minutes, long_break_minutes, pomodoros_before_long_break, auto_start_breaks, sound_enabled, selected_sound, sound_volume, timer_notifications)
                        VALUES (?, 'default', 'light', '#ea580c', 180, 25, 5, 15, 4, 1, 1, 'rain', 70, 1)");
                    $setStmt->execute([$newUserId]);

                    // Seed starter subjects for clean user onboarding
                    $starterSubjects = [
                        ['Mathematics', '#ea580c', 'Calculus and Linear Algebra'],
                        ['Physics', '#3b82f6', 'Mechanics and Waves'],
                        ['Computer Science', '#10b981', 'Algorithms and Web Development']
                    ];
                    $subIns = $pdo->prepare("INSERT INTO subjects (user_id, subject_name, colour, description) VALUES (?, ?, ?, ?)");
                    foreach ($starterSubjects as $s) {
                        $subIns->execute([$newUserId, $s[0], $s[1], $s[2]]);
                    }

                    $pdo->commit();

                    // Log in immediately and redirect to dashboard
                    loginUser($newUserId);
                    header("Location: dashboard.php");
                    exit;
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Registration error: " . $e->getMessage());
                $error = 'Registration failed due to a server error. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign Up — StudyMaze';
?>
<!DOCTYPE html>
<html lang="en" data-theme="default" data-mode="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create a free StudyMaze account to track your study sessions, stay consistent, and unlock achievements.">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#ea580c">
  <link rel="canonical" href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>/StudyMaze/register.php">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='3' width='5' height='18' rx='2.5' fill='%23ea580c'/%3E%3Crect x='11' y='7' width='5' height='14' rx='2.5' fill='%23ea580c'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="assets/css/themes.css?v=<?= filemtime(__DIR__ . '/assets/css/themes.css') ?>">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="assets/css/responsive.css?v=<?= filemtime(__DIR__ . '/assets/css/responsive.css') ?>">
  
  <script src="assets/js/lucide.min.js" defer></script>
</head>
<body>
  <!-- Accessible Skip-to-Content Link -->
  <a href="#mainContent" class="skip-link">Skip to main content</a>

  <main class="auth-wrapper" id="mainContent" role="main">
    <div class="auth-card">
      <header class="auth-card-header">
        <div class="auth-logo-center">
          <a href="login.php" class="navbar-brand" aria-label="StudyMaze Home">
            <div class="brand-logo-icon" aria-hidden="true">
              <span class="brand-bar-1"></span>
              <span class="brand-bar-2"></span>
            </div>
            <span class="brand-name">Study<span class="brand-maze">Maze</span></span>
          </a>
        </div>

        <div class="auth-header">
          <h1>Create your account</h1>
          <p>Start your focused study journey today.</p>
        </div>
      </header>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
          <i data-lucide="alert-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php" id="registerForm" autocomplete="on" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="name">Full name</label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            class="form-input" 
            placeholder="Enter your full name" 
            value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" 
            required 
            minlength="2"
            maxlength="100"
            autofocus
            aria-required="true"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email address</label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            class="form-input" 
            placeholder="Enter your email address" 
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" 
            required
            aria-required="true"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="password-input-wrapper">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-input" 
              placeholder="At least 8 characters (letters & numbers)" 
              required
              minlength="8"
              aria-required="true"
              aria-describedby="passwordHelp"
            >
            <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
              <i data-lucide="eye" id="eyeIcon" aria-hidden="true" style="width: 18px; height: 18px;"></i>
            </button>
          </div>
          <small id="passwordHelp" style="display:block; font-size:0.75rem; color:var(--text-secondary); margin-top:4px;">Must be at least 8 characters with at least one letter and one number.</small>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm password</label>
          <div class="password-input-wrapper">
            <input 
              type="password" 
              id="confirm_password" 
              name="confirm_password" 
              class="form-input" 
              placeholder="Re-enter your password" 
              required
              minlength="8"
              aria-required="true"
            >
            <button type="button" class="password-toggle-btn" id="toggleConfirmBtn" aria-label="Toggle confirm password visibility">
              <i data-lucide="eye" id="eyeIcon2" aria-hidden="true" style="width: 18px; height: 18px;"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="auth-btn">Create Account</button>

        <div class="auth-footer">
          Already have an account? <a href="login.php" aria-label="Sign in to your existing StudyMaze account">Sign in</a>
        </div>
      </form>
    </div>

    <footer class="auth-footer-region">
      <p class="auth-disclaimer">
        By signing up, you agree to our Terms of Service and Privacy Policy.
      </p>
    </footer>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }

      function setupToggle(btnId, inputId, iconId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (btn && input && icon) {
          btn.addEventListener('click', () => {
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
            icon.setAttribute('data-lucide', isPass ? 'eye-off' : 'eye');
            lucide.createIcons();
          });
        }
      }

      setupToggle('togglePasswordBtn', 'password', 'eyeIcon');
      setupToggle('toggleConfirmBtn', 'confirm_password', 'eyeIcon2');

      // Client-side validation
      const form = document.getElementById('registerForm');
      form.addEventListener('submit', (e) => {
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        let clientAlert = document.getElementById('clientAlert');
        if (pass !== confirm) {
          e.preventDefault();
          if (!clientAlert) {
            clientAlert = document.createElement('div');
            clientAlert.id = 'clientAlert';
            clientAlert.className = 'alert alert-danger';
            clientAlert.setAttribute('role', 'alert');
            clientAlert.innerHTML = '<i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i><span>Passwords do not match. Please check again.</span>';
            form.parentNode.insertBefore(clientAlert, form);
            if (typeof lucide !== 'undefined') lucide.createIcons();
          } else {
            clientAlert.querySelector('span').textContent = 'Passwords do not match. Please check again.';
            clientAlert.style.display = 'flex';
          }
          document.getElementById('confirm_password').focus();
        }
      });
    });
  </script>
</body>
</html>
