<?php
// reset_password.php - Verify Code & Set New Password
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireGuest();

$error = '';
$success = '';
$email = trim($_GET['email'] ?? $_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));
$devCode = $_SESSION['dev_reset_code'] ?? '';
$code = trim($_GET['code'] ?? $_POST['code'] ?? $devCode);

if (isset($_GET['sent']) && $_GET['sent'] === '1' && empty($success)) {
    if (!empty($devCode)) {
        $success = "A 6-digit verification code was generated. (Local Dev Mode: Your code is <strong style='letter-spacing:2px;font-size:1.05rem;'>{$devCode}</strong> — also logged to logs/email_log.txt).";
    } else {
        $success = 'A 6-digit verification code has been dispatched to your email.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($code) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
            $error = 'The verification code must be exactly 6 digits.';
        } elseif (strlen($password) < 8) {
            $error = 'The new password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'The password must contain at least one letter and one number.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match. Please verify.';
        } else {
            try {
                $pdo = getDbConnection();
                $codeHash = hash('sha256', $code);

                // Verify code against password_resets
                $stmt = $pdo->prepare("
                    SELECT pr.reset_id, pr.user_id, pr.expires_at, pr.attempt_count, u.email
                    FROM password_resets pr
                    JOIN users u ON pr.user_id = u.user_id
                    WHERE u.email = ? AND pr.code_hash = ? AND pr.used_at IS NULL
                    ORDER BY pr.reset_id DESC
                    LIMIT 1
                ");
                $stmt->execute([$email, $codeHash]);
                $reset = $stmt->fetch();

                if (!$reset) {
                    $error = 'Invalid verification code or email address. Please check and try again.';
                } elseif (strtotime($reset['expires_at']) < time()) {
                    $error = 'This verification code has expired (valid for 10 minutes). Please request a new one.';
                } else {
                    // Valid! Update password
                    $pdo->beginTransaction();

                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updUser = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $updUser->execute([$newHash, $reset['user_id']]);

                    // Invalidate all reset tokens for this user
                    $updReset = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ?");
                    $updReset->execute([$reset['user_id']]);

                    $pdo->commit();

                    unset($_SESSION['reset_email']);
                    unset($_SESSION['dev_reset_code']);
                    header("Location: login.php?reset=1");
                    exit;
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Password reset error: " . $e->getMessage());
                $error = 'A server error occurred while resetting password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Enter New Password — StudyMaze';
?>
<!DOCTYPE html>
<html lang="en" data-theme="default" data-mode="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reset your StudyMaze account password securely.">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#ea580c">
  <link rel="canonical" href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>/StudyMaze/reset_password.php">
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
          <h1>Set New Password</h1>
          <p>Enter the 6-digit code received via email and your new password.</p>
        </div>
      </header>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success" role="status" aria-live="polite" style="background: rgba(16, 185, 129, 0.1); border-color: #10b981; color: #065f46; display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px;">
          <i data-lucide="check-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0; color: #10b981;"></i>
          <span><?= $success /* Contains safe markup */ ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
          <i data-lucide="alert-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="reset_password.php" autocomplete="off">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="email">Account email address</label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            class="form-input" 
            placeholder="Enter your registered email" 
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" 
            required 
            aria-required="true"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="code">6-Digit verification code</label>
          <input 
            type="text" 
            id="code" 
            name="code" 
            class="form-input" 
            placeholder="123456" 
            value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" 
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            style="letter-spacing: 4px; font-size: 1.25rem; text-align: center; font-weight: 600;"
            required 
            autofocus
            aria-required="true"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password">New password</label>
          <div class="password-input-wrapper">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-input" 
              placeholder="Minimum 8 characters" 
              required
              minlength="8"
              aria-required="true"
            >
            <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
              <i data-lucide="eye" id="eyeIcon" aria-hidden="true" style="width: 18px; height: 18px;"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm new password</label>
          <div class="password-input-wrapper">
            <input 
              type="password" 
              id="confirm_password" 
              name="confirm_password" 
              class="form-input" 
              placeholder="Re-enter new password" 
              required
              minlength="8"
              aria-required="true"
            >
            <button type="button" class="password-toggle-btn" id="toggleConfirmBtn" aria-label="Toggle confirm password visibility">
              <i data-lucide="eye" id="eyeIcon2" aria-hidden="true" style="width: 18px; height: 18px;"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="auth-btn">Change Password</button>

        <div class="auth-footer">
          <a href="forgot_password.php">Request a new code</a> &bull; <a href="login.php">Back to Sign In</a>
        </div>
      </form>
    </div>
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
    });
  </script>
</body>
</html>
