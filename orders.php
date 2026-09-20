<?php
/**
 * OrderGo - Student Order History Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();
$db = get_db();

$tab = $_GET['tab'] ?? 'active';

if ($tab === 'past') {
    $stmt = $db->prepare("SELECT * FROM odg_orders WHERE user_id = ? AND status IN ('completed', 'cancelled') ORDER BY created_at DESC");
} else {
    $stmt = $db->prepare("SELECT * FROM odg_orders WHERE user_id = ? AND status IN ('pending', 'preparing', 'ready') ORDER BY created_at DESC");
}
$stmt->execute([$user['user_id']]);
$orders = $stmt->fetchAll();

// Attach items to each order
foreach ($orders as &$ord) {
    $iStmt = $db->prepare("
        SELECT oi.*, mi.name, mi.image_url 
        FROM odg_order_items oi 
        JOIN odg_menu_items mi ON oi.item_id = mi.item_id 
        WHERE oi.order_id = ?
    ");
    $iStmt->execute([$ord['order_id']]);
    $ord['items'] = $iStmt->fetchAll();
}
unset($ord);

$pageTitle = 'My Orders';
$extraCss = ['orders.css'];
require __DIR__ . '/includes/header.php';
?>

<div class="page container">
  <div class="page-header" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem;">
    <div style="flex: 1; min-width: 250px;">
      <h1 class="page-title">My Orders</h1>
      <p class="page-subtitle">Track your active meals and review order history</p>
    </div>
    <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-sm" style="flex-shrink: 0; margin-top: auto;">
      <i data-lucide="plus"></i> Order More Food
    </a>
  </div>

  <!-- Order Tabs -->
  <div class="orders-tabs">
    <a href="?tab=active" class="btn <?= $tab === 'active' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
      Active Orders
    </a>
    <a href="?tab=past" class="btn <?= $tab === 'past' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
      Past / Completed
    </a>
  </div>

  <!-- Orders List -->
  <div class="orders-list">
    <?php if (empty($orders)): ?>
      <div class="glass-card empty-state" style="padding: 4rem 2rem;">
        <i data-lucide="inbox" style="width: 56px; height: 56px; margin: 0 auto 1rem; color: var(--text-muted); opacity: 0.4;"></i>
        <h3>No <?= $tab === 'active' ? 'Active' : 'Past' ?> Orders</h3>
        <p style="margin-bottom: 1.5rem;">
          <?= $tab === 'active' ? "You don't have any meals cooking right now." : "You haven't completed any orders yet." ?>
        </p>
        <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-sm">Order Food Now</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $order): ?>
        <div class="glass-card order-card" onclick="window.location.href='order-detail.php?id=<?= $order['order_id'] ?>'" style="cursor: pointer; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem;">
          <div class="order-card-info" style="flex: 1; min-width: 200px;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 4px; flex-wrap: wrap;">
              <span class="order-card-id">#<?= substr($order['order_id'], -6) ?></span>
              <?= render_status_badge($order['status']) ?>
            </div>
            <span class="order-card-meta">
              <?= date('D, M j, Y • g:i A', strtotime($order['created_at'])) ?>
            </span>
            <div style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">
              <?= implode(', ', array_map(fn($i) => "{$i['quantity']}x {$i['name']}", $order['items'])) ?>
            </div>
          </div>

          <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; flex-shrink: 0;">
            <span class="order-card-price"><?= format_price($order['total_price']) ?></span>
            <span class="btn btn-secondary btn-sm" style="pointer-events: none;">
              <?= $order['status'] === 'completed' ? 'View Details <i data-lucide="check"></i>' : 'Track & QR <i data-lucide="arrow-right"></i>' ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
