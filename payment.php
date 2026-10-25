<?php
/**
 * OrderGo - Razorpay Official Payment Gateway
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/razorpay.php';

require_login();
$user = current_user();
$orderId = trim($_GET['id'] ?? '');

if (empty($orderId)) {
    header('Location: ' . ROOT_PATH . '/orders.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM odg_orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    die("<h1>Order not found</h1><p><a href='" . ROOT_PATH . "/orders.php'>View Orders</a></p>");
}

// If already paid, immediately go to order detail / live tracker
if ($order['payment_status'] === 'success') {
    header('Location: ' . ROOT_PATH . '/order-detail.php?id=' . urlencode($orderId));
    exit;
}

// Create Razorpay Order
$rzpRes = razorpay_create_order($order['order_id'], (float)$order['total_price']);
$razorpayOrderId = $rzpRes['success'] ? $rzpRes['razorpay_order_id'] : '';
$razorpayError = $rzpRes['success'] ? '' : ($rzpRes['error'] ?? 'Could not initialize gateway order');

$pageTitle = 'Checkout Order #' . substr($orderId, -6);
require __DIR__ . '/includes/header.php';
?>

<!-- Official Razorpay Standard Checkout SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div class="page container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh; padding: 2rem 1rem;">
  <div class="glass-card" style="width: 100%; max-width: 480px; padding: 2.5rem 2rem; text-align: center; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); position: relative; overflow: hidden;">
    
    <!-- Test Mode Badge -->
    <?php if (PAYMENT_MODE === 'test'): ?>
      <div style="background: rgba(234, 88, 12, 0.12); border: 1.5px dashed var(--accent-primary); border-radius: 9999px; padding: 6px 16px; margin: 0 auto 1.5rem; font-size: 0.8rem; color: var(--accent-primary); font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
        <i data-lucide="flask-conical" style="width: 15px; height: 15px;"></i> Razorpay Test Mode (Sandbox)
      </div>
    <?php endif; ?>

    <div style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; border-radius: 50%; background: var(--accent-tint); color: var(--accent-primary); margin-bottom: 1.25rem;">
      <i data-lucide="shield-check" style="width: 36px; height: 36px;"></i>
    </div>

    <h1 style="font-size: 1.65rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.35rem; font-family: var(--font-display);">
      OrderGo Checkout
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1.5rem;">
      Order Reference: <strong>#<?= substr($order['order_id'], -6) ?></strong>
    </p>

    <!-- Amount Card -->
    <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--border-subtle);">
      <span style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
        Total Amount Payable
      </span>
      <span style="font-size: 2.75rem; font-weight: 900; color: var(--accent-primary-hover); font-family: var(--font-display); line-height: 1.1; display: block;">
        <?= format_price($order['total_price']) ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; color: #10b981; margin-top: 8px; font-weight: 600;">
        <i data-lucide="lock" style="width: 12px; height: 12px;"></i> 256-Bit SSL Encrypted & Verified
      </span>
    </div>

    <!-- Supported Methods Banner -->
    <div style="background: var(--bg-tertiary, rgba(255,255,255,0.03)); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.5rem; text-align: left;">
      <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px;">
        Supported Payment Options
      </div>
      <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
        <span style="background: rgba(255,255,255,0.07); border-radius: 6px; padding: 4px 10px; font-size: 0.8rem; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 5px;">
          <i data-lucide="smartphone" style="width: 14px; height: 14px; color: #10b981;"></i> GPay / PhonePe / Paytm
        </span>
        <span style="background: rgba(255,255,255,0.07); border-radius: 6px; padding: 4px 10px; font-size: 0.8rem; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 5px;">
          <i data-lucide="qr-code" style="width: 14px; height: 14px; color: var(--accent-primary);"></i> UPI QR
        </span>
        <span style="background: rgba(255,255,255,0.07); border-radius: 6px; padding: 4px 10px; font-size: 0.8rem; font-weight: 600; color: var(--text-primary); display: inline-flex; align-items: center; gap: 5px;">
          <i data-lucide="credit-card" style="width: 14px; height: 14px; color: #3b82f6;"></i> Cards & Netbanking
        </span>
      </div>
    </div>

    <!-- Razorpay Main Trigger Button -->
    <button id="btn-pay-razorpay" class="btn btn-primary btn-lg w-full" style="width: 100%; font-size: 1.05rem; padding: 0.9rem; font-weight: 700;" onclick="launchRazorpay()">
      <span>Pay <?= format_price($order['total_price']) ?> via Razorpay</span>
      <i data-lucide="external-link"></i>
    </button>

    <!-- Presentation Quick Demo Link -->
    <?php if (PAYMENT_MODE === 'test'): ?>
      <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle);">
        <button type="button" class="btn btn-ghost btn-sm" style="font-size: 0.8rem; opacity: 0.8;" onclick="simulateFastDemoPayment()">
          ⚡ Quick Demo Instant Verification (1-Click)
        </button>
      </div>
    <?php endif; ?>

    <div style="margin-top: 1rem;">
      <a href="<?= ROOT_PATH ?>/orders.php" style="font-size: 0.8rem; color: var(--text-muted); text-decoration: none;">
        &larr; Return to Orders
      </a>
    </div>

  </div>
</div>

<script>
const orderDetails = {
  order_id: <?= json_encode($order['order_id']) ?>,
  total_price: <?= json_encode((float)$order['total_price']) ?>,
  amount_paise: <?= json_encode((int)round($order['total_price'] * 100)) ?>,
  razorpay_order_id: <?= json_encode($razorpayOrderId) ?>,
  key_id: <?= json_encode(RAZORPAY_KEY_ID) ?>,
  customer_name: <?= json_encode($user['name'] ?? 'Student') ?>,
  customer_email: <?= json_encode($user['email'] ?? '') ?>,
  customer_phone: <?= json_encode($user['phone'] ?? '') ?>
};

function launchRazorpay() {
  const btn = document.getElementById('btn-pay-razorpay');
  btn.disabled = true;
  btn.innerHTML = 'Connecting to Razorpay... <i data-lucide="loader"></i>';
  if (window.lucide) lucide.createIcons();

  const options = {
    key: orderDetails.key_id,
    amount: orderDetails.amount_paise,
    currency: 'INR',
    name: 'OrderGo Canteen',
    description: 'Meal Order #' + orderDetails.order_id.slice(-6),
    order_id: orderDetails.razorpay_order_id,
    prefill: {
      name: orderDetails.customer_name,
      email: orderDetails.customer_email,
      contact: orderDetails.customer_phone
    },
    notes: {
      order_id: orderDetails.order_id,
      system: 'OrderGo Campus'
    },
    theme: {
      color: '#EA580C'
    },
    handler: async function (response) {
      btn.innerHTML = 'Verifying Payment... <i data-lucide="loader"></i>';
      if (window.lucide) lucide.createIcons();

      try {
        const verifyRes = await fetch('<?= ROOT_PATH ?>/api/razorpay_verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            order_id: orderDetails.order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_signature: response.razorpay_signature
          })
        });

        const data = await verifyRes.json();
        if (data.success) {
          localStorage.removeItem('ordergo_cart');
          window.location.href = '<?= ROOT_PATH ?>/order-detail.php?id=' + encodeURIComponent(orderDetails.order_id);
        } else {
          alert(data.error || 'Signature verification failed.');
          btn.disabled = false;
          btn.innerHTML = '<span>Pay ' + <?= json_encode(format_price($order['total_price'])) ?> + ' via Razorpay</span> <i data-lucide="external-link"></i>';
          if (window.lucide) lucide.createIcons();
        }
      } catch (err) {
        alert('Network error while verifying payment.');
        btn.disabled = false;
        btn.innerHTML = '<span>Pay ' + <?= json_encode(format_price($order['total_price'])) ?> + ' via Razorpay</span> <i data-lucide="external-link"></i>';
        if (window.lucide) lucide.createIcons();
      }
    },
    modal: {
      ondismiss: function () {
        btn.disabled = false;
        btn.innerHTML = '<span>Pay ' + <?= json_encode(format_price($order['total_price'])) ?> + ' via Razorpay</span> <i data-lucide="external-link"></i>';
        if (window.lucide) lucide.createIcons();
      }
    }
  };

  try {
    const rzp = new Razorpay(options);
    rzp.on('payment.failed', function (response) {
      alert('Payment failed: ' + (response.error.description || 'Unknown error'));
      btn.disabled = false;
      btn.innerHTML = '<span>Pay ' + <?= json_encode(format_price($order['total_price'])) ?> + ' via Razorpay</span> <i data-lucide="external-link"></i>';
      if (window.lucide) lucide.createIcons();
    });
    rzp.open();
  } catch (e) {
    alert('Failed to launch Razorpay gateway: ' + e.message);
    btn.disabled = false;
    btn.innerHTML = '<span>Pay ' + <?= json_encode(format_price($order['total_price'])) ?> + ' via Razorpay</span> <i data-lucide="external-link"></i>';
    if (window.lucide) lucide.createIcons();
  }
}

async function simulateFastDemoPayment() {
  if (!confirm('Run instant Sandbox Demo Payment for this order?')) return;

  const btn = document.getElementById('btn-pay-razorpay');
  btn.disabled = true;
  btn.innerHTML = 'Verifying Demo Payment... <i data-lucide="loader"></i>';
  if (window.lucide) lucide.createIcons();

  try {
    const res = await fetch('<?= ROOT_PATH ?>/api/razorpay_verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderDetails.order_id,
        simulate: true
      })
    });

    const data = await res.json();
    if (data.success) {
      localStorage.removeItem('ordergo_cart');
      window.location.href = '<?= ROOT_PATH ?>/order-detail.php?id=' + encodeURIComponent(orderDetails.order_id);
    } else {
      alert(data.error || 'Demo simulation failed');
      btn.disabled = false;
      btn.innerHTML = '<span>Pay via Razorpay</span> <i data-lucide="external-link"></i>';
      if (window.lucide) lucide.createIcons();
    }
  } catch (err) {
    alert('Network error during demo payment.');
    btn.disabled = false;
    btn.innerHTML = '<span>Pay via Razorpay</span> <i data-lucide="external-link"></i>';
    if (window.lucide) lucide.createIcons();
  }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
