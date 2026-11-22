<?php
// forgot_password.php - Request Password Reset Code
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

requireGuest();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $pdo = getDbConnection();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                // Look up user
                $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Invalidate any previous unused reset codes for this user
                    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$user['user_id']]);

                    // Cryptographically secure 6-digit verification code
                    $resetCode = (string)random_int(100000, 999999);
                    $codeHash = hash('sha256', $resetCode);

                    // 10 minutes expiration per requirements
                    $ins = $pdo->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                    $ins->execute([$user['user_id'], $codeHash]);

                    $resetUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?code={$resetCode}&email=" . urlencode($email);

                    $emailHtml = "
                    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; color: #1f2937;\">
                      <div style=\"text-align: center; margin-bottom: 24px;\">
                        <h1 style=\"color: #111827; margin: 0;\">Study<span style=\"color: #ea580c;\">Maze</span></h1>
                        <p style=\"color: #6b7280; font-size: 0.9rem;\">Password Reset Request</p>
                      </div>
                      <div style=\"background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;\">
                        <p>Hello " . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . ",</p>
                        <p>We received a request to reset your StudyMaze account password. Use the 6-digit verification code below:</p>
                        
                        <div style=\"text-align: center; margin: 24px 0;\">
                          <span style=\"display: inline-block; background: #fff7ed; border: 2px dashed #ea580c; border-radius: 8px; font-size: 2.2rem; font-weight: 700; letter-spacing: 6px; color: #ea580c; padding: 12px 28px;\">{$resetCode}</span>
                        </div>

                        <p style=\"text-align: center;\">
                          <a href=\"{$resetUrl}\" style=\"display: inline-block; background: #ea580c; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600;\">Reset Password Automatically</a>
                        </p>

                        <p style=\"font-size: 0.85rem; color: #6b7280; margin-top: 24px;\">
                          This code is valid for <strong>10 minutes</strong>.
                        </p>
                      </div>
                    </div>";

                    $emailText = "Hello {$user['name']},\n\nYour 6-digit StudyMaze password reset code is: {$resetCode}\n\nThis code expires in 10 minutes.\n\nReset link: {$resetUrl}";

                    sendStudyMazeEmail($email, $user['name'], 'StudyMaze Password Reset Code', $emailHtml, $emailText);

                    // Store development helper code for local XAMPP environments
                    $_SESSION['dev_reset_code'] = $resetCode;
                }

                $_SESSION['reset_email'] = $email;
                header("Location: reset_password.php?sent=1&email=" . urlencode($email));
                exit;
            } catch (Exception $e) {
                error_log("Password reset request error: " . $e->getMessage());
                $error = 'A server error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = 'Forgot Password — StudyMaze';
?>
<!DOCTYPE html>
<html lang="en" data-theme="default" data-mode="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reset your StudyMaze account password securely.">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='3' width='5' height='18' rx='2.5' fill='%23ea580c'/%3E%3Crect x='11' y='7' width='5' height='14' rx='2.5' fill='%23ea580c'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="assets/css/themes.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  
  <script src="assets/js/lucide.min.js" defer></script>
  
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="auth-logo-center">
        <a href="login.php" class="navbar-brand">
          <div class="brand-logo-icon" aria-hidden="true">
            <span class="brand-bar-1"></span>
            <span class="brand-bar-2"></span>
          </div>
          <span class="brand-name">Study<span class="brand-maze">Maze</span></span>
        </a>
      </div>

      <div class="auth-header">
        <h1>Reset Password</h1>
        <p>Enter your account's email address to receive a 6-digit reset code.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
          <i data-lucide="alert-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="forgot_password.php" autocomplete="on">
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
            autofocus
            aria-required="true"
          >
        </div>

        <button type="submit" class="auth-btn">Send Reset Code</button>

        <div class="auth-footer">
          Remember your password? <a href="login.php">Sign in</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</body>
</html>
