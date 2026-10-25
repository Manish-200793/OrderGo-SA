<?php
/**
 * OrderGo - Unified Payment Gateway
 * Supports Dual Mode: Instant UPI & QR + Official Razorpay Gateway
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

// Create Razorpay Order in the background
$rzpRes = razorpay_create_order($order['order_id'], (float)$order['total_price']);
$razorpayOrderId = $rzpRes['success'] ? $rzpRes['razorpay_order_id'] : '';
$razorpayError = $rzpRes['success'] ? '' : ($rzpRes['error'] ?? 'Could not initialize gateway order');

$pageTitle = 'Pay for Order #' . substr($orderId, -6);
require __DIR__ . '/includes/header.php';
?>

<!-- Official Razorpay Standard Checkout SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div class="page container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh; padding: 2rem 1rem;">
  <div class="glass-card" style="width: 100%; max-width: 490px; padding: 2rem 1.75rem; text-align: center; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); position: relative;">
    
    <!-- Header -->
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; border-radius: 50%; background: var(--accent-tint); color: var(--accent-primary); margin-bottom: 1rem;">
      <i data-lucide="shield-check" style="width: 32px; height: 32px;"></i>
    </div>

    <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.25rem; font-family: var(--font-display);">
      OrderGo Checkout
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 1.25rem;">
      Order Reference: <strong>#<?= substr($order['order_id'], -6) ?></strong>
    </p>

    <!-- Amount Card -->
    <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 1.25rem 1rem; margin-bottom: 1.25rem; border: 1px solid var(--border-subtle);">
      <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
        Total Amount Payable
      </span>
      <span style="font-size: 2.5rem; font-weight: 900; color: var(--accent-primary-hover); font-family: var(--font-display); line-height: 1.1; display: block;">
        <?= format_price($order['total_price']) ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; color: #10b981; margin-top: 6px; font-weight: 600;">
        <i data-lucide="lock" style="width: 12px; height: 12px;"></i> 256-Bit Encrypted Campus Payment
      </span>
    </div>

    <!-- Mode Toggle Tabs -->
    <div style="display: flex; background: var(--bg-tertiary, rgba(255,255,255,0.04)); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 4px; margin-bottom: 1.5rem; gap: 4px;">
      <button type="button" id="tab-btn-upi" class="btn btn-sm" style="flex: 1; border-radius: 8px; background: var(--accent-primary); color: #fff; font-weight: 700; transition: all 0.2s;" onclick="switchPaymentTab('upi')">
        <i data-lucide="smartphone" style="width: 14px; height: 14px; vertical-align: middle;"></i> Instant UPI & QR
      </button>
      <button type="button" id="tab-btn-rzp" class="btn btn-sm btn-ghost" style="flex: 1; border-radius: 8px; font-weight: 600; transition: all 0.2s;" onclick="switchPaymentTab('rzp')">
        <i data-lucide="credit-card" style="width: 14px; height: 14px; vertical-align: middle;"></i> Razorpay Gateway
      </button>
    </div>

    <!-- TAB 1: UPI & QR Code (Active by Default) -->
    <div id="view-tab-upi">
      <div style="margin-bottom: 1.25rem;">
        <div style="display: inline-block; background: #FFFFFF; padding: 12px; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: 2px solid var(--border-subtle);">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi://pay?pa=ordergo@canteen%26pn=OrderGo%20Canteen%26am=<?= $order['total_price'] ?>%26cu=INR" alt="UPI QR" style="width: 160px; height: 160px; display: block;">
        </div>
        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.6rem; font-weight: 500;">
          Scan with <strong>Google Pay, PhonePe, Paytm</strong> or any UPI App
        </p>
      </div>

      <!-- Mobile Direct UPI App Link -->
      <div style="margin-bottom: 1rem;">
        <a href="upi://pay?pa=ordergo@canteen&pn=OrderGo%20Canteen&am=<?= $order['total_price'] ?>&cu=INR" class="btn btn-secondary w-full" style="width: 100%; font-size: 0.9rem; padding: 0.75rem; border-radius: 10px; display: inline-flex; justify-content: center; align-items: center; gap: 6px;">
          <i data-lucide="external-link" style="width: 16px; height: 16px; color: var(--accent-primary);"></i> Open in Installed UPI App
        </a>
      </div>

      <!-- Verification Button -->
      <button id="btn-verify-upi" class="btn btn-primary btn-lg w-full" style="width: 100%; font-size: 1rem; font-weight: 700; padding: 0.85rem;" onclick="confirmUpiPayment()">
        <span>Simulate Successful Payment</span>
        <i data-lucide="check-circle-2"></i>
      </button>
    </div>

    <!-- TAB 2: Official Razorpay Gateway (Cards & Netbanking) -->
    <div id="view-tab-rzp" style="display: none;">
      <?php if (PAYMENT_MODE === 'test'): ?>
        <div style="background: rgba(234, 88, 12, 0.12); border: 1.5px dashed var(--accent-primary); border-radius: 8px; padding: 6px 12px; margin-bottom: 1rem; font-size: 0.75rem; color: var(--accent-primary); font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
          <i data-lucide="flask-conical" style="width: 14px; height: 14px;"></i> Razorpay Test Mode Active
        </div>
      <?php endif; ?>

      <div style="background: var(--bg-secondary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1.25rem 1rem; margin-bottom: 1.25rem; text-align: left;">
        <p style="font-size: 0.85rem; color: var(--text-primary); margin-bottom: 0.75rem; font-weight: 600;">
          Launch the official Razorpay Checkout popup to pay via:
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8rem; color: var(--text-secondary);">
          <div><i data-lucide="credit-card" style="width: 14px; height: 14px; color: #3b82f6; vertical-align: middle;"></i> Debit & Credit Cards (Visa, Mastercard, RuPay)</div>
          <div><i data-lucide="landmark" style="width: 14px; height: 14px; color: #10b981; vertical-align: middle;"></i> Netbanking (All Indian Banks)</div>
          <div><i data-lucide="wallet" style="width: 14px; height: 14px; color: #f59e0b; vertical-align: middle;"></i> Digital Wallets</div>
        </div>
      </div>

      <button id="btn-pay-razorpay" class="btn btn-primary btn-lg w-full" style="width: 100%; font-size: 1rem; font-weight: 700; padding: 0.85rem;" onclick="launchRazorpay()">
        <span>Open Razorpay Checkout</span>
        <i data-lucide="external-link"></i>
      </button>
    </div>

    <!-- Back to Orders -->
    <div style="margin-top: 1.25rem;">
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

function switchPaymentTab(tab) {
  const tabUpiBtn = document.getElementById('tab-btn-upi');
  const tabRzpBtn = document.getElementById('tab-btn-rzp');
  const viewUpi = document.getElementById('view-tab-upi');
  const viewRzp = document.getElementById('view-tab-rzp');

  if (tab === 'upi') {
    tabUpiBtn.style.background = 'var(--accent-primary)';
    tabUpiBtn.style.color = '#fff';
    tabUpiBtn.classList.remove('btn-ghost');

    tabRzpBtn.style.background = 'transparent';
    tabRzpBtn.style.color = 'var(--text-secondary)';
    tabRzpBtn.classList.add('btn-ghost');

    viewUpi.style.display = 'block';
    viewRzp.style.display = 'none';
  } else {
    tabRzpBtn.style.background = 'var(--accent-primary)';
    tabRzpBtn.style.color = '#fff';
    tabRzpBtn.classList.remove('btn-ghost');

    tabUpiBtn.style.background = 'transparent';
    tabUpiBtn.style.color = 'var(--text-secondary)';
    tabUpiBtn.classList.add('btn-ghost');

    viewUpi.style.display = 'none';
    viewRzp.style.display = 'block';
  }
  if (window.lucide) lucide.createIcons();
}

async function confirmUpiPayment() {
  const btn = document.getElementById('btn-verify-upi');
  btn.disabled = true;
  btn.innerHTML = 'Verifying UPI Payment... <i data-lucide="loader"></i>';
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
      alert(data.error || 'Payment confirmation failed');
      btn.disabled = false;
      btn.innerHTML = '<span>Simulate Successful Payment</span> <i data-lucide="check-circle-2"></i>';
      if (window.lucide) lucide.createIcons();
    }
  } catch (err) {
    alert('Network error while confirming payment.');
    btn.disabled = false;
    btn.innerHTML = '<span>Simulate Successful Payment</span> <i data-lucide="check-circle-2"></i>';
    if (window.lucide) lucide.createIcons();
  }
}

function launchRazorpay() {
  const btn = document.getElementById('btn-pay-razorpay');
  btn.disabled = true;
  btn.innerHTML = 'Opening Razorpay... <i data-lucide="loader"></i>';
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
      btn.innerHTML = 'Verifying Gateway Signature... <i data-lucide="loader"></i>';
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
          btn.innerHTML = '<span>Open Razorpay Checkout</span> <i data-lucide="external-link"></i>';
          if (window.lucide) lucide.createIcons();
        }
      } catch (err) {
        alert('Network error while verifying payment.');
        btn.disabled = false;
        btn.innerHTML = '<span>Open Razorpay Checkout</span> <i data-lucide="external-link"></i>';
        if (window.lucide) lucide.createIcons();
      }
    },
    modal: {
      ondismiss: function () {
        btn.disabled = false;
        btn.innerHTML = '<span>Open Razorpay Checkout</span> <i data-lucide="external-link"></i>';
        if (window.lucide) lucide.createIcons();
      }
    }
  };

  try {
    const rzp = new Razorpay(options);
    rzp.on('payment.failed', function (response) {
      alert('Payment failed: ' + (response.error.description || 'Unknown error'));
      btn.disabled = false;
      btn.innerHTML = '<span>Open Razorpay Checkout</span> <i data-lucide="external-link"></i>';
      if (window.lucide) lucide.createIcons();
    });
    rzp.open();
  } catch (e) {
    alert('Failed to launch Razorpay gateway: ' + e.message);
    btn.disabled = false;
    btn.innerHTML = '<span>Open Razorpay Checkout</span> <i data-lucide="external-link"></i>';
    if (window.lucide) lucide.createIcons();
  }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
