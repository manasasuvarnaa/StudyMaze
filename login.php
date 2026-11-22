<?php
// login.php - User Sign In Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireGuest();

$error = '';
$success = '';
$loginId = '';

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success = 'Your password has been reset successfully. Please sign in with your new password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $loginId = trim($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (empty($loginId) || empty($password)) {
            $error = 'Please enter both your email/username and password.';
        } else {
            try {
                $pdo = getDbConnection();

                // Rate limiting check: max 15 failed attempts in last 15 minutes
                $rlStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $rlStmt->execute([$ip]);
                $recentAttempts = (int)$rlStmt->fetchColumn();

                if ($recentAttempts >= 15) {
                    $error = 'Too many failed sign-in attempts. Please wait a few minutes before trying again.';
                } else {
                    $stmt = $pdo->prepare("SELECT user_id, email, username, password_hash FROM users WHERE email = ? OR username = ?");
                    $stmt->execute([$loginId, $loginId]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Clear recent failed attempts on successful login
                        $clrStmt = $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?");
                        $clrStmt->execute([$ip]);

                        loginUser((int)$user['user_id']);
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        // Record failed attempt
                        $insAttempt = $pdo->prepare("INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, NOW())");
                        $insAttempt->execute([$ip]);

                        $error = 'Invalid email/username or password. Please verify your details.';
                    }
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'An unexpected server error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign In — StudyMaze';
?>
<!DOCTYPE html>
<html lang="en" data-theme="default" data-mode="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in to your StudyMaze account to continue tracking your study sessions and stats.">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#ea580c">
  <link rel="canonical" href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>/StudyMaze/login.php">
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
        <!-- Centered Logo -->
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
          <h1>Welcome back</h1>
          <p>Sign in to continue your study journey.</p>
        </div>
      </header>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success" role="status" aria-live="polite" style="background: rgba(16, 185, 129, 0.1); border-color: #10b981; color: #065f46; display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px;">
          <i data-lucide="check-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0; color: #059669;"></i>
          <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
          <i data-lucide="alert-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" id="loginForm" autocomplete="on">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="login_id">Username or email</label>
          <input 
            type="text" 
            id="login_id" 
            name="login_id" 
            class="form-input" 
            placeholder="Enter your username or email" 
            value="<?= htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') ?>" 
            required 
            autofocus
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
              placeholder="Enter your password" 
              required
              aria-required="true"
            >
            <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
              <i data-lucide="eye" id="eyeIcon" aria-hidden="true" style="width: 18px; height: 18px;"></i>
            </button>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 18px;">
          <a href="forgot_password.php" class="forgot-password-link" aria-label="Forgot your password? Reset your password here">
            Forgot password?
          </a>
        </div>

        <button type="submit" class="auth-btn">Sign In</button>

        <div class="auth-footer">
          Don't have an account? <a href="register.php" aria-label="Create a new StudyMaze account">Create an account</a>
        </div>
      </form>
    </div>

    <footer class="auth-footer-region">
      <p class="auth-disclaimer">
        By signing in, you agree to our Terms of Service and Privacy Policy.
      </p>
    </footer>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }

      const toggleBtn = document.getElementById('togglePasswordBtn');
      const passInput = document.getElementById('password');
      if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', () => {
          const isPass = passInput.type === 'password';
          passInput.type = isPass ? 'text' : 'password';
          toggleBtn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
          const icon = document.getElementById('eyeIcon');
          if (icon) {
            icon.setAttribute('data-lucide', isPass ? 'eye-off' : 'eye');
            lucide.createIcons();
          }
        });
      }
    });
  </script>
</body>
</html>
