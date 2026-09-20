<?php
/**
 * OrderGo - Payment Simulation Gateway
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();
$orderId = $_GET['id'] ?? '';

$db = get_db();
$stmt = $db->prepare("SELECT * FROM odg_orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    die("<h1>Order not found</h1><p><a href='" . ROOT_PATH . "/orders.php'>View Orders</a></p>");
}

$pageTitle = 'Pay for Order #' . substr($orderId, -6);
require __DIR__ . '/includes/header.php';
?>

<div class="page container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
  <div class="glass-card" style="width: 100%; max-width: 460px; padding: 2.5rem; text-align: center;">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--accent-tint); color: var(--accent-primary); margin-bottom: 1.5rem;">
      <i data-lucide="smartphone" style="width: 32px; height: 32px;"></i>
    </div>

    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem;">UPI Payment</h1>
    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">
      Order Reference: <strong>#<?= substr($order['order_id'], -6) ?></strong>
    </p>

    <div style="background: var(--bg-secondary); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--border-subtle);">
      <span style="font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Total Amount</span>
      <span style="font-size: 2.5rem; font-weight: 900; color: var(--accent-primary-hover); font-family: var(--font-display);">
        <?= format_price($order['total_price']) ?>
      </span>
      <span style="display: block; font-size: 0.75rem; color: #10b981; margin-top: 4px; font-weight: 600;">
        ⚡ Instant UPI Verification
      </span>
    </div>

    <!-- Mock QR Code visual -->
    <div style="margin-bottom: 1.5rem;">
      <div style="display: inline-block; background: #FFFFFF; padding: 12px; border-radius: 12px; border: 1px solid var(--border-subtle);">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi://pay?pa=ordergo@canteen&pn=OrderGo&am=<?= $order['total_price'] ?>" alt="UPI QR" style="width: 160px; height: 160px;">
      </div>
      <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Scan using GPay, PhonePe, or Paytm</p>
    </div>

    <button id="btn-pay-now" class="btn btn-primary btn-lg w-full" onclick="confirmPayment('<?= $order['order_id'] ?>')">
      Simulate Successful Payment <i data-lucide="check-circle-2"></i>
    </button>

    <div style="margin-top: 1rem;">
      <a href="<?= ROOT_PATH ?>/orders.php" style="font-size: 0.85rem; color: var(--text-muted);">Pay later in Cash at counter</a>
    </div>
  </div>
</div>

<script>
async function confirmPayment(orderId) {
  const btn = document.getElementById('btn-pay-now');
  btn.disabled = true;
  btn.textContent = 'Verifying Transaction...';

  try {
    const url = typeof apiUrl === 'function' ? apiUrl(`orders.php?action=pay&id=${orderId}`) : `/api/orders.php?action=pay&id=${orderId}`;
    const res = await fetch(url, { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      window.location.href = `order-detail.php?id=${orderId}`;
    } else {
      alert(data.error || 'Payment confirmation failed');
      btn.disabled = false;
      btn.textContent = 'Try Again';
    }
  } catch (e) {
    alert('Network error');
    btn.disabled = false;
  }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
