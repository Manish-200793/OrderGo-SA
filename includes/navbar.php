<?php
/**
 * Navigation Bar Component
 */
$user = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<nav class="navbar">
  <div class="container navbar-inner">
    <!-- Brand Logo -->
    <a href="<?= ROOT_PATH ?>/index.php" class="navbar-logo">
      <i data-lucide="utensils" style="width: 26px; height: 26px; color: var(--accent-primary);"></i>
      <span>Order<strong>Go</strong></span>
    </a>

    <!-- Mobile Menu Toggle -->
    <button class="navbar-toggle" onclick="document.querySelector('.navbar-links').classList.toggle('open')" aria-label="Toggle menu">
      <i data-lucide="menu"></i>
    </button>

    <!-- Navigation Links -->
    <div class="navbar-links">
      <?php if (!$user || ($user['role'] !== 'staff' && $user['role'] !== 'admin')): ?>
        <a href="<?= ROOT_PATH ?>/index.php" class="nav-link <?= $currentScript === 'index.php' ? 'active' : '' ?>">
          <i data-lucide="home" style="width: 16px; height: 16px;"></i> Home
        </a>
      <?php endif; ?>

      <?php if (!$user || $user['role'] !== 'staff'): ?>
        <a href="<?= ROOT_PATH ?>/menu.php" class="nav-link <?= $currentScript === 'menu.php' ? 'active' : '' ?>">
          <i data-lucide="book-open" style="width: 16px; height: 16px;"></i> Menu
        </a>
      <?php endif; ?>

      <?php if ($user && ($user['role'] === 'staff' || $user['role'] === 'admin')): ?>
        <a href="<?= ROOT_PATH ?>/staff/index.php" class="nav-link nav-link-admin <?= str_contains($_SERVER['SCRIPT_NAME'], '/staff/') ? 'active' : '' ?>">
          <i data-lucide="chef-hat" style="width: 16px; height: 16px;"></i> Staff Kitchen
        </a>
      <?php endif; ?>

      <?php if ($user && $user['role'] === 'admin'): ?>
        <a href="<?= ROOT_PATH ?>/admin/index.php" class="nav-link nav-link-admin <?= str_contains($_SERVER['SCRIPT_NAME'], '/admin/') ? 'active' : '' ?>">
          <i data-lucide="shield" style="width: 16px; height: 16px;"></i> Admin
        </a>
      <?php endif; ?>


      <!-- Cart -->
      <?php if (!$user || ($user['role'] !== 'staff' && $user['role'] !== 'admin')): ?>
        <a href="<?= ROOT_PATH ?>/cart.php" class="nav-link cart-link <?= $currentScript === 'cart.php' ? 'active' : '' ?>">
          <i data-lucide="shopping-bag" style="width: 18px; height: 18px;"></i> Cart
          <span class="cart-badge" style="display: none;">0</span>
        </a>
      <?php endif; ?>

      <!-- Theme Toggle -->
      <button class="nav-link" onclick="toggleTheme()" title="Toggle Dark/Light Mode" style="border:none; background:none; cursor:pointer;">
        <i data-lucide="sun" id="theme-icon-sun" style="width: 16px; height: 16px; display: none;"></i>
        <i data-lucide="moon" id="theme-icon-moon" style="width: 16px; height: 16px; display: none;"></i>
      </button>

      <!-- Auth Section -->
      <?php if ($user): ?>
        <?php if ($user['role'] === 'staff'): ?>
          <a href="<?= ROOT_PATH ?>/staff/orders.php" class="nav-link <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
            <i data-lucide="list-ordered" style="width: 16px; height: 16px;"></i> Orders
          </a>
        <?php elseif ($user['role'] === 'admin'): ?>
          <a href="<?= ROOT_PATH ?>/admin/orders.php" class="nav-link <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
            <i data-lucide="list-ordered" style="width: 16px; height: 16px;"></i> Orders
          </a>
        <?php else: ?>
          <a href="<?= ROOT_PATH ?>/orders.php" class="nav-link <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
            <i data-lucide="clock" style="width: 16px; height: 16px;"></i> Orders
          </a>
        <?php endif; ?>

        <div class="nav-user">
          <a href="<?= ROOT_PATH ?>/profile.php" class="nav-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>" title="User Profile">
            <i data-lucide="user" style="width: 16px; height: 16px;"></i>
            <span class="user-name"><?= htmlspecialchars($user['name'] ?: $user['email']) ?></span>
          </a>
          <a href="<?= ROOT_PATH ?>/logout.php" class="nav-link logout-btn" title="Logout">
            <i data-lucide="log-out" style="width: 16px; height: 16px;"></i>
          </a>
        </div>
      <?php else: ?>
        <div class="nav-auth">
          <a href="<?= ROOT_PATH ?>/login.php" class="btn btn-secondary btn-sm">Log In</a>
          <a href="<?= ROOT_PATH ?>/register.php" class="btn btn-primary btn-sm">Sign Up</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Mobile Bottom Navigation (Zomato Style) -->
<nav class="bottom-nav">
  <?php if (!$user || ($user['role'] !== 'staff' && $user['role'] !== 'admin')): ?>
    <a href="<?= ROOT_PATH ?>/index.php" class="bottom-nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
      <i data-lucide="home"></i>
      <span>Home</span>
    </a>
  <?php endif; ?>
  
  <?php if (!$user || $user['role'] !== 'staff'): ?>
    <a href="<?= ROOT_PATH ?>/menu.php" class="bottom-nav-item <?= $currentScript === 'menu.php' ? 'active' : '' ?>">
      <i data-lucide="book-open"></i>
      <span>Menu</span>
    </a>
  <?php endif; ?>
  
  <?php if (!$user || ($user['role'] !== 'staff' && $user['role'] !== 'admin')): ?>
    <a href="<?= ROOT_PATH ?>/cart.php" class="bottom-nav-item cart-link <?= $currentScript === 'cart.php' ? 'active' : '' ?>">
      <i data-lucide="shopping-bag"></i>
      <span>Cart</span>
      <span class="cart-badge" style="display: none;">0</span>
    </a>
  <?php endif; ?>
  <?php if ($user): ?>
    <?php if ($user['role'] === 'staff'): ?>
      <a href="<?= ROOT_PATH ?>/staff/orders.php" class="bottom-nav-item <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
        <i data-lucide="list-ordered"></i>
        <span>Orders</span>
      </a>
    <?php elseif ($user['role'] === 'admin'): ?>
      <a href="<?= ROOT_PATH ?>/admin/orders.php" class="bottom-nav-item <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
        <i data-lucide="list-ordered"></i>
        <span>Orders</span>
      </a>
    <?php else: ?>
      <a href="<?= ROOT_PATH ?>/orders.php" class="bottom-nav-item <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
        <i data-lucide="clock"></i>
        <span>Orders</span>
      </a>
    <?php endif; ?>
    <a href="<?= ROOT_PATH ?>/profile.php" class="bottom-nav-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
      <i data-lucide="user"></i>
      <span>Profile</span>
    </a>
  <?php else: ?>
    <a href="<?= ROOT_PATH ?>/login.php" class="bottom-nav-item">
      <i data-lucide="log-in"></i>
      <span>Login</span>
    </a>
  <?php endif; ?>
</nav>


<script>
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('ordergo_theme', next);
  updateThemeIcons(next);
}

function updateThemeIcons(theme) {
  const sun = document.getElementById('theme-icon-sun');
  const moon = document.getElementById('theme-icon-moon');
  if (sun && moon) {
    if (theme === 'dark') {
      sun.style.display = 'inline-block';
      moon.style.display = 'none';
    } else {
      sun.style.display = 'none';
      moon.style.display = 'inline-block';
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  updateThemeIcons(current);
});
</script>
