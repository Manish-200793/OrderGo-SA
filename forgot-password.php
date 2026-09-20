<?php
/**
 * OrderGo - Password Recovery Flow with Real SMTP Email Dispatch
 * Sends 6-digit verification code from admin@specanciens.com
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Allow restart / change email
if (isset($_GET['restart']) && $_GET['restart'] == '1') {
    unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_expires'], $_SESSION['reset_step'], $_SESSION['reset_name']);
    header('Location: ' . ROOT_PATH . '/forgot-password.php');
    exit;
}

$step = (int)($_SESSION['reset_step'] ?? 1);
$msg = '';
$err = '';
$db = get_db();

// Handle Reset Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // STEP 1: Send Verification Code via SMTP
    if ($action === 'send_code' || $action === 'resend_code') {
        $email = trim($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = "Please enter a valid email address.";
        } else {
            // Find registered account in odg_users
            $stmt = $db->prepare("
                SELECT u.user_id, u.email,
                       COALESCE(s.name, a.name, st.name, 'Student') as name
                FROM odg_users u
                LEFT JOIN odg_students s ON u.user_id = s.student_id
                LEFT JOIN odg_admins a ON u.user_id = a.admin_id
                LEFT JOIN odg_staff st ON u.user_id = st.staff_id
                WHERE u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $userAccount = $stmt->fetch();

            if ($userAccount) {
                // Generate a secure 6-digit verification code
                $code = sprintf('%06d', random_int(100000, 999999));
                $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

                // Clean up any existing active reset codes for this email in database
                $db->prepare("DELETE FROM odg_password_resets WHERE email = ?")->execute([$email]);

                // Store in database
                $ins = $db->prepare("INSERT INTO odg_password_resets (email, code, expires_at) VALUES (?, ?, ?)");
                $ins->execute([$email, $code, $expiresAt]);

                // Store in session
                $_SESSION['reset_code'] = $code;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_name'] = $userAccount['name'];
                $_SESSION['reset_expires'] = time() + 600;

                // Send email from admin@specanciens.com
                $mailRes = send_password_reset_email($email, $userAccount['name'], $code);

                if ($mailRes['success']) {
                    $_SESSION['reset_step'] = 2;
                    $step = 2;
                    $msg = "A 6-digit verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox (and spam folder).";
                } else {
                    $err = "Failed to dispatch verification email. Error: " . htmlspecialchars($mailRes['error'] ?? 'SMTP error');
                }
            } else {
                // Account not found
                $err = "No OrderGo account found matching this email address.";
            }
        }
    } 
    // STEP 2: Verify 6-digit code
    elseif ($action === 'verify_code') {
        $code = trim($_POST['code'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';

        // Check both session and database
        $valid = false;

        if (!empty($email) && !empty($code)) {
            // Check session
            if (isset($_SESSION['reset_code']) && $_SESSION['reset_code'] === $code && time() <= ($_SESSION['reset_expires'] ?? 0)) {
                $valid = true;
            } else {
                // Fallback check in database
                $cStmt = $db->prepare("SELECT id FROM odg_password_resets WHERE email = ? AND code = ? AND expires_at >= NOW() LIMIT 1");
                $cStmt->execute([$email, $code]);
                if ($cStmt->fetch()) {
                    $valid = true;
                }
            }
        }

        if ($valid) {
            $_SESSION['reset_step'] = 3;
            $step = 3;
            $msg = "Verification code confirmed! You can now set your new password.";
        } else {
            $err = "Invalid or expired verification code. Please check your email or request a new code.";
        }
    } 
    // STEP 3: Reset password
    elseif ($action === 'reset_password') {
        $newPass = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';

        if (empty($email)) {
            $err = "Session expired. Please restart the password reset process.";
            $step = 1;
        } elseif (strlen($newPass) < 6) {
            $err = "Password must be at least 6 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $err = "Passwords do not match.";
        } else {
            $pwdHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE odg_users SET password_hash = ? WHERE email = ?");
            $stmt->execute([$pwdHash, $email]);

            // Clear codes from database and session
            $db->prepare("DELETE FROM odg_password_resets WHERE email = ?")->execute([$email]);
            unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_expires'], $_SESSION['reset_step'], $_SESSION['reset_name']);

            $step = 4;
            $msg = "Password changed successfully! You can now log in with your new credentials.";
        }
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>

<style>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.spin {
  animation: spin 1s linear infinite;
  display: inline-block;
}
</style>

<div class="page" style="display: flex; align-items: center; justify-content: center; min-height: 85vh; padding: 2rem 1rem;">
  <div class="glass-card" style="width: 100%; max-width: 460px; padding: 2.5rem; margin: auto;">
    
    <!-- Header Icon & Title -->
    <div class="text-center" style="margin-bottom: 2rem;">
      <div style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; border-radius: 50%; background: var(--accent-tint, rgba(249, 115, 22, 0.12)); color: var(--accent-primary, #f97316); margin-bottom: 1rem; border: 1px solid rgba(249, 115, 22, 0.25);">
        <?php if ($step === 4): ?>
          <i data-lucide="check-circle" style="width: 32px; height: 32px; color: #10b981;"></i>
        <?php elseif ($step === 3): ?>
          <i data-lucide="shield-check" style="width: 30px; height: 30px;"></i>
        <?php elseif ($step === 2): ?>
          <i data-lucide="mail-check" style="width: 30px; height: 30px;"></i>
        <?php else: ?>
          <i data-lucide="key-round" style="width: 30px; height: 30px;"></i>
        <?php endif; ?>
      </div>
      <h1 class="page-title" style="font-size: 1.75rem; margin-bottom: 0.35rem;">
        <?php
          if ($step === 2) echo 'Enter Verification Code';
          elseif ($step === 3) echo 'Set New Password';
          elseif ($step === 4) echo 'Password Reset Complete';
          else echo 'Password Recovery';
        ?>
      </h1>
      <p class="page-subtitle" style="font-size: 0.9rem;">
        <?php
          if ($step === 2) echo 'Check your email inbox for your 6-digit code';
          elseif ($step === 3) echo 'Create a strong new password for your account';
          elseif ($step === 4) echo 'Your account credentials have been updated';
          else echo 'Reset your campus dining account password';
        ?>
      </p>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($err)): ?>
      <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; padding: 0.85rem 1rem; border-radius: var(--radius-md, 8px); margin-bottom: 1.5rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.6rem;">
        <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
        <div><?= htmlspecialchars($err) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($msg)): ?>
      <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #059669; padding: 0.85rem 1rem; border-radius: var(--radius-md, 8px); margin-bottom: 1.5rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.6rem;">
        <i data-lucide="check" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
        <div><?= $msg ?></div>
      </div>
    <?php endif; ?>

    <!-- STEP 1: Enter Email -->
    <?php if ($step === 1): ?>
      <form method="POST" action="" onsubmit="const btn = document.getElementById('btn-send'); btn.disabled = true; btn.innerHTML = '<span class=\'spin\'>⏳</span> Sending Code...';">
        <input type="hidden" name="action" value="send_code">
        
        <div class="form-group" style="margin-bottom: 1.5rem;">
          <label class="form-label" for="email">Your Registered Email</label>
          <div style="position: relative;">
            <input type="email" id="email" name="email" class="form-input" placeholder="student@college.edu" required autofocus style="padding-left: 2.5rem;">
            <i data-lucide="mail" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted, #94a3b8);"></i>
          </div>
          <small style="color: var(--text-muted, #94a3b8); font-size: 0.78rem; display: block; margin-top: 0.35rem;">
            A secure verification code will be sent from <strong>admin@specanciens.com</strong>.
          </small>
        </div>

        <button type="submit" id="btn-send" class="btn btn-primary btn-lg w-full">
          Send Verification Code <i data-lucide="arrow-right"></i>
        </button>
      </form>

    <!-- STEP 2: Enter Verification Code -->
    <?php elseif ($step === 2): ?>
      <div style="background: var(--bg-surface, rgba(255,255,255,0.04)); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: var(--radius-md, 8px); padding: 0.85rem 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="color: var(--text-muted, #94a3b8);">Recipient:</span>
          <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>
        </div>
        <a href="<?= ROOT_PATH ?>/forgot-password.php?restart=1" style="font-size: 0.8rem; color: var(--accent-primary, #f97316); text-decoration: underline;">
          Change
        </a>
      </div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="verify_code">
        
        <div class="form-group" style="margin-bottom: 1.5rem;">
          <label class="form-label text-center" for="code" style="display: block;">Enter 6-Digit Code</label>
          <input type="text" id="code" name="code" class="form-input text-center" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" style="letter-spacing: 8px; font-size: 1.8rem; font-weight: 800; padding: 0.6rem 0;" required autofocus>
          <small style="color: var(--text-muted, #94a3b8); font-size: 0.78rem; text-align: center; display: block; margin-top: 0.4rem;">
            Code expires in 10 minutes. Check spam or junk folder if not seen.
          </small>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full" style="margin-bottom: 1rem;">
          Verify Code <i data-lucide="check"></i>
        </button>
      </form>

      <!-- Resend Code Form -->
      <form method="POST" action="" onsubmit="const btn = document.getElementById('btn-resend'); btn.disabled = true; btn.innerHTML = 'Resending code...';">
        <input type="hidden" name="action" value="resend_code">
        <button type="submit" id="btn-resend" class="btn btn-ghost btn-sm w-full" style="font-size: 0.85rem; color: var(--text-secondary, #cbd5e1);">
          <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i> Didn't receive email? Resend Code
        </button>
      </form>

    <!-- STEP 3: Enter New Password -->
    <?php elseif ($step === 3): ?>
      <form method="POST" action="">
        <input type="hidden" name="action" value="reset_password">

        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label class="form-label" for="new_password">New Password</label>
          <div style="position: relative;">
            <input type="password" id="new_password" name="new_password" class="form-input" placeholder="At least 6 characters" minlength="6" required autofocus style="padding-left: 2.5rem;">
            <i data-lucide="lock" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted, #94a3b8);"></i>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
          <label class="form-label" for="confirm_password">Confirm New Password</label>
          <div style="position: relative;">
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Repeat new password" minlength="6" required style="padding-left: 2.5rem;">
            <i data-lucide="lock-check" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted, #94a3b8);"></i>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full">
          Update Password <i data-lucide="check-circle-2"></i>
        </button>
      </form>

    <!-- STEP 4: Reset Success -->
    <?php elseif ($step === 4): ?>
      <div class="text-center" style="padding: 1rem 0;">
        <div style="background: rgba(16, 185, 129, 0.12); border: 1.5px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
          <h3 style="color: #10b981; margin-bottom: 0.5rem; font-size: 1.2rem;">All Set!</h3>
          <p style="font-size: 0.9rem; color: var(--text-secondary, #cbd5e1); margin: 0;">
            Your password has been updated securely. You can now use your new credentials to access your OrderGo account.
          </p>
        </div>

        <a href="<?= ROOT_PATH ?>/login.php" class="btn btn-primary btn-lg w-full">
          Sign In Now <i data-lucide="log-in"></i>
        </a>
      </div>
    <?php endif; ?>

    <!-- Back to Login Navigation -->
    <?php if ($step !== 4): ?>
      <div style="text-align: center; margin-top: 1.75rem; border-top: 1px solid var(--border-subtle, rgba(255,255,255,0.06)); padding-top: 1.25rem;">
        <a href="<?= ROOT_PATH ?>/login.php" style="font-size: 0.85rem; color: var(--text-secondary, #cbd5e1); display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;">
          <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> Return to Sign In
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
