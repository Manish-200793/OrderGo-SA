<?php
/**
 * OrderGo - Cart & Checkout Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'My Cart';
$extraCss = ['cart.css'];

$isLoggedIn = is_logged_in();
$user = current_user();

require __DIR__ . '/includes/header.php';
?>

<div class="page container">
  <div class="page-header">
    <h1 class="page-title">Your Tray</h1>
    <p class="page-subtitle">Review items and confirm pickup payment</p>
  </div>

  <div id="cart-empty-view" class="glass-card empty-state" style="display: none; padding: 4rem 2rem;">
    <i data-lucide="shopping-bag" style="width: 56px; height: 56px; margin: 0 auto 1rem; color: var(--text-muted); opacity: 0.4;"></i>
    <h3>Your Tray is Empty</h3>
    <p style="margin-bottom: 1.5rem;">Looks like you haven't added any delicious canteen items yet.</p>
    <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-lg">Explore Menu &rarr;</a>
  </div>

  <div id="cart-content-view" class="cart-layout" style="display: none;">
    <!-- Items List -->
    <div class="cart-items" id="cart-items-container">
      <!-- Injected via JavaScript -->
    </div>

    <!-- Order Summary & Checkout -->
    <div class="glass-card cart-summary">
      <h2 class="summary-title">Order Summary</h2>
      
      <div class="summary-items">
        <div class="summary-line">
          <span>Items Subtotal</span>
          <span id="summary-subtotal">₹0</span>
        </div>
        <div class="summary-line">
          <span>Campus Fee</span>
          <span style="color: #10b981; font-weight: 600;">FREE</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-line summary-total">
          <span>Total Payable</span>
          <span id="summary-total">₹0</span>
        </div>
      </div>

      <div style="margin-top: 1.5rem;">
        <label class="payment-title">Payment Option</label>
        <div class="payment-methods">
          <div class="payment-option active" id="pay-upi" onclick="selectPayment('upi')">
            <input type="radio" name="payment_method" value="upi" checked style="display: none;">
            <i data-lucide="smartphone" style="width: 20px; height: 20px; color: var(--accent-primary);"></i>
            <div>
              <strong>Instant UPI / QR</strong>
              <small>Pay online via GPay, PhonePe, Paytm</small>
            </div>
          </div>

        </div>
      </div>

      <?php if ($isLoggedIn): ?>
        <button id="btn-checkout" class="btn btn-primary btn-lg w-full" onclick="handleCheckout()">
          Place Order <i data-lucide="arrow-right"></i>
        </button>
      <?php else: ?>
        <a href="<?= ROOT_PATH ?>/login.php?redirect=cart.php" class="btn btn-primary btn-lg w-full text-center">
          Log in to Place Order <i data-lucide="log-in"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
let selectedPayment = 'upi';

function selectPayment(method) {
  selectedPayment = method;
  document.getElementById('pay-upi').classList.toggle('active', method === 'upi');
  document.getElementById('pay-cash').classList.toggle('active', method === 'cash');
}

function renderCart() {
  const items = Cart.getItems();
  const emptyView = document.getElementById('cart-empty-view');
  const contentView = document.getElementById('cart-content-view');
  const container = document.getElementById('cart-items-container');

  if (items.length === 0) {
    emptyView.style.display = 'block';
    contentView.style.display = 'none';
    return;
  }

  emptyView.style.display = 'none';
  contentView.style.display = 'grid';

  container.innerHTML = items.map(item => `
    <div class="glass-card cart-item">
      <div class="cart-item-image">
        <img src="${item.image_url || 'assets/images/placeholder.jpg'}" alt="${item.name}">
      </div>
      <div class="cart-item-info">
        <h3>${item.name}</h3>
        <span class="cart-item-price">₹${item.price} each</span>
      </div>
      <div class="cart-item-controls">
        <div class="quantity-control">
          <button class="qty-btn" onclick="Cart.updateQuantity(${item.item_id}, ${item.quantity - 1}); renderCart();">-</button>
          <span class="qty-value">${item.quantity}</span>
          <button class="qty-btn" onclick="Cart.updateQuantity(${item.item_id}, ${item.quantity + 1}); renderCart();">+</button>
        </div>
        <div class="cart-item-total">₹${(item.price * item.quantity).toFixed(0)}</div>
        <button class="cart-item-remove" onclick="Cart.removeItem(${item.item_id}); renderCart();" title="Remove item">
          <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
        </button>
      </div>
    </div>
  `).join('');

  const total = Cart.getTotalPrice();
  document.getElementById('summary-subtotal').textContent = '₹' + total.toFixed(0);
  document.getElementById('summary-total').textContent = '₹' + total.toFixed(0);

  if (window.lucide) lucide.createIcons();
}

async function handleCheckout() {
  const items = Cart.getItems();
  if (items.length === 0) return;

  const btn = document.getElementById('btn-checkout');
  btn.disabled = true;
  btn.textContent = 'Processing Order...';

  try {
    const url = typeof apiUrl === 'function' ? apiUrl('orders.php') : '/api/orders.php';
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        items: items.map(i => ({ item_id: i.item_id, quantity: i.quantity })),
        payment_method: selectedPayment,
        pickup_type: 'pickup'
      })
    });

    const data = await res.json();
    if (data.success && data.order) {
      Cart.clear();
      if (selectedPayment === 'cash') {
        window.location.href = `order-detail.php?id=${data.order.order_id}`;
      } else {
        window.location.href = `payment.php?id=${data.order.order_id}`;
      }
    } else {
      alert(data.error || 'Failed to place order.');
      btn.disabled = false;
      btn.innerHTML = 'Place Order <i data-lucide="arrow-right"></i>';
      if (window.lucide) lucide.createIcons();
    }
  } catch (err) {
    alert('Network error placing order.');
    btn.disabled = false;
    btn.innerHTML = 'Place Order <i data-lucide="arrow-right"></i>';
    if (window.lucide) lucide.createIcons();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  renderCart();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
