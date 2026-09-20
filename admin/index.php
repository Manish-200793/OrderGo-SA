<?php
/**
 * OrderGo - Admin Dashboard Overview
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();

// Metrics
$mStmt = $db->query("
    SELECT COUNT(*) as total_orders, 
           COALESCE(SUM(total_price), 0) as total_revenue,
           COALESCE(AVG(total_price), 0) as avg_order_value 
    FROM odg_orders 
    WHERE status != 'cancelled'
");
$metrics = $mStmt->fetch();

$uStmt = $db->query("SELECT COUNT(*) as total_students FROM odg_students");
$userStats = $uStmt->fetch();

// Recent orders
$rStmt = $db->query("
    SELECT o.*, COALESCE(o.guest_name, s.name, a.name, st.name) as customer_name
    FROM odg_orders o
    JOIN odg_users u ON o.user_id = u.user_id
    LEFT JOIN odg_students s ON u.user_id = s.student_id
    LEFT JOIN odg_admins a ON u.user_id = a.admin_id
    LEFT JOIN odg_staff st ON u.user_id = st.staff_id
    ORDER BY o.created_at DESC
    LIMIT 8
");
$recentOrders = $rStmt->fetchAll();

$pageTitle = 'Admin Dashboard';
$extraCss = ['admin.css'];
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end;">
      <div>
        <h1 class="page-title">Executive Dashboard</h1>
        <p class="page-subtitle">Campus cafeteria metrics, sales, and activity monitoring</p>
      </div>
      <a href="<?= ROOT_PATH ?>/admin/menu.php" class="btn btn-primary btn-sm">
        <i data-lucide="plus"></i> Add New Menu Item
      </a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="glass-card stat-card">
        <div class="stat-value"><?= $metrics['total_orders'] ?></div>
        <div class="stat-label">Total Orders</div>
      </div>
      <div class="glass-card stat-card">
        <div class="stat-value" style="color: #10b981;"><?= format_price($metrics['total_revenue']) ?></div>
        <div class="stat-label">Total Revenue</div>
      </div>
      <div class="glass-card stat-card">
        <div class="stat-value" style="color: #3b82f6;"><?= format_price($metrics['avg_order_value']) ?></div>
        <div class="stat-label">Avg Order Value</div>
      </div>
      <div class="glass-card stat-card">
        <div class="stat-value" style="color: #8b5cf6;"><?= $userStats['total_students'] ?></div>
        <div class="stat-label">Registered Students</div>
      </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="glass-card" style="padding: 1.5rem; overflow-x: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.2rem; font-weight: 700;">Recent Campus Orders</h2>
        <a href="<?= ROOT_PATH ?>/admin/orders.php" class="btn btn-secondary btn-sm">View All Orders &rarr;</a>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Amount</th>
            <th>Time</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
            <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No orders recorded yet.</td></tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $ro): ?>
              <tr>
                <td><strong>#<?= substr($ro['order_id'], -6) ?></strong></td>
                <td><?= htmlspecialchars($ro['customer_name'] || 'Guest') ?></td>
                <td><?= render_status_badge($ro['status']) ?></td>
                <td><span style="text-transform: uppercase; font-size: 0.8rem;"><?= $ro['payment_method'] ?></span> (<?= $ro['payment_status'] ?>)</td>
                <td><strong><?= format_price($ro['total_price']) ?></strong></td>
                <td><?= date('g:i A', strtotime($ro['created_at'])) ?></td>
                <td>
                  <a href="<?= ROOT_PATH ?>/order-detail.php?id=<?= $ro['order_id'] ?>" class="btn btn-ghost btn-sm" title="View details">
                    <i data-lucide="eye"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
