<?php
/**
 * OrderGo - Admin Analytics & Business Intelligence
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();

// 1. Daily Revenue (Last 7 days)
$revStmt = $db->query("
    SELECT DATE(created_at) as order_date, 
           COUNT(*) as orders_count, 
           COALESCE(SUM(total_price), 0) as revenue
    FROM odg_orders 
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) 
    ORDER BY order_date ASC
");
$dailyRevenue = $revStmt->fetchAll();

$revLabels = [];
$revData = [];
foreach ($dailyRevenue as $row) {
    $revLabels[] = date('M j', strtotime($row['order_date']));
    $revData[] = (float)$row['revenue'];
}

// 2. Peak Ordering Hours
$peakStmt = $db->query("
    SELECT HOUR(created_at) as hr, COUNT(*) as count 
    FROM odg_orders 
    WHERE status != 'cancelled'
    GROUP BY HOUR(created_at)
    ORDER BY hr ASC
");
$peakHours = $peakStmt->fetchAll();

$peakLabels = [];
$peakData = [];
foreach ($peakHours as $ph) {
    $hour = (int)$ph['hr'];
    $peakLabels[] = ($hour % 12 ?: 12) . ($hour >= 12 ? ' PM' : ' AM');
    $peakData[] = (int)$ph['count'];
}

// 3. Top Sellers
$topStmt = $db->query("
    SELECT mi.item_id, mi.name, mi.category, mi.price, mi.image_url,
           SUM(oi.quantity) as total_sold,
           COALESCE(AVG(f.rating), 0) as avg_rating
    FROM odg_order_items oi
    JOIN odg_menu_items mi ON oi.item_id = mi.item_id
    LEFT JOIN odg_feedback f ON mi.item_id = f.item_id
    GROUP BY mi.item_id
    ORDER BY total_sold DESC
    LIMIT 6
");
$topSellers = $topStmt->fetchAll();

$pageTitle = 'Analytics';
$extraCss = ['admin.css'];
$extraJs = ['analytics.js'];
require __DIR__ . '/../includes/header.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="admin-layout">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">
    <div class="page-header">
      <h1 class="page-title">Cafeteria Analytics</h1>
      <p class="page-subtitle">Revenue trends, peak canteen traffic, and dish popularity</p>
    </div>

    <!-- Revenue Chart -->
    <div class="glass-card" style="padding: 1.75rem; margin-bottom: 2rem;">
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">
        📈 Daily Revenue (Past 7 Days)
      </h2>
      <div style="width: 100%; height: 280px;">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
      <!-- Peak Hours Chart -->
      <div class="glass-card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">
          ⏰ Peak Ordering Hours
        </h2>
        <div style="width: 100%; height: 250px;">
          <canvas id="peakHoursChart"></canvas>
        </div>
      </div>

      <!-- Top Sellers Leaderboard -->
      <div class="glass-card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">
          🏆 Top Campus Sellers
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          <?php if (empty($topSellers)): ?>
            <p style="color: var(--text-muted); font-size: 0.85rem;">No sales data available yet.</p>
          <?php else: ?>
            <?php foreach ($topSellers as $idx => $ts): ?>
              <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem; background: var(--bg-secondary); border-radius: var(--radius-md);">
                <span style="font-weight: 800; width: 24px; color: <?= $idx < 3 ? 'var(--accent-primary)' : 'var(--text-muted)' ?>;">
                  #<?= $idx + 1 ?>
                </span>
                <img src="<?= get_image_url($ts['image_url']) ?>" style="width: 38px; height: 38px; border-radius: 6px; object-fit: cover;">
                <div style="flex: 1; min-width: 0;">
                  <strong style="font-size: 0.9rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= htmlspecialchars($ts['name']) ?>
                  </strong>
                  <span style="font-size: 0.75rem; color: var(--text-muted);"><?= format_price($ts['price']) ?></span>
                </div>
                <div style="text-align: right;">
                  <strong style="color: var(--accent-primary); font-size: 0.9rem;"><?= $ts['total_sold'] ?> sold</strong>
                  <div style="font-size: 0.75rem; color: #d97706;">★ <?= round($ts['avg_rating'], 1) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const revLabels = <?= json_encode($revLabels) ?>;
  const revData = <?= json_encode($revData) ?>;
  renderRevenueChart('revenueChart', revLabels.length ? revLabels : ['Today'], revData.length ? revData : [0]);

  const peakLabels = <?= json_encode($peakLabels) ?>;
  const peakData = <?= json_encode($peakData) ?>;
  renderPeakHoursChart('peakHoursChart', peakLabels.length ? peakLabels : ['12 PM'], peakData.length ? peakData : [0]);
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
