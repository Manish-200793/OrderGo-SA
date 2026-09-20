<?php
/**
 * OrderGo - Kitchen Staff Dashboard & POS
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['staff', 'admin']);

$db = get_db();

// Load available menu items for the Counter POS modal
$mStmt = $db->query("SELECT item_id, name, price, category FROM odg_menu_items WHERE is_available = 1 ORDER BY category, name");
$posMenuItems = $mStmt->fetchAll();

$pageTitle = 'Kitchen Staff Dashboard';
$extraCss = ['staff.css'];
$extraJs = ['staff.js', 'scanner.js'];

require __DIR__ . '/../includes/header.php';
?>

<!-- HTML5-QRCode Scanner Library CDN -->
<script src="https://unpkg.com/html5-qrcode"></script>

<div class="staff-dashboard">
  <!-- Header -->
  <div class="staff-header">
    <div class="staff-header-left">
      <h1>Kitchen Management Console</h1>
      <p>Live kitchen workflow, counter walk-in POS, and instant camera QR scanner</p>
    </div>
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <div id="refresh-indicator" class="staff-refresh-indicator">
        <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> Live Syncing (3s)
      </div>
      <button class="btn btn-secondary btn-sm" onclick="toggleStaffSound()" id="btn-sound-toggle">
        🔔 Sound: ON
      </button>
    </div>
  </div>

  <!-- Quick Actions Grid -->
  <div class="staff-quick-actions">
    <!-- POS Action -->
    <div class="staff-quick-btn" onclick="openPOSModal()">
      <div class="sq-icon pos">
        <i data-lucide="calculator" style="width: 26px; height: 26px;"></i>
      </div>
      <div class="sq-text">
        <span>Counter POS</span>
        <small>Take walk-in offline orders</small>
      </div>
    </div>

    <!-- Scanner Action -->
    <div class="staff-quick-btn" onclick="ScannerApp.openModal()">
      <div class="sq-icon scan">
        <i data-lucide="scan-line" style="width: 26px; height: 26px;"></i>
      </div>
      <div class="sq-text">
        <span>Scan QR Code</span>
        <small>Verify student phone screen</small>
      </div>
    </div>

    <!-- TV Display Action -->
    <a href="<?= ROOT_PATH ?>/queue.php" target="_blank" class="staff-quick-btn">
      <div class="sq-icon monitor">
        <i data-lucide="monitor" style="width: 26px; height: 26px;"></i>
      </div>
      <div class="sq-text">
        <span>Canteen TV Queue</span>
        <small>Open public display board</small>
      </div>
    </a>
  </div>

  <!-- Real-time Stats Cards -->
  <div class="staff-stats-grid">
    <div class="staff-stat-card active" data-filter="active">
      <div>
        <div class="stat-number" id="stat-pending">0</div>
        <div class="stat-label">Pending / New</div>
      </div>
    </div>
    <div class="staff-stat-card" data-filter="preparing">
      <div>
        <div class="stat-number" id="stat-prep" style="color: #3b82f6;">0</div>
        <div class="stat-label">Preparing</div>
      </div>
    </div>
    <div class="staff-stat-card" data-filter="ready">
      <div>
        <div class="stat-number" id="stat-ready" style="color: #ea580c;">0</div>
        <div class="stat-label">Ready for Pickup</div>
      </div>
    </div>
    <div class="staff-stat-card" data-filter="completed">
      <div>
        <div class="stat-number" id="stat-done" style="color: #10b981;">0</div>
        <div class="stat-label">Completed Today</div>
      </div>
    </div>
    <div class="staff-stat-card" data-filter="cancelled">
      <div>
        <div class="stat-number" id="stat-cancel" style="color: #dc2626;">0</div>
        <div class="stat-label">Cancelled Today</div>
      </div>
    </div>
  </div>

  <!-- Live Orders Container -->
  <div class="kitchen-grid" id="kitchen-orders-container">
    <div class="empty-state" style="grid-column: 1 / -1; padding: 4rem;">
      <p>Connecting to live kitchen queue...</p>
    </div>
  </div>
</div>

<!-- Camera QR Scanner Modal -->
<div class="modal-overlay" id="scanner-modal">
  <div class="modal-content" style="max-width: 480px;">
    <div class="modal-header">
      <h2 id="scanner-modal-title">📷 Scan Customer QR Code</h2>
      <button class="btn-close" onclick="ScannerApp.closeModal()"><i data-lucide="x"></i></button>
    </div>
    <div id="qr-reader" style="width: 100%; min-height: 260px; background: #000; border-radius: 12px; overflow: hidden;"></div>

    <div style="margin-top: 1rem; border-top: 1px solid var(--border-subtle); padding-top: 0.75rem;">
      <label class="form-label" style="font-size: 0.85rem; margin-bottom: 0.4rem;">Or Enter Order ID / QR Text Manually:</label>
      <div style="display: flex; gap: 0.5rem;">
        <input type="text" id="manual-qr-input" class="form-input" placeholder="e.g. ORD-6AAE2EF4E4AC or 6AAE2EF4E4AC" onkeydown="if(event.key==='Enter') ScannerApp.verifyManual()">
        <button class="btn btn-primary btn-sm" onclick="ScannerApp.verifyManual()">Verify</button>
      </div>
    </div>

    <div id="scan-result-box"></div>
  </div>
</div>

<!-- Counter POS Modal -->
<div class="modal-overlay" id="pos-modal">
  <div class="modal-content" style="max-width: 580px;">
    <div class="modal-header">
      <h2>Counter Walk-In POS</h2>
      <button class="btn-close" onclick="closePOSModal()"><i data-lucide="x"></i></button>
    </div>

    <div class="form-group">
      <label class="form-label" for="pos-guest-name">Student / Customer Name</label>
      <input type="text" id="pos-guest-name" class="form-input" placeholder="Walk-in Student or Roll #">
    </div>

    <label class="form-label">Select Canteen Items</label>
    <div class="pos-items-selector">
      <?php foreach ($posMenuItems as $mItem): ?>
        <div class="pos-item-row">
          <div>
            <strong><?= htmlspecialchars($mItem['name']) ?></strong>
            <span style="color: var(--text-muted); font-size: 0.8rem; margin-left: 0.5rem;"><?= format_price($mItem['price']) ?></span>
          </div>
          <button class="btn btn-secondary btn-sm" onclick="addPosItem(<?= $mItem['item_id'] ?>, '<?= addslashes($mItem['name']) ?>', <?= $mItem['price'] ?>)">
            <i data-lucide="plus"></i> Add
          </button>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="background: var(--bg-secondary); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
      <h4 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem;">Current POS Tray:</h4>
      <div id="pos-tray-items">
        <p style="color: var(--text-muted); font-size: 0.85rem;">Tray is empty</p>
      </div>
      <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-subtle); margin-top: 0.5rem; padding-top: 0.5rem; font-weight: 800;">
        <span>Total Due:</span>
        <span id="pos-total-display" style="color: var(--accent-primary);">₹0</span>
      </div>
    </div>

    <button class="btn btn-primary btn-lg w-full" onclick="submitPosOrder()">
      Complete Cash Order & Print Ticket <i data-lucide="check"></i>
    </button>
  </div>
</div>

<script>
let posCart = [];

function toggleStaffSound() {
  StaffApp.soundEnabled = !StaffApp.soundEnabled;
  const btn = document.getElementById('btn-sound-toggle');
  if (btn) btn.textContent = StaffApp.soundEnabled ? '🔔 Sound: ON' : '🔕 Sound: OFF';
}

function openPOSModal() {
  document.getElementById('pos-modal').classList.add('active');
  renderPosTray();
}

function closePOSModal() {
  document.getElementById('pos-modal').classList.remove('active');
}

function addPosItem(id, name, price) {
  const existing = posCart.find(i => i.item_id === id);
  if (existing) {
    existing.quantity += 1;
  } else {
    posCart.push({ item_id: id, name: name, price: price, quantity: 1 });
  }
  renderPosTray();
}

function updatePosQty(id, delta) {
  const existing = posCart.find(i => i.item_id === id);
  if (existing) {
    existing.quantity += delta;
    if (existing.quantity <= 0) {
      posCart = posCart.filter(i => i.item_id !== id);
    }
  }
  renderPosTray();
}

function renderPosTray() {
  const container = document.getElementById('pos-tray-items');
  const totalDisplay = document.getElementById('pos-total-display');

  if (posCart.length === 0) {
    container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.85rem;">Tray is empty</p>';
    totalDisplay.textContent = '₹0';
    return;
  }

  container.innerHTML = posCart.map(item => `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px; font-size: 0.85rem;">
      <span><strong>${item.quantity}x</strong> ${item.name}</span>
      <div>
        <span style="margin-right: 8px;">₹${item.price * item.quantity}</span>
        <button class="btn btn-ghost btn-sm" onclick="updatePosQty(${item.item_id}, -1)">-</button>
        <button class="btn btn-ghost btn-sm" onclick="updatePosQty(${item.item_id}, 1)">+</button>
      </div>
    </div>
  `).join('');

  const total = posCart.reduce((sum, i) => sum + (i.price * i.quantity), 0);
  totalDisplay.textContent = '₹' + total;
}

async function submitPosOrder() {
  if (posCart.length === 0) {
    alert('Please add at least one item to the POS tray.');
    return;
  }

  const guestName = document.getElementById('pos-guest-name').value.trim() || 'Counter Walk-in';

  try {
    const url = typeof apiUrl === 'function' ? apiUrl('orders.php') : '/api/orders.php';
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        items: posCart.map(i => ({ item_id: i.item_id, quantity: i.quantity })),
        payment_method: 'cash',
        pickup_type: 'pickup',
        guest_name: guestName
      })
    });
    const data = await res.json();
    if (data.success) {
      Cart.showToast(`POS Order #${data.order.order_id.replace('ORD-','')} placed!`, 'success');
      posCart = [];
      document.getElementById('pos-guest-name').value = '';
      closePOSModal();
      StaffApp.fetchOrders(false);
    } else {
      alert(data.error || 'Failed to place POS order');
    }
  } catch (e) {
    alert('Network error while placing POS order');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  StaffApp.init();
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
