<?php
// verify_email.php - Email Verification Page
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

$error = '';
$success = '';

// Check if user is already logged in and verified
$currentUser = getCurrentUser();
if ($currentUser && !empty($currentUser['email_verified_at'])) {
    header("Location: dashboard.php");
    exit;
}

$email = trim($_GET['email'] ?? $_POST['email'] ?? ($_SESSION['pending_verification_email'] ?? ''));
if ($currentUser && empty($email)) {
    $email = $currentUser['email'];
}

// Handle Resend Request
if (isset($_GET['resend']) || (isset($_POST['action']) && $_POST['action'] === 'resend')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            $error = 'Security session expired. Please refresh and try again.';
        }
    }

    if (empty($error)) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address to resend verification.';
        } else {
            try {
                $pdo = getDbConnection();
                $uStmt = $pdo->prepare("SELECT user_id, name, email, email_verified_at FROM users WHERE email = ?");
                $uStmt->execute([$email]);
                $user = $uStmt->fetch();

                if ($user) {
                    if (!empty($user['email_verified_at'])) {
                        $success = 'This account is already verified! You may sign in now.';
                    } else {
                        // Rate limit resends: 1 per 60 seconds
                        $chkStmt = $pdo->prepare("SELECT created_at FROM email_verifications WHERE user_id = ? ORDER BY verification_id DESC LIMIT 1");
                        $chkStmt->execute([$user['user_id']]);
                        $lastTime = $chkStmt->fetchColumn();

                        if ($lastTime && (time() - strtotime($lastTime)) < 60) {
                            $remaining = 60 - (time() - strtotime($lastTime));
                            $error = "Please wait {$remaining} seconds before requesting another code.";
                        } else {
                            // Invalidate previous active tokens
                            $pdo->prepare("UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$user['user_id']]);

                            $newCode = (string)random_int(100000, 999999);
                            $tokenHash = hash('sha256', $newCode);
                            $ins = $pdo->prepare("INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
                            $ins->execute([$user['user_id'], $tokenHash]);

                            $verifyUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . "/verify_email.php?code={$newCode}&email=" . urlencode($email);

                            $emailHtml = "
                            <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; color: #1f2937;\">
                              <h2 style=\"color: #111827;\">Your New Verification Code</h2>
                              <p>Here is your new verification code for StudyMaze:</p>
                              <div style=\"text-align: center; margin: 24px 0;\">
                                <span style=\"display: inline-block; background: #fff7ed; border: 2px dashed #ea580c; border-radius: 8px; font-size: 2rem; font-weight: 700; letter-spacing: 6px; color: #ea580c; padding: 12px 28px;\">{$newCode}</span>
                              </div>
                              <p style=\"text-align: center;\"><a href=\"{$verifyUrl}\" style=\"color: #ea580c;\">Click here to verify automatically</a></p>
                            </div>";

                            sendStudyMazeEmail($email, $user['name'], 'New StudyMaze Verification Code', $emailHtml);
                            $success = 'A fresh 6-digit verification code has been dispatched to your email.';
                        }
                    }
                } else {
                    // Neutral response to prevent user enumeration
                    $success = 'If an account exists for that email, a verification code was sent.';
                }
            } catch (Exception $e) {
                error_log("Resend verification error: " . $e->getMessage());
                $error = 'Failed to resend code due to a server error. Please try again.';
            }
        }
    }
}

// Handle Code Submission (GET auto-link or POST manual code entry)
$inputCode = trim($_GET['code'] ?? $_POST['code'] ?? '');
if (!empty($inputCode) && empty($error)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            $error = 'Security session expired. Please refresh and try again.';
        }
    }

    if (empty($error)) {
        try {
            $pdo = getDbConnection();
            $codeHash = hash('sha256', $inputCode);

            // Find valid verification record
            $sql = "
                SELECT ev.verification_id, ev.user_id, ev.expires_at, ev.attempt_count, u.email, u.name
                FROM email_verifications ev
                JOIN users u ON ev.user_id = u.user_id
                WHERE ev.token_hash = ? AND ev.used_at IS NULL
            ";
            $params = [$codeHash];
            if (!empty($email)) {
                $sql .= " AND u.email = ?";
                $params[] = $email;
            }
            $sql .= " ORDER BY ev.verification_id DESC LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $record = $stmt->fetch();

            if (!$record) {
                // If code not found, track attempt count if user known
                if (!empty($email)) {
                    $pdo->prepare("UPDATE email_verifications ev JOIN users u ON ev.user_id = u.user_id SET ev.attempt_count = ev.attempt_count + 1 WHERE u.email = ? AND ev.used_at IS NULL")->execute([$email]);
                }
                $error = 'Invalid verification code. Please check the code and try again.';
            } elseif (strtotime($record['expires_at']) < time()) {
                $error = 'This verification code has expired. Please request a new code below.';
            } elseif ((int)$record['attempt_count'] >= 5) {
                $error = 'Too many failed attempts with this code. Please request a new verification code.';
            } else {
                // Valid! Mark verified
                $pdo->beginTransaction();

                $updEv = $pdo->prepare("UPDATE email_verifications SET used_at = NOW() WHERE verification_id = ?");
                $updEv->execute([$record['verification_id']]);

                $updU = $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE user_id = ?");
                $updU->execute([$record['user_id']]);

                $pdo->commit();

                // Log user in
                loginUser((int)$record['user_id']);
                unset($_SESSION['pending_verification_email']);

                header("Location: dashboard.php?verified=1");
                exit;
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Verification error: " . $e->getMessage());
            $error = 'Verification failed due to a server error. Please try again.';
        }
    }
}

if (isset($_GET['sent']) && empty($success) && empty($error)) {
    $success = 'We have sent a 6-digit verification code to your email address.';
}

$pageTitle = 'Verify Email — StudyMaze';
?>
<!DOCTYPE html>
<html lang="en" data-theme="default" data-mode="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h1>Verify Your Email</h1>
        <p>Please enter the 6-digit code sent to <?= !empty($email) ? '<strong>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong>' : 'your email address' ?>.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
          <i data-lucide="alert-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success" role="status" aria-live="polite" style="background: rgba(16, 185, 129, 0.1); border-color: #10b981; color: #065f46; display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px;">
          <i data-lucide="check-circle" aria-hidden="true" style="width: 18px; height: 18px; flex-shrink: 0; color: #10b981;"></i>
          <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <!-- Code Submission Form -->
      <form method="POST" action="verify_email.php" autocomplete="off">
        <?= csrfField() ?>
        <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
          <label class="form-label" for="code">6-Digit Verification Code</label>
          <input 
            type="text" 
            id="code" 
            name="code" 
            class="form-input" 
            placeholder="123456" 
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            style="letter-spacing: 4px; font-size: 1.25rem; text-align: center; font-weight: 600;"
            required 
            autofocus
            aria-required="true"
          >
        </div>

        <button type="submit" class="auth-btn">Verify Account</button>
      </form>

      <!-- Resend Code Form -->
      <div style="margin-top: 20px; text-align: center; border-top: 1px solid var(--border-color); padding-top: 16px;">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">Didn't receive the email? Check your spam folder or request a new code.</p>
        <form method="POST" action="verify_email.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="resend">
          <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="btn" style="background: none; border: none; color: var(--accent); font-weight: 600; cursor: pointer; text-decoration: underline; font-size: 0.875rem;">
            Resend Verification Code
          </button>
        </form>
      </div>

      <div class="auth-footer" style="margin-top: 18px;">
        <a href="login.php">Back to Sign In</a>
      </div>
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
