<?php
/**
 * OrderGo - Login Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = user_role();
    if ($role === 'admin') header('Location: ' . ROOT_PATH . '/admin/index.php');
    elseif ($role === 'staff') header('Location: ' . ROOT_PATH . '/staff/index.php');
    else header('Location: ' . ROOT_PATH . '/menu.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM odg_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Fetch profile data based on role
            $profile = [];
            if ($user['role'] === 'student') {
                $pStmt = $db->prepare("SELECT * FROM odg_students WHERE student_id = ?");
                $pStmt->execute([$user['user_id']]);
                $profile = $pStmt->fetch() ?: [];
            } elseif ($user['role'] === 'admin') {
                $pStmt = $db->prepare("SELECT * FROM odg_admins WHERE admin_id = ?");
                $pStmt->execute([$user['user_id']]);
                $profile = $pStmt->fetch() ?: [];
            } elseif ($user['role'] === 'staff') {
                $pStmt = $db->prepare("SELECT * FROM odg_staff WHERE staff_id = ?");
                $pStmt->execute([$user['user_id']]);
                $profile = $pStmt->fetch() ?: [];
            }

            login_user($user, $profile);

            if (!empty($redirect) && !str_contains($redirect, 'login.php')) {
                header('Location: ' . $redirect);
            } else {
                if ($user['role'] === 'admin') header('Location: ' . ROOT_PATH . '/admin/index.php');
                elseif ($user['role'] === 'staff') header('Location: ' . ROOT_PATH . '/staff/index.php');
                else header('Location: ' . ROOT_PATH . '/menu.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="page" style="display: flex; align-items: center; justify-content: center; min-height: 85vh;">
  <div class="glass-card" style="width: 100%; max-width: 440px; padding: 2.5rem; margin: 1rem;">
    <div class="text-center" style="margin-bottom: 2rem;">
      <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: var(--accent-tint); color: var(--accent-primary); margin-bottom: 1rem;">
        <i data-lucide="log-in" style="width: 28px; height: 28px;"></i>
      </div>
      <h1 class="page-title" style="font-size: 1.8rem; margin-bottom: 0.3rem;">Welcome Back</h1>
      <p class="page-subtitle" style="font-size: 0.9rem;">Sign in to your OrderGo account</p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="email">Campus Email</label>
        <input type="email" id="email" name="email" class="form-input" placeholder="student@college.edu" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
          <label class="form-label" for="password" style="margin: 0;">Password</label>
          <a href="<?= ROOT_PATH ?>/forgot-password.php" style="font-size: 0.75rem;">Forgot Password?</a>
        </div>
        <div style="position: relative;">
          <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required style="padding-right: 2.5rem;">
          <button type="button" id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 0; display: flex; align-items: center; justify-content: center;" title="Toggle Password Visibility">
            <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-full" style="margin-top: 0.5rem;">
        Sign In <i data-lucide="arrow-right"></i>
      </button>
    </form>

    <p style="text-align: center; font-size: 0.875rem; color: var(--text-secondary); margin-top: 1.5rem;">
      Don't have an account? <a href="<?= ROOT_PATH ?>/register.php" style="font-weight: 600;">Sign up here</a>
    </p>
  </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        passwordInput.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    
    // Refresh lucide icons to apply the new icon
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
