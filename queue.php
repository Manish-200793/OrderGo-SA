<?php
/**
 * OrderGo - Public TV Queue Screen for Canteen Counter
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'Live Order Queue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Canteen Order Queue | OrderGo</title>
  <link rel="stylesheet" href="<?= ROOT_PATH ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= ROOT_PATH ?>/assets/css/queue.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<div class="queue-container">
  <header class="queue-header">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <i data-lucide="utensils" style="width: 36px; height: 36px; color: var(--accent-primary);"></i>
      <h1>OrderGo Live Queue</h1>
    </div>
    <div class="queue-time" id="clock-display">--:-- --</div>
  </header>

  <div class="queue-grid">
    <!-- Preparing Column -->
    <div class="queue-col col-preparing">
      <h2 class="queue-col-title">Cooking / Preparing</h2>
      <div class="queue-list" id="queue-preparing-list">
        <div class="queue-empty">No orders currently preparing</div>
      </div>
    </div>

    <!-- Ready Column -->
    <div class="queue-col col-ready">
      <h2 class="queue-col-title pulse-text">Please Collect at Counter</h2>
      <div class="queue-list" id="queue-ready-list">
        <div class="queue-empty">No orders waiting for collection</div>
      </div>
    </div>
  </div>
</div>

<script>
function updateClock() {
  const el = document.getElementById('clock-display');
  if (el) {
    el.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }
}
setInterval(updateClock, 1000);
updateClock();

async function fetchQueue() {
  try {
    const res = await fetch('<?= ROOT_PATH ?>/api/staff_orders.php?status=active');
    const data = await res.json();
    if (!data.orders) return;

    const preparing = data.orders.filter(o => o.status === 'preparing' || o.status === 'pending');
    const ready = data.orders.filter(o => o.status === 'ready');

    const prepList = document.getElementById('queue-preparing-list');
    const readyList = document.getElementById('queue-ready-list');

    if (preparing.length === 0) {
      prepList.innerHTML = '<div class="queue-empty">No orders currently preparing</div>';
    } else {
      prepList.innerHTML = preparing.map(o => `
        <div class="queue-card card-preparing">
          <span class="queue-number">#${o.order_id.replace('ORD-', '')}</span>
          <span class="queue-name">${o.customer_name || 'Guest'}</span>
        </div>
      `).join('');
    }

    if (ready.length === 0) {
      readyList.innerHTML = '<div class="queue-empty">No orders waiting for collection</div>';
    } else {
      readyList.innerHTML = ready.map(o => `
        <div class="queue-card card-ready">
          <span class="queue-number">#${o.order_id.replace('ORD-', '')}</span>
          <span class="queue-name">${o.customer_name || 'Guest'}</span>
        </div>
      `).join('');
    }
  } catch (err) {
    console.error('Queue poll error:', err);
  }
}

fetchQueue();
setInterval(fetchQueue, 4000);
</script>

</body>
</html>
