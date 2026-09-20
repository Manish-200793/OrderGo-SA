<?php
/**
 * Admin Sidebar Component
 */
$adminPage = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="glass-card admin-sidebar">
  <div class="admin-sidebar-header">
    <h2>Admin Portal</h2>
  </div>
  <nav class="admin-nav">
    <a href="<?= ROOT_PATH ?>/admin/index.php" class="admin-nav-link <?= $adminPage === 'index.php' ? 'active' : '' ?>">
      <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i> Overview
    </a>
    <a href="<?= ROOT_PATH ?>/admin/menu.php" class="admin-nav-link <?= $adminPage === 'menu.php' ? 'active' : '' ?>">
      <i data-lucide="utensils-crossed" style="width: 18px; height: 18px;"></i> Menu Manager
    </a>
    <a href="<?= ROOT_PATH ?>/admin/orders.php" class="admin-nav-link <?= $adminPage === 'orders.php' ? 'active' : '' ?>">
      <i data-lucide="shopping-cart" style="width: 18px; height: 18px;"></i> All Orders
    </a>
    <a href="<?= ROOT_PATH ?>/admin/analytics.php" class="admin-nav-link <?= $adminPage === 'analytics.php' ? 'active' : '' ?>">
      <i data-lucide="trending-up" style="width: 18px; height: 18px;"></i> Analytics
    </a>
    <a href="<?= ROOT_PATH ?>/staff/index.php" class="admin-nav-link">
      <i data-lucide="chef-hat" style="width: 18px; height: 18px;"></i> Kitchen View
    </a>
  </nav>
</aside>
