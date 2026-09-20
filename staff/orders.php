<?php
/**
 * OrderGo - Staff Order Viewer
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('staff');

$db = get_db();

// Filter parameters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$sql = "
    SELECT o.*, u.email as user_email,
           COALESCE(o.guest_name, s.name, a.name, st.name) as customer_name,
           s.roll_number as customer_roll_number
    FROM odg_orders o
    JOIN odg_users u ON o.user_id = u.user_id
    LEFT JOIN odg_students s ON u.user_id = s.student_id
    LEFT JOIN odg_admins a ON u.user_id = a.admin_id
    LEFT JOIN odg_staff st ON u.user_id = st.staff_id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND DATE(o.created_at) = ?";
    $params[] = $dateFilter;
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Attach items to each order
foreach ($orders as &$ord) {
    $iStmt = $db->prepare("
        SELECT oi.*, mi.name 
        FROM odg_order_items oi 
        JOIN odg_menu_items mi ON oi.item_id = mi.item_id 
        WHERE oi.order_id = ?
    ");
    $iStmt->execute([$ord['order_id']]);
    $ord['items'] = $iStmt->fetchAll();
}
unset($ord);

$pageTitle = 'Campus Orders - Staff View';
$extraCss = ['admin.css']; // We reuse admin table styles
require __DIR__ . '/../includes/header.php';
?>

<div class="page" style="padding-top: 100px; padding-bottom: 60px;">
  <div class="container">
    <div class="page-header" style="margin-bottom: 2rem;">
      <h1 class="page-title">Campus Orders</h1>
      <p class="page-subtitle">Read-only view of all recent orders</p>
    </div>

    <!-- Filters Bar -->
    <div class="glass-card" style="padding: 1rem 1.5rem; margin-bottom: 1.5rem;">
      <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div>
          <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">Status</label>
          <select name="status" class="form-input" style="padding: 0.4rem 2rem 0.4rem 0.8rem; font-size: 0.85rem;" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="preparing" <?= $statusFilter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
            <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <div>
          <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">Date</label>
          <input type="date" name="date" class="form-input" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;" value="<?= htmlspecialchars($dateFilter) ?>" onchange="this.form.submit()">
        </div>

        <?php if (!empty($statusFilter) || !empty($dateFilter)): ?>
          <div style="margin-top: auto;">
            <a href="orders.php" class="btn btn-ghost btn-sm">Clear Filters</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Orders Table -->
    <div class="glass-card" style="padding: 1.5rem; overflow-x: auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Status</th>
            <th>Amount</th>
            <th>Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">No orders match the filter criteria.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td><strong>#<?= substr($order['order_id'], -6) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($order['customer_name'] || 'Guest') ?></strong>
                  <?php if ($order['customer_roll_number']): ?>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $order['customer_roll_number'] ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-size: 0.85rem; max-width: 250px;">
                    <?= implode(', ', array_map(fn($i) => "{$i['quantity']}x {$i['name']}", $order['items'])) ?>
                  </div>
                </td>
                <td>
                  <span class="badge badge-<?= strtolower($order['status']) ?>">
                    <?= ucfirst(htmlspecialchars($order['status'])) ?>
                  </span>
                </td>
                <td>
                  <strong><?= format_price($order['total_price']) ?></strong>
                  <div style="font-size: 0.75rem; color: var(--text-muted);"><?= strtoupper($order['payment_method']) ?></div>
                </td>
                <td>
                  <span style="font-size: 0.85rem;"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
