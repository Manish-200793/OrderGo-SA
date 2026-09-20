/**
 * Kitchen Staff Real-Time Dashboard Manager
 * Features: 3s Polling, Synthesized Audio Chime, Status Transitions, POS Modal
 */

const StaffApp = {
  currentFilter: 'active',
  lastOrderCount: 0,
  knownOrderIds: new Set(),
  soundEnabled: true,
  pollTimer: null,

  initialized: false,

  init() {
    if (this.initialized) return;
    this.initialized = true;

    this.fetchOrders();
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = setInterval(() => this.fetchOrders(true), 3500);

    // Filter clicks
    document.querySelectorAll('.staff-stat-card').forEach(card => {
      card.addEventListener('click', () => {
        document.querySelectorAll('.staff-stat-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        this.currentFilter = card.getAttribute('data-filter') || 'active';
        this.fetchOrders(false);
      });
    });
  },

  playChime() {
    if (!this.soundEnabled) return;
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const now = ctx.currentTime;
      
      const osc1 = ctx.createOscillator();
      const gain1 = ctx.createGain();
      osc1.frequency.setValueAtTime(587.33, now); // D5
      gain1.gain.setValueAtTime(0.3, now);
      gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.5);
      osc1.connect(gain1);
      gain1.connect(ctx.destination);
      osc1.start(now);
      osc1.stop(now + 0.5);

      const osc2 = ctx.createOscillator();
      const gain2 = ctx.createGain();
      osc2.frequency.setValueAtTime(880.00, now + 0.15); // A5
      gain2.gain.setValueAtTime(0.3, now + 0.15);
      gain2.gain.exponentialRampToValueAtTime(0.01, now + 0.8);
      osc2.connect(gain2);
      gain2.connect(ctx.destination);
      osc2.start(now + 0.15);
      osc2.stop(now + 0.8);
    } catch (e) {
      console.warn('AudioContext not allowed without interaction yet');
    }
  },

  async fetchOrders(isBackground = false) {
    const indicator = document.getElementById('refresh-indicator');
    if (indicator) indicator.classList.add('refreshing');

    try {
      const url = typeof apiUrl === 'function' 
        ? apiUrl('staff_orders.php?status=' + encodeURIComponent(this.currentFilter)) 
        : ('/api/staff_orders.php?status=' + encodeURIComponent(this.currentFilter));
        
      const res = await fetch(url);
      if (!res.ok) {
        throw new Error(`HTTP Error: ${res.status}`);
      }
      const data = await res.json();

      if (data.stats) {
        this.updateStats(data.stats);
      }

      if (data.orders) {
        // Detect brand new incoming orders
        const newOrders = data.orders.filter(o => !this.knownOrderIds.has(o.order_id) && o.status === 'pending');
        if (isBackground && newOrders.length > 0 && this.knownOrderIds.size > 0) {
          this.playChime();
          if (typeof Cart !== 'undefined' && Cart.showToast) {
            Cart.showToast(`🔔 ${newOrders.length} New Order(s) Received!`, 'info');
          }
        }

        data.orders.forEach(o => this.knownOrderIds.add(o.order_id));
        this.renderOrders(data.orders);
      }
    } catch (err) {
      console.error('Failed to fetch staff orders:', err);
      const container = document.getElementById('kitchen-orders-container');
      if (container && this.knownOrderIds.size === 0) {
        container.innerHTML = `
          <div class="empty-state" style="grid-column: 1 / -1; padding: 3rem; text-align: center;">
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Could not connect to live queue. Please check server status.</p>
            <button class="btn btn-primary btn-sm" onclick="StaffApp.fetchOrders(false)">
              <i data-lucide="refresh-cw"></i> Retry Connection
            </button>
          </div>
        `;
        if (window.lucide) lucide.createIcons();
      }
    } finally {
      if (indicator) indicator.classList.remove('refreshing');
    }
  },

  updateStats(stats) {
    const elPending = document.getElementById('stat-pending');
    const elPrep = document.getElementById('stat-prep');
    const elReady = document.getElementById('stat-ready');
    const elDone = document.getElementById('stat-done');
    const elCancel = document.getElementById('stat-cancel');

    if (elPending) elPending.textContent = stats.pending || 0;
    if (elPrep) elPrep.textContent = stats.preparing || 0;
    if (elReady) elReady.textContent = stats.ready || 0;
    if (elDone) elDone.textContent = stats.completed_today || 0;
    if (elCancel) elCancel.textContent = stats.cancelled_today || 0;
  },

  renderOrders(orders) {
    const container = document.getElementById('kitchen-orders-container');
    if (!container) return;

    if (!orders || orders.length === 0) {
      container.innerHTML = `
        <div class="empty-state" style="grid-column: 1 / -1; padding: 4rem;">
          <i data-lucide="package-open" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.3;"></i>
          <h3>No Orders Found</h3>
          <p>There are no active orders matching this filter right now.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    container.innerHTML = orders.map(order => {
      let timeFormatted = '';
      if (order.created_at) {
        try {
          const d = new Date(order.created_at.replace(' ', 'T'));
          timeFormatted = !isNaN(d.getTime()) ? d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : order.created_at;
        } catch (e) {
          timeFormatted = order.created_at;
        }
      }

      const orderDisplayId = (order.order_id || '').replace('ORD-', '');
      const paymentMethod = (order.payment_method || 'CASH').toUpperCase();
      const totalPrice = parseFloat(order.total_price || 0).toFixed(0);

      return `
        <div class="kitchen-card status-${order.status}" id="card-${order.order_id}">
          <div class="kitchen-card-header">
            <div>
              <div class="kitchen-order-id">#${orderDisplayId}</div>
              <div class="kitchen-customer">
                <i data-lucide="user" style="width: 14px; height: 14px;"></i>
                <strong>${order.customer_name || 'Guest'}</strong>
                ${order.customer_roll_number ? `(${order.customer_roll_number})` : ''}
              </div>
            </div>
            <div>
              <span class="badge badge-${order.status}">${order.status}</span>
            </div>
          </div>

          <div class="kitchen-items-list">
            ${(order.items || []).map(item => `
              <div class="kitchen-item-row">
                <span><strong class="kitchen-item-qty">${item.quantity}x</strong> ${item.name}</span>
                <span style="color: var(--text-muted); font-size: 0.8rem;">₹${(item.price_at_order * item.quantity).toFixed(0)}</span>
              </div>
            `).join('')}
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: var(--text-secondary);">
            <span>Total: <strong>₹${totalPrice}</strong> (${paymentMethod})</span>
            <span style="font-size: 0.75rem; color: var(--text-muted);">${timeFormatted}</span>
          </div>

          <div class="kitchen-card-actions">
            ${this.renderActionButtons(order)}
          </div>
        </div>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();
  },

  renderActionButtons(order) {
    if (order.status === 'pending') {
      return `
        <button class="btn btn-primary btn-sm" onclick="StaffApp.updateStatus('${order.order_id}', 'preparing')">
          <i data-lucide="cooking-pot"></i> Start Preparing
        </button>
        <button class="btn btn-danger btn-sm" onclick="StaffApp.updateStatus('${order.order_id}', 'cancelled')">
          Cancel
        </button>
      `;
    } else if (order.status === 'preparing') {
      return `
        <button class="btn btn-primary btn-sm" style="background: #10b981; border-color: #10b981;" onclick="StaffApp.updateStatus('${order.order_id}', 'ready')">
          <i data-lucide="check-circle-2"></i> Mark Ready
        </button>
        <button class="btn btn-danger btn-sm" onclick="StaffApp.updateStatus('${order.order_id}', 'cancelled')">
          Cancel
        </button>
      `;
    } else if (order.status === 'ready') {
      return `
        <button class="btn btn-primary btn-sm" style="background: #2563eb; border-color: #2563eb;" onclick="ScannerApp.openModal('${order.order_id}')">
          <i data-lucide="scan-line"></i> Scan Student QR
        </button>
      `;
    }
    return '';
  },

  async updateStatus(orderId, status) {
    if (status === 'completed') {
      ScannerApp.openModal(orderId);
      return;
    }
    try {
      const url = typeof apiUrl === 'function' ? apiUrl('staff_orders.php') : '/api/staff_orders.php';
      const res = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: status })
      });
      const data = await res.json();
      if (data.success) {
        if (typeof Cart !== 'undefined' && Cart.showToast) {
          Cart.showToast(`Order updated to ${status}!`, 'success');
        }
        this.fetchOrders(false);
      } else {
        alert(data.error || 'Failed to update order status');
      }
    } catch (err) {
      alert('Network error while updating status');
    }
  }
};

// Auto-run if document already interactive/complete
if (typeof window !== 'undefined') {
  window.StaffApp = StaffApp;
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    StaffApp.init();
  } else {
    document.addEventListener('DOMContentLoaded', () => StaffApp.init());
  }
}
