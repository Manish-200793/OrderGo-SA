/**
 * OrderGo Cart State Management
 * Fully compatible with previous React localStorage schema ('ordergo_cart')
 */

const Cart = {
  KEY: 'ordergo_cart',

  getItems() {
    try {
      return JSON.parse(localStorage.getItem(this.KEY)) || [];
    } catch (e) {
      return [];
    }
  },

  saveItems(items) {
    localStorage.setItem(this.KEY, JSON.stringify(items));
    this.updateUI();
    window.dispatchEvent(new CustomEvent('cart-updated', { detail: items }));
  },

  addItem(item) {
    const items = this.getItems();
    const existing = items.find(i => i.item_id === item.item_id);
    if (existing) {
      existing.quantity += 1;
    } else {
      items.push({
        item_id: item.item_id,
        name: item.name,
        price: parseFloat(item.price),
        image_url: item.image_url,
        quantity: 1
      });
    }
    this.saveItems(items);
    this.showToast(`Added ${item.name} to cart!`, 'success');
  },

  updateQuantity(itemId, quantity) {
    let items = this.getItems();
    if (quantity <= 0) {
      items = items.filter(i => i.item_id !== itemId);
    } else {
      items = items.map(i => i.item_id === itemId ? { ...i, quantity } : i);
    }
    this.saveItems(items);
  },

  removeItem(itemId) {
    const items = this.getItems().filter(i => i.item_id !== itemId);
    this.saveItems(items);
  },

  clear() {
    localStorage.removeItem(this.KEY);
    this.updateUI();
    window.dispatchEvent(new CustomEvent('cart-updated', { detail: [] }));
  },

  getTotalCount() {
    return this.getItems().reduce((sum, item) => sum + item.quantity, 0);
  },

  getTotalPrice() {
    return this.getItems().reduce((sum, item) => sum + (item.price * item.quantity), 0);
  },

  updateUI() {
    const count = this.getTotalCount();
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    });

    // Update food card quantity controls if on menu page
    const items = this.getItems();
    document.querySelectorAll('[data-cart-item-id]').forEach(el => {
      const id = parseInt(el.getAttribute('data-cart-item-id'));
      const inCart = items.find(i => i.item_id === id);
      const qtyEl = el.querySelector('.qty-value');
      const addBtn = el.querySelector('.btn-add-cart');
      const ctrl = el.querySelector('.quantity-control');

      if (inCart && inCart.quantity > 0) {
        if (addBtn) addBtn.style.display = 'none';
        if (ctrl) ctrl.style.display = 'flex';
        if (qtyEl) qtyEl.textContent = inCart.quantity;
      } else {
        if (addBtn) addBtn.style.display = 'inline-flex';
        if (ctrl) ctrl.style.display = 'none';
      }
    });
  },

  showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }
};

// Initialize badges on page load
document.addEventListener('DOMContentLoaded', () => {
  Cart.updateUI();
});
