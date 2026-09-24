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
<script>
  window.isKitchenAdmin = <?= json_encode($user['role'] === 'admin') ?>;
</script>

<div class="staff-dashboard">
  <!-- Header -->
  <div class="staff-header">
    <div class="staff-header-left">
      <h1>Kitchen Management Console</h1>
      <p>Live kitchen workflow, counter walk-in POS, and instant camera QR scanner</p>
    </div>
    <div style="display: flex; align-items: center; gap: 0.75rem;">

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

    <!-- Menu Management Action -->
    <a href="<?= ROOT_PATH ?>/admin/menu.php" class="staff-quick-btn" style="text-decoration: none;">
      <div class="sq-icon">
        <i data-lucide="layout-list" style="width: 26px; height: 26px;"></i>
      </div>
      <div class="sq-text">
        <span>Menu Management</span>
        <small>Edit items, prices & stock</small>
      </div>
    </a>

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
  <div class="modal-content" style="max-width: 800px; padding: 2rem;">
    <div class="modal-header" style="margin-bottom: 1.5rem;">
      <h2 style="font-size: 1.5rem; font-weight: 700;">New POS Order (Cash)</h2>
      <button class="btn-close" onclick="closePOSModal()"><i data-lucide="x"></i></button>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem; align-items: start;">
      <!-- Left Column: Menu Items -->
      <div>
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Menu Items</h3>
        <div class="pos-items-selector" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
          <?php foreach ($posMenuItems as $mItem): ?>
            <div class="pos-item-row" style="background: var(--bg-secondary); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <strong style="display: block; margin-bottom: 0.2rem;"><?= htmlspecialchars($mItem['name']) ?></strong>
                <span style="color: var(--text-muted); font-size: 0.85rem;">₹<?= $mItem['price'] ?></span>
              </div>
              <button class="btn btn-secondary btn-sm" style="border-radius: 20px; color: var(--accent-primary); border-color: var(--accent-primary); background: transparent; padding: 0.25rem 1rem;" onclick="addPosItem(<?= $mItem['item_id'] ?>, '<?= addslashes($mItem['name']) ?>', <?= $mItem['price'] ?>)">
                Add
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right Column: Order Summary -->
      <div style="background: var(--bg-secondary); border-radius: 12px; padding: 1.5rem;">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Order Summary</h3>
        <div id="pos-tray-items" style="min-height: 150px; margin-bottom: 1rem;">
          <p style="color: var(--text-muted); font-size: 0.9rem;">Cart is empty</p>
        </div>
        
        <div style="border-top: 1px dashed var(--border-subtle); padding-top: 1rem; margin-bottom: 1rem;">
          <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1.2rem; margin-bottom: 1rem;">
            <span>Total:</span>
            <span id="pos-total-display">₹0</span>
          </div>
          
          <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" for="pos-guest-name" style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.4rem;">Student Name (Optional)</label>
            <input type="text" id="pos-guest-name" class="form-input" style="border-radius: 8px; border-color: var(--accent-primary);" placeholder="e.g. John Doe">
          </div>
          
          <button class="btn btn-primary btn-lg w-full" style="border-radius: 8px;" onclick="submitPosOrder()">
            Collect Cash & Order
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Order Slip Modal -->
<div class="modal-overlay" id="slip-modal">
  <div class="modal-content" style="max-width: 400px; padding: 2rem; background: #fff; color: #000;">
    <div class="modal-header" style="margin-bottom: 1rem;">
      <h2 style="font-size: 1.2rem; font-weight: 700; margin: 0;">Order Slip</h2>
      <button class="btn-close" style="color: #000;" onclick="closeSlipModal()"><i data-lucide="x"></i></button>
    </div>
    
    <div id="slip-printable-area" style="text-align: center; font-family: sans-serif;">
      <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 5px 0;">OrderGo</h1>
      <p style="margin: 0; font-size: 0.9rem; color: #444;">College Canteen</p>
      
      <div style="border-top: 1px dashed #ccc; margin: 15px 0;"></div>
      
      <div style="text-align: left; font-size: 0.95rem; line-height: 1.5;">
        <div><strong>Order ID:</strong> <span id="slip-order-id"></span></div>
        <div><strong>Name:</strong> <span id="slip-guest-name"></span></div>
      </div>
      
      <div style="border-top: 1px dashed #ccc; margin: 15px 0;"></div>
      
      <div id="slip-items" style="text-align: left; font-size: 0.95rem; line-height: 1.8;">
        <!-- Items injected here -->
      </div>
      
      <div style="border-top: 1px solid #000; margin: 10px 0;"></div>
      
      <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1rem; margin-bottom: 20px;">
        <span>Total (PAID CASH)</span>
        <span id="slip-total"></span>
      </div>
      
      <img id="slip-qr-image" src="" alt="QR Code" style="width: 150px; height: 150px; margin: 0 auto; display: block;" />
      
      <p style="font-size: 0.8rem; margin-top: 10px; color: #444;">Show this QR at the counter</p>
    </div>
    
    <button class="btn btn-primary btn-lg w-full" style="margin-top: 1.5rem; border-radius: 8px;" onclick="printSlip()">
      Print Slip
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
    container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem;">Cart is empty</p>';
    totalDisplay.textContent = '₹0';
    return;
  }

  container.innerHTML = posCart.map(item => `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; font-size: 0.95rem;">
      <span style="flex: 1;">${item.name}</span>
      <div style="display: flex; align-items: center; gap: 10px;">
        <button class="btn btn-primary" style="padding: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; line-height: 1;" onclick="updatePosQty(${item.item_id}, -1)">-</button>
        <strong style="min-width: 20px; text-align: center;">${item.quantity}</strong>
        <button class="btn btn-primary" style="padding: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; line-height: 1;" onclick="updatePosQty(${item.item_id}, 1)">+</button>
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
      
      // Setup Slip
      document.getElementById('slip-order-id').textContent = data.order.order_id;
      document.getElementById('slip-guest-name').textContent = guestName;
      
      const slipItemsHtml = posCart.map(item => `
        <div style="display: flex; justify-content: space-between;">
          <span>${item.quantity}x ${item.name}</span>
          <span>₹${item.price * item.quantity}</span>
        </div>
      `).join('');
      document.getElementById('slip-items').innerHTML = slipItemsHtml;
      
      const total = posCart.reduce((sum, i) => sum + (i.price * i.quantity), 0);
      document.getElementById('slip-total').textContent = '₹' + total.toFixed(2);
      
      // Generate QR Code using the order ID
      document.getElementById('slip-qr-image').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(data.order.qr_code || data.order.order_id)}`;
      
      posCart = [];
      document.getElementById('pos-guest-name').value = '';
      closePOSModal();
      document.getElementById('slip-modal').classList.add('active');
      StaffApp.fetchOrders(false);
    } else {
      alert(data.error || 'Failed to place POS order');
    }
  } catch (e) {
    alert('Network error while placing POS order');
  }
}

function closeSlipModal() {
  document.getElementById('slip-modal').classList.remove('active');
}

function printSlip() {
  const printContent = document.getElementById('slip-printable-area').innerHTML;
  const printWindow = window.open('', '', 'width=600,height=800');
  printWindow.document.write('<html><head><title>Print Slip</title>');
  printWindow.document.write('<style>body{font-family:sans-serif;padding:20px;text-align:center;} @media print{body{margin:0;padding:0;}}</style>');
  printWindow.document.write('</head><body>');
  printWindow.document.write(printContent);
  printWindow.document.write('<script>');
  printWindow.document.write('window.onload = function() { setTimeout(function() { window.print(); window.close(); }, 500); }');
  printWindow.document.write('<\\/script>');
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.focus();
}

document.addEventListener('DOMContentLoaded', () => {
  StaffApp.init();
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
