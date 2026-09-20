<?php
/**
 * OrderGo - Order Details & Live QR Pickup Tracker
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();
$orderId = $_GET['id'] ?? '';

$db = get_db();

// Admin and staff can inspect any order, students can only inspect their own
if ($user['role'] === 'admin' || $user['role'] === 'staff') {
    $stmt = $db->prepare("
        SELECT o.*, u.email as customer_email, 
               COALESCE(o.guest_name, s.name, a.name, st.name) as customer_name
        FROM odg_orders o
        JOIN odg_users u ON o.user_id = u.user_id
        LEFT JOIN odg_students s ON u.user_id = s.student_id
        LEFT JOIN odg_admins a ON u.user_id = a.admin_id
        LEFT JOIN odg_staff st ON u.user_id = st.staff_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$orderId]);
} else {
    $stmt = $db->prepare("
        SELECT o.*, u.email as customer_email,
               COALESCE(o.guest_name, s.name) as customer_name
        FROM odg_orders o
        JOIN odg_users u ON o.user_id = u.user_id
        LEFT JOIN odg_students s ON u.user_id = s.student_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stmt->execute([$orderId, $user['user_id']]);
}

$order = $stmt->fetch();
if (!$order) {
    die("<h1>Order not found</h1><p><a href='" . ROOT_PATH . "/orders.php'>Back to Orders</a></p>");
}

// Fetch order items
$iStmt = $db->prepare("
    SELECT oi.*, mi.name, mi.image_url, mi.category 
    FROM odg_order_items oi
    JOIN odg_menu_items mi ON oi.item_id = mi.item_id
    WHERE oi.order_id = ?
");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll();

// Status tracking step calculations
$statuses = ['pending', 'preparing', 'ready', 'completed'];
$currentStatusIdx = array_search($order['status'], $statuses);
if ($currentStatusIdx === false) $currentStatusIdx = 0;

$pageTitle = 'Order #' . substr($orderId, -6);
$extraCss = ['orders.css'];
require __DIR__ . '/includes/header.php';
?>

<!-- QR Code Library CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="page container" style="max-width: 800px;">
  <a href="<?= ROOT_PATH ?>/orders.php" class="btn btn-ghost btn-sm" style="margin-bottom: 1rem;">
    <i data-lucide="arrow-left"></i> Back to Orders
  </a>

  <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="page-title">Order #<?= substr($order['order_id'], -6) ?></h1>
      <p class="page-subtitle">Placed on <?= date('M j, Y • g:i A', strtotime($order['created_at'])) ?></p>
    </div>
    <div id="live-status-badge">
      <?= render_status_badge($order['status']) ?>
    </div>
  </div>

  <!-- Status Tracker -->
  <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem;">
    <div class="status-tracker">
      <div class="status-steps">
        <!-- Line 1 -->
        <div class="status-line <?= $currentStatusIdx >= 1 ? 'filled' : '' ?>" id="status-line-1" style="left: 12%; width: 25%;"></div>
        <!-- Line 2 -->
        <div class="status-line <?= $currentStatusIdx >= 2 ? 'filled' : '' ?>" id="status-line-2" style="left: 37%; width: 25%;"></div>
        <!-- Line 3 -->
        <div class="status-line <?= $currentStatusIdx >= 3 ? 'filled' : '' ?>" id="status-line-3" style="left: 62%; width: 25%;"></div>

        <!-- Step 1: Placed -->
        <div class="status-step <?= $currentStatusIdx >= 0 ? 'active' : '' ?> <?= $currentStatusIdx === 0 ? 'current' : '' ?>" id="status-step-0">
          <div class="status-dot"><i data-lucide="clock"></i></div>
          <span class="status-label">Order Placed</span>
        </div>

        <!-- Step 2: Preparing -->
        <div class="status-step <?= $currentStatusIdx >= 1 ? 'active' : '' ?> <?= $currentStatusIdx === 1 ? 'current' : '' ?>" id="status-step-1">
          <div class="status-dot"><i data-lucide="cooking-pot"></i></div>
          <span class="status-label">Preparing Food</span>
        </div>

        <!-- Step 3: Ready -->
        <div class="status-step <?= $currentStatusIdx >= 2 ? 'active' : '' ?> <?= $currentStatusIdx === 2 ? 'current' : '' ?>" id="status-step-2">
          <div class="status-dot"><i data-lucide="bell"></i></div>
          <span class="status-label">Ready for Pickup</span>
        </div>

        <!-- Step 4: Completed -->
        <div class="status-step <?= $currentStatusIdx >= 3 ? 'active' : '' ?> <?= $currentStatusIdx === 3 ? 'current' : '' ?>" id="status-step-3">
          <div class="status-dot"><i data-lucide="package-check"></i></div>
          <span class="status-label">Collected</span>
        </div>
      </div>
    </div>
  </div>

  <!-- QR Code Pickup Voucher OR Waiting Card OR Verified Pickup Success Card -->
  <?php if ($order['status'] === 'completed'): ?>
    <!-- Already Completed: Show Verified Success Tick Card Directly (No QR) -->
    <div class="pickup-success-card" id="pickup-success-container">
      <canvas class="success-confetti-canvas" id="confetti-canvas"></canvas>
      <div class="success-tick-wrapper">
        <div class="success-tick-ring"></div>
        <div class="success-tick-circle">
          <svg class="success-tick-svg" viewBox="0 0 52 52">
            <path class="success-tick-check" d="M14 27l8 8 16-16" />
          </svg>
        </div>
      </div>
      <div class="pickup-success-badge">
        <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> Verified & Collected
      </div>
      <h2 class="pickup-success-title">Order Collected Successfully! 🎉</h2>
      <p class="pickup-success-text">
        Your QR voucher was scanned and verified by the canteen staff. Enjoy your meal!
      </p>
      <div class="pickup-success-meta">
        <span>Voucher: <strong>#<?= substr($order['order_id'], -6) ?></strong></span>
        <span>•</span>
        <span>Pickup: <strong><?= ucfirst($order['pickup_type'] ?? 'Counter') ?></strong></span>
        <span>•</span>
        <span>Collected: <strong><?= date('g:i A', strtotime($order['updated_at'] ?? $order['created_at'])) ?></strong></span>
      </div>
      <div class="pickup-success-actions">
        <a href="<?= ROOT_PATH ?>/orders.php" class="btn btn-secondary btn-sm">
          <i data-lucide="receipt"></i> View All Orders
        </a>
        <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-sm">
          <i data-lucide="utensils"></i> Order More Food
        </a>
      </div>
    </div>

  <?php elseif ($order['status'] === 'ready'): ?>
    <!-- READY: Student can now show QR code for staff to scan -->
    <div class="qr-container" id="qr-voucher-container">
      <div class="wait-badge" style="background: rgba(16, 185, 129, 0.16); color: #10b981; border: 1.5px solid rgba(16, 185, 129, 0.35); margin-bottom: 0.75rem;">
        <i data-lucide="bell-ring" style="width: 14px; height: 14px;"></i> Ready for Collection
      </div>
      <h3 style="margin-bottom: 0.5rem; font-size: 1.4rem;">Show this QR Code at the counter</h3>
      <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
        Your meal is ready! Staff will scan this QR to hand over your tray.
      </p>

      <div class="qr-code-box" id="qrcode-box"></div>

      <div class="qr-pickup-id">#<?= substr($order['order_id'], -6) ?></div>
      <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; max-width: 320px;">
        Staff will scan this QR code with their kitchen camera to instantly verify and hand over your meal.
      </p>
    </div>

    <!-- Hidden Pickup Success Card: Pops up smoothly once staff scans the QR code -->
    <div class="pickup-success-card" id="pickup-success-container" style="display: none;">
      <canvas class="success-confetti-canvas" id="confetti-canvas"></canvas>
      <div class="success-tick-wrapper">
        <div class="success-tick-ring"></div>
        <div class="success-tick-circle">
          <svg class="success-tick-svg" viewBox="0 0 52 52">
            <path class="success-tick-check" d="M14 27l8 8 16-16" />
          </svg>
        </div>
      </div>
      <div class="pickup-success-badge">
        <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> Verified & Collected
      </div>
      <h2 class="pickup-success-title">Order Collected Successfully! 🎉</h2>
      <p class="pickup-success-text">
        Your QR voucher was just verified by the kitchen counter staff. Enjoy your meal!
      </p>
      <div class="pickup-success-meta">
        <span>Voucher: <strong>#<?= substr($order['order_id'], -6) ?></strong></span>
        <span>•</span>
        <span>Status: <strong style="color: #10b981;">Collected Just Now</strong></span>
      </div>
      <div class="pickup-success-actions">
        <a href="<?= ROOT_PATH ?>/orders.php" class="btn btn-secondary btn-sm">
          <i data-lucide="receipt"></i> View All Orders
        </a>
        <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-sm">
          <i data-lucide="utensils"></i> Order More Food
        </a>
      </div>
    </div>

  <?php elseif (in_array($order['status'], ['pending', 'preparing'])): ?>
    <!-- WAITING / COOKING STATE: NO QR CODE SHOWN TO STUDENT YET -->
    <div class="kitchen-wait-card state-<?= $order['status'] ?>" id="kitchen-wait-container">
      <div class="wait-icon-wrapper">
        <?php if ($order['status'] === 'preparing'): ?>
          <i data-lucide="cooking-pot" style="width: 42px; height: 42px;" id="wait-icon"></i>
        <?php else: ?>
          <i data-lucide="clock" style="width: 42px; height: 42px;" id="wait-icon"></i>
        <?php endif; ?>
      </div>

      <div class="wait-badge" id="wait-badge">
        <?php if ($order['status'] === 'preparing'): ?>
          <i data-lucide="flame" style="width: 14px; height: 14px;"></i> Cooking in Kitchen
        <?php else: ?>
          <i data-lucide="hourglass" style="width: 14px; height: 14px;"></i> Order Received
        <?php endif; ?>
      </div>

      <h2 class="wait-title" id="wait-title">
        <?= $order['status'] === 'preparing' ? 'Food is Being Prepared! 🍳' : 'Order Placed & Queued!' ?>
      </h2>

      <p class="wait-description" id="wait-desc">
        <?= $order['status'] === 'preparing' 
            ? 'Our kitchen chefs are actively preparing your hot meal. As soon as cooking is finished, your pickup QR code will unlock here automatically.' 
            : 'Your meal order has been submitted to the kitchen. Once staff accept and begin cooking, you can track it live right here.' ?>
      </p>

      <div class="wait-unlock-hint">
        <div class="wait-unlock-icon">
          <i data-lucide="lock" style="width: 20px; height: 20px;"></i>
        </div>
        <div class="wait-unlock-text">
          <strong>Pickup QR Code Unlocks on Ready:</strong><br>
          Your counter QR voucher will appear here automatically when the order is marked <strong>Ready for Pickup</strong>.
        </div>
      </div>

      <div class="wait-live-sync">
        <span class="pulse-dot"></span> Live updating with kitchen status (2s)
      </div>
    </div>

    <!-- Hidden QR Container: Pops up automatically the moment order becomes ready -->
    <div class="qr-container" id="qr-voucher-container" style="display: none;">
      <div class="wait-badge" style="background: rgba(16, 185, 129, 0.16); color: #10b981; border: 1.5px solid rgba(16, 185, 129, 0.35); margin-bottom: 0.75rem;">
        <i data-lucide="bell-ring" style="width: 14px; height: 14px;"></i> Ready for Collection
      </div>
      <h3 style="margin-bottom: 0.5rem; font-size: 1.4rem;">Show this QR Code at the counter</h3>
      <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
        Your meal is ready! Staff will scan this QR to hand over your tray.
      </p>

      <div class="qr-code-box" id="qrcode-box"></div>

      <div class="qr-pickup-id">#<?= substr($order['order_id'], -6) ?></div>
      <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; max-width: 320px;">
        Staff will scan this QR code with their kitchen camera to instantly verify and hand over your meal.
      </p>
    </div>

    <!-- Hidden Pickup Success Card: Pops up smoothly once staff scans the QR code -->
    <div class="pickup-success-card" id="pickup-success-container" style="display: none;">
      <canvas class="success-confetti-canvas" id="confetti-canvas"></canvas>
      <div class="success-tick-wrapper">
        <div class="success-tick-ring"></div>
        <div class="success-tick-circle">
          <svg class="success-tick-svg" viewBox="0 0 52 52">
            <path class="success-tick-check" d="M14 27l8 8 16-16" />
          </svg>
        </div>
      </div>
      <div class="pickup-success-badge">
        <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> Verified & Collected
      </div>
      <h2 class="pickup-success-title">Order Collected Successfully! 🎉</h2>
      <p class="pickup-success-text">
        Your QR voucher was just verified by the kitchen counter staff. Enjoy your meal!
      </p>
      <div class="pickup-success-meta">
        <span>Voucher: <strong>#<?= substr($order['order_id'], -6) ?></strong></span>
        <span>•</span>
        <span>Status: <strong style="color: #10b981;">Collected Just Now</strong></span>
      </div>
      <div class="pickup-success-actions">
        <a href="<?= ROOT_PATH ?>/orders.php" class="btn btn-secondary btn-sm">
          <i data-lucide="receipt"></i> View All Orders
        </a>
        <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-sm">
          <i data-lucide="utensils"></i> Order More Food
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Order Items Breakdown -->
  <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.5rem;">
      Order Items Breakdown
    </h3>
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
      <?php foreach ($items as $item): ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <strong style="color: var(--accent-primary); font-size: 1rem;"><?= $item['quantity'] ?>x</strong>
            <span><?= htmlspecialchars($item['name']) ?></span>
          </div>
          <span style="font-weight: 600;"><?= format_price($item['price_at_order'] * $item['quantity']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="border-top: 1px solid var(--border-subtle); margin-top: 1rem; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span style="font-size: 0.85rem; color: var(--text-muted);">Payment: <strong><?= strtoupper($order['payment_method']) ?></strong> (<?= ucfirst($order['payment_status']) ?>)</span>
      </div>
      <div>
        <span style="font-size: 1.25rem; font-weight: 800; color: var(--accent-primary-hover);"><?= format_price($order['total_price']) ?></span>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const qrBox = document.getElementById('qrcode-box');
  const qrDataText = '<?= addslashes($order['qr_code'] ?: "ORDERGO:{$order['order_id']}:{$order['user_id']}:{$order['total_price']}") ?>';

  function renderQRCodeIfNeeded() {
    if (qrBox && qrBox.children.length === 0) {
      new QRCode(qrBox, {
        text: qrDataText,
        width: 170,
        height: 170,
        colorDark : "#0F172A",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
      });
    }
  }

  // If already in ready state on initial page load, render QR code
  <?php if ($order['status'] === 'ready'): ?>
    renderQRCodeIfNeeded();
  <?php endif; ?>

  // Audio: Food Ready Bell
  function playFoodReadyChime() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      [1046.50, 1318.51, 1567.98].forEach((freq, idx) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.12);
        gain.gain.setValueAtTime(0.25, ctx.currentTime + idx * 0.12);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.12 + 0.5);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(ctx.currentTime + idx * 0.12);
        osc.stop(ctx.currentTime + idx * 0.12 + 0.5);
      });
    } catch(e) {}
  }

  // Audio: Pickup Verified Arpeggio
  function playPickupSuccessChime() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const notes = [523.25, 659.25, 783.99, 1046.50];
      notes.forEach((freq, idx) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.09);
        gain.gain.setValueAtTime(0.2, ctx.currentTime + idx * 0.09);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.09 + 0.35);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(ctx.currentTime + idx * 0.09);
        osc.stop(ctx.currentTime + idx * 0.09 + 0.35);
      });
    } catch(e) {}
  }

  // Confetti celebration animation
  function triggerConfetti() {
    const canvas = document.getElementById('confetti-canvas');
    if (!canvas) return;
    const parent = canvas.parentElement;
    canvas.width = parent.clientWidth || 360;
    canvas.height = parent.clientHeight || 280;
    const ctx = canvas.getContext('2d');
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f43f5e'];
    const particles = [];
    for (let i = 0; i < 48; i++) {
      particles.push({
        x: canvas.width / 2,
        y: canvas.height / 2 - 20,
        vx: (Math.random() - 0.5) * 9,
        vy: (Math.random() - 0.85) * 10,
        size: Math.random() * 6 + 4,
        color: colors[Math.floor(Math.random() * colors.length)],
        rotation: Math.random() * 360,
        vRot: (Math.random() - 0.5) * 12,
        alpha: 1,
        decay: Math.random() * 0.015 + 0.008
      });
    }

    function render() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      let alive = false;
      particles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.18;
        p.rotation += p.vRot;
        p.alpha -= p.decay;
        if (p.alpha > 0) {
          alive = true;
          ctx.save();
          ctx.translate(p.x, p.y);
          ctx.rotate((p.rotation * Math.PI) / 180);
          ctx.globalAlpha = Math.max(0, p.alpha);
          ctx.fillStyle = p.color;
          ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
          ctx.restore();
        }
      });
      if (alive) requestAnimationFrame(render);
    }
    requestAnimationFrame(render);
  }

  // Smooth live transition when food becomes READY (Unlock QR code!)
  function handleOrderReadyLive() {
    playFoodReadyChime();

    const waitContainer = document.getElementById('kitchen-wait-container');
    const qrContainer = document.getElementById('qr-voucher-container');

    if (waitContainer) {
      waitContainer.classList.add('disappearing');
      setTimeout(() => {
        waitContainer.style.display = 'none';
        if (qrContainer) {
          qrContainer.style.display = 'flex';
          renderQRCodeIfNeeded();
        }
      }, 380);
    } else if (qrContainer) {
      qrContainer.style.display = 'flex';
      renderQRCodeIfNeeded();
    }

    // Update top badge
    const badgeEl = document.getElementById('live-status-badge');
    if (badgeEl) {
      badgeEl.innerHTML = `<span class="badge badge-ready"><i data-lucide="check-circle-2"></i> Ready</span>`;
    }

    // Update status steps to step 2 (Ready)
    const line1 = document.getElementById('status-line-1');
    const line2 = document.getElementById('status-line-2');
    if (line1) line1.classList.add('filled');
    if (line2) line2.classList.add('filled');

    const step0 = document.getElementById('status-step-0');
    const step1 = document.getElementById('status-step-1');
    const step2 = document.getElementById('status-step-2');
    if (step0) { step0.classList.add('active'); step0.classList.remove('current'); }
    if (step1) { step1.classList.add('active'); step1.classList.remove('current'); }
    if (step2) { step2.classList.add('active', 'current'); }

    if (window.lucide) lucide.createIcons();
  }

  // Smooth live transition when staff scans student's QR code (Completed)
  function handleOrderCompletedLive() {
    playPickupSuccessChime();

    const qrContainer = document.getElementById('qr-voucher-container');
    const waitContainer = document.getElementById('kitchen-wait-container');
    const successContainer = document.getElementById('pickup-success-container');

    if (qrContainer) {
      qrContainer.classList.add('disappearing');
      setTimeout(() => {
        qrContainer.style.display = 'none';
        if (waitContainer) waitContainer.style.display = 'none';
        if (successContainer) {
          successContainer.style.display = 'flex';
          triggerConfetti();
        }
      }, 380);
    } else if (successContainer) {
      if (waitContainer) waitContainer.style.display = 'none';
      successContainer.style.display = 'flex';
      triggerConfetti();
    }

    // Update top live badge
    const badgeEl = document.getElementById('live-status-badge');
    if (badgeEl) {
      badgeEl.innerHTML = `<span class="badge badge-completed"><i data-lucide="package-check"></i> Completed</span>`;
    }

    // Update status steps to full completion
    for (let i = 1; i <= 3; i++) {
      const line = document.getElementById('status-line-' + i);
      if (line) line.classList.add('filled');
    }
    for (let i = 0; i < 3; i++) {
      const step = document.getElementById('status-step-' + i);
      if (step) {
        step.classList.add('active');
        step.classList.remove('current');
      }
    }
    const step3 = document.getElementById('status-step-3');
    if (step3) {
      step3.classList.add('active', 'current');
    }

    if (window.lucide) lucide.createIcons();
  }

  // If already completed on initial page load, trigger celebratory confetti
  <?php if ($order['status'] === 'completed'): ?>
    setTimeout(triggerConfetti, 350);
  <?php endif; ?>

  // Auto-poll status every 2 seconds while order is active
  <?php if (in_array($order['status'], ['pending', 'preparing', 'ready'])): ?>
    let currentStatus = '<?= $order['status'] ?>';
    const pollTimer = setInterval(async () => {
      try {
        const url = typeof apiUrl === 'function' ? apiUrl('orders.php?action=get_status&id=<?= $order['order_id'] ?>') : '/api/orders.php?action=get_status&id=<?= $order['order_id'] ?>';
        const res = await fetch(url);
        const data = await res.json();
        if (!data || !data.status) return;

        if (data.status === currentStatus) return;

        if (data.status === 'preparing' && currentStatus === 'pending') {
          currentStatus = 'preparing';
          const waitContainer = document.getElementById('kitchen-wait-container');
          if (waitContainer) {
            waitContainer.className = 'kitchen-wait-card state-preparing';
            const iconWrap = waitContainer.querySelector('.wait-icon-wrapper');
            if (iconWrap) iconWrap.innerHTML = `<i data-lucide="cooking-pot" style="width: 42px; height: 42px;"></i>`;
            const badge = document.getElementById('wait-badge');
            if (badge) badge.innerHTML = `<i data-lucide="flame" style="width: 14px; height: 14px;"></i> Cooking in Kitchen`;
            const title = document.getElementById('wait-title');
            if (title) title.innerHTML = `Food is Being Prepared! 🍳`;
            const desc = document.getElementById('wait-desc');
            if (desc) desc.innerHTML = `Our kitchen chefs are actively preparing your hot meal. As soon as cooking is finished, your pickup QR code will unlock here automatically.`;
          }
          const badgeEl = document.getElementById('live-status-badge');
          if (badgeEl) badgeEl.innerHTML = `<span class="badge badge-preparing"><i data-lucide="cooking-pot"></i> Preparing</span>`;
          const line1 = document.getElementById('status-line-1');
          if (line1) line1.classList.add('filled');
          const step0 = document.getElementById('status-step-0');
          if (step0) { step0.classList.add('active'); step0.classList.remove('current'); }
          const step1 = document.getElementById('status-step-1');
          if (step1) { step1.classList.add('active', 'current'); }
          if (window.lucide) lucide.createIcons();
        } else if (data.status === 'ready' && currentStatus !== 'ready') {
          currentStatus = 'ready';
          handleOrderReadyLive();
        } else if (data.status === 'completed' && currentStatus !== 'completed') {
          currentStatus = 'completed';
          clearInterval(pollTimer);
          handleOrderCompletedLive();
        } else if (data.status === 'cancelled') {
          window.location.reload();
        }
      } catch (e) {}
    }, 2000);
  <?php endif; ?>
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
