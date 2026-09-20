<?php
/**
 * OrderGo - Student Profile Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();
$db = get_db();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $rollNumber = trim($_POST['roll_number'] ?? '');

    if (empty($name)) {
        $err = 'Name cannot be empty.';
    } else {
        if ($user['role'] === 'student') {
            $stmt = $db->prepare("UPDATE odg_students SET name = ?, phone = ?, roll_number = ? WHERE student_id = ?");
            $stmt->execute([$name, $phone, $rollNumber, $user['user_id']]);
        } elseif ($user['role'] === 'admin') {
            $stmt = $db->prepare("UPDATE odg_admins SET name = ?, phone = ? WHERE admin_id = ?");
            $stmt->execute([$name, $phone, $user['user_id']]);
        } elseif ($user['role'] === 'staff') {
            $stmt = $db->prepare("UPDATE odg_staff SET name = ?, phone = ? WHERE staff_id = ?");
            $stmt->execute([$name, $phone, $user['user_id']]);
        }

        // Update session
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;
        $_SESSION['user']['roll_number'] = $rollNumber;
        $user = current_user();
        $msg = 'Profile updated successfully!';
    }
}

// Fetch stats
$statsStmt = $db->prepare("
    SELECT COUNT(*) as total_orders, COALESCE(SUM(total_price), 0) as total_spent 
    FROM odg_orders 
    WHERE user_id = ? AND status != 'cancelled'
");
$statsStmt->execute([$user['user_id']]);
$stats = $statsStmt->fetch();

$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>

<div class="page container" style="max-width: 650px;">
  <div class="page-header">
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage campus account credentials and view your order summary</p>
  </div>

  <?php if (!empty($msg)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #059669; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($err)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
      <?= htmlspecialchars($err) ?>
    </div>
  <?php endif; ?>

  <!-- Stats Banner -->
  <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
    <div class="glass-card" style="padding: 1.5rem; text-align: center; flex: 1; min-width: 140px;">
      <span style="font-size: 2rem; font-weight: 800; color: var(--accent-primary); font-family: var(--font-display);">
        <?= $stats['total_orders'] ?>
      </span>
      <span style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Orders Placed</span>
    </div>
    <div class="glass-card" style="padding: 1.5rem; text-align: center; flex: 1; min-width: 140px;">
      <span style="font-size: 2rem; font-weight: 800; color: #10b981; font-family: var(--font-display);">
        <?= format_price($stats['total_spent']) ?>
      </span>
      <span style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Total Spent</span>
    </div>
  </div>

  <!-- Profile Form -->
  <div class="glass-card" style="padding: 2rem;">
    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="email">Campus Email (Read Only)</label>
        <input type="email" id="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background: var(--bg-secondary); cursor: not-allowed;">
      </div>

      <div class="form-group">
        <label class="form-label" for="name">Full Name</label>
        <input type="text" id="name" name="name" class="form-input" value="<?= htmlspecialchars($user['name']) ?>" required>
      </div>

      <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
        <div class="form-group" style="flex: 1; min-width: 200px;">
          <label class="form-label" for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" class="form-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>

        <div class="form-group" style="flex: 1; min-width: 200px;">
          <label class="form-label" for="roll_number">Roll Number</label>
          <input type="text" id="roll_number" name="roll_number" class="form-input" value="<?= htmlspecialchars($user['roll_number'] ?? '') ?>">
        </div>
      </div>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
        <a href="<?= ROOT_PATH ?>/logout.php" class="btn btn-danger btn-sm">
          <i data-lucide="log-out"></i> Log Out
        </a>
        <button type="submit" class="btn btn-primary btn-md">
          Save Changes <i data-lucide="check"></i>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
