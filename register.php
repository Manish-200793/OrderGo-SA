<?php
/**
 * OrderGo - Student Registration Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . ROOT_PATH . '/menu.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $rollNumber = trim($_POST['roll_number'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Name, email, and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $db = get_db();
        
        // Check if email already registered
        $stmt = $db->prepare("SELECT user_id FROM odg_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            try {
                $db->beginTransaction();

                $pwdHash = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $db->prepare("INSERT INTO odg_users (email, password_hash, role) VALUES (?, ?, 'student')");
                $uStmt->execute([$email, $pwdHash]);
                $userId = (int)$db->lastInsertId();

                $sStmt = $db->prepare("INSERT INTO odg_students (student_id, name, email, phone, roll_number) VALUES (?, ?, ?, ?, ?)");
                $sStmt->execute([$userId, $name, $email, $phone ?: null, $rollNumber ?: null]);

                $db->commit();

                // Log the student in
                login_user(
                    ['user_id' => $userId, 'email' => $email, 'role' => 'student'],
                    ['name' => $name, 'phone' => $phone, 'roll_number' => $rollNumber]
                );

                header('Location: ' . ROOT_PATH . '/menu.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="page" style="display: flex; align-items: center; justify-content: center; min-height: 85vh;">
  <div class="glass-card" style="width: 100%; max-width: 480px; padding: 2.5rem; margin: 1rem;">
    <div class="text-center" style="margin-bottom: 2rem;">
      <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: var(--accent-tint); color: var(--accent-primary); margin-bottom: 1rem;">
        <i data-lucide="user-plus" style="width: 28px; height: 28px;"></i>
      </div>
      <h1 class="page-title" style="font-size: 1.8rem; margin-bottom: 0.3rem;">Create Account</h1>
      <p class="page-subtitle" style="font-size: 0.9rem;">Join OrderGo to skip campus canteen queues</p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="name">Full Name *</label>
        <input type="text" id="name" name="name" class="form-input" placeholder="Rahul Sharma" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Campus Email *</label>
        <input type="email" id="email" name="email" class="form-input" placeholder="rahul@college.edu" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" class="form-input" placeholder="9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="roll_number">Roll / ID Number</label>
          <input type="text" id="roll_number" name="roll_number" class="form-input" placeholder="CS2024001" value="<?= htmlspecialchars($_POST['roll_number'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password (Min. 6 chars) *</label>
        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-full" style="margin-top: 0.5rem;">
        Sign Up <i data-lucide="arrow-right"></i>
      </button>
    </form>

    <p style="text-align: center; font-size: 0.875rem; color: var(--text-secondary); margin-top: 1.5rem;">
      Already have an account? <a href="<?= ROOT_PATH ?>/login.php" style="font-weight: 600;">Sign in here</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
