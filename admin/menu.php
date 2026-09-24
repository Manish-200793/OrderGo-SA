<?php
/**
 * OrderGo - Admin Menu Manager
 * Full CRUD for food catalog with image file upload support
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'staff']);

$db = get_db();
$msg = '';
$err = '';

// Handle Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'lunch';
        $price = floatval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 50);
        $description = trim($_POST['description'] ?? '');
        $isSpecial = isset($_POST['is_daily_special']) ? 1 : 0;
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        // Image upload handling
        $imageUrl = trim($_POST['existing_image'] ?? '');
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'item_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = UPLOAD_DIR . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $imageUrl = 'uploads/' . $filename;
                }
            }
        }

        if (empty($name) || $price <= 0) {
            $err = 'Name and valid price are required.';
        } else {
            if ($action === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO odg_menu_items (name, description, category, price, stock, image_url, is_available, is_daily_special)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $category, $price, $stock, $imageUrl ?: null, $isAvailable, $isSpecial]);
                $msg = "Item '{$name}' added successfully!";
            } else {
                $id = (int)$_POST['item_id'];
                $stmt = $db->prepare("
                    UPDATE odg_menu_items 
                    SET name = ?, description = ?, category = ?, price = ?, stock = ?, image_url = ?, is_available = ?, is_daily_special = ?
                    WHERE item_id = ?
                ");
                $stmt->execute([$name, $description, $category, $price, $stock, $imageUrl ?: null, $isAvailable, $isSpecial, $id]);
                $msg = "Item updated successfully!";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['item_id'];
        $stmt = $db->prepare("DELETE FROM odg_menu_items WHERE item_id = ?");
        $stmt->execute([$id]);
        $msg = "Menu item deleted successfully!";
    }
}

// Fetch all menu items
$stmt = $db->query("SELECT * FROM odg_menu_items ORDER BY category, name");
$items = $stmt->fetchAll();

$pageTitle = 'Menu Manager';
$extraCss = ['admin.css'];
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h1 class="page-title">Menu Management</h1>
        <p class="page-subtitle">Add dishes, restock canteen inventory, and adjust prices</p>
      </div>
      <button class="btn btn-primary btn-sm" onclick="openAddModal()">
        <i data-lucide="plus"></i> Add New Dish
      </button>
    </div>

    <?php if (!empty($msg)): ?>
      <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #059669; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
      <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>

    <div class="glass-card" style="padding: 1.5rem; overflow-x: auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Dish</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th><i data-lucide="eye" style="width: 14px; height: 14px; margin-right: 4px;"></i>Visible on Menu</th>
            <th>Daily Special</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <img src="<?= get_image_url($item['image_url']) ?>" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover;">
                  <div>
                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                      <?= htmlspecialchars($item['description']) ?>
                    </div>
                  </div>
                </div>
              </td>
              <td><span style="text-transform: capitalize;"><?= $item['category'] ?></span></td>
              <td><strong><?= format_price($item['price']) ?></strong></td>
              <td>
                <span class="badge <?= $item['stock'] < 10 ? 'badge-cancelled' : 'badge-completed' ?>">
                  <?= $item['stock'] ?> left
                </span>
              </td>
              <td>
                <input type="checkbox" <?= $item['is_available'] ? 'checked' : '' ?> onchange="toggleItemSetting(<?= $item['item_id'] ?>, 'available', this.checked)">
              </td>
              <td>
                <input type="checkbox" <?= $item['is_daily_special'] ? 'checked' : '' ?> onchange="toggleItemSetting(<?= $item['item_id'] ?>, 'special', this.checked)">
              </td>
              <td style="text-align: right;">
                <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($item) ?>)'>
                  <i data-lucide="edit"></i>
                </button>
                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete <?= addslashes($item['name']) ?> from menu?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">
                    <i data-lucide="trash-2"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Add / Edit Item Modal -->
<div class="modal-overlay" id="menu-modal">
  <div class="modal-content" style="max-width: 540px;">
    <div class="modal-header">
      <h2 id="modal-title">Add Menu Item</h2>
      <button class="btn-close" onclick="closeMenuModal()"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="" enctype="multipart/form-data">
      <input type="hidden" name="action" id="form-action" value="add">
      <input type="hidden" name="item_id" id="form-item-id" value="">
      <input type="hidden" name="existing_image" id="form-existing-image" value="">

      <div class="form-group">
        <label class="form-label">Dish Name *</label>
        <input type="text" name="name" id="form-name" class="form-input" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Category *</label>
          <select name="category" id="form-category" class="form-input">
            <option value="breakfast">Breakfast</option>
            <option value="lunch">Lunch</option>
            <option value="snacks">Snacks</option>
            <option value="beverages">Beverages</option>
            <option value="desserts">Desserts</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Price (₹) *</label>
          <input type="number" step="0.5" name="price" id="form-price" class="form-input" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Initial Stock Quantity</label>
        <input type="number" name="stock" id="form-stock" class="form-input" value="50" min="0">
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" id="form-desc" class="form-input" rows="2"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Item Photo (Upload or keep existing)</label>
        <input type="file" name="image" accept="image/*" class="form-input">
      </div>

      <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;">
          <input type="checkbox" name="is_available" id="form-available" value="1" checked> Visible on Menu
        </label>
        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer;">
          <input type="checkbox" name="is_daily_special" id="form-special" value="1"> Daily Special
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-full" id="form-submit-btn">
        Save Dish <i data-lucide="check"></i>
      </button>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('modal-title').textContent = 'Add Menu Item';
  document.getElementById('form-action').value = 'add';
  document.getElementById('form-item-id').value = '';
  document.getElementById('form-existing-image').value = '';
  document.getElementById('form-name').value = '';
  document.getElementById('form-price').value = '';
  document.getElementById('form-stock').value = '50';
  document.getElementById('form-desc').value = '';
  document.getElementById('form-available').checked = true;
  document.getElementById('form-special').checked = false;
  document.getElementById('menu-modal').classList.add('active');
}

function openEditModal(item) {
  document.getElementById('modal-title').textContent = 'Edit Menu Item';
  document.getElementById('form-action').value = 'edit';
  document.getElementById('form-item-id').value = item.item_id;
  document.getElementById('form-existing-image').value = item.image_url || '';
  document.getElementById('form-name').value = item.name;
  document.getElementById('form-category').value = item.category;
  document.getElementById('form-price').value = item.price;
  document.getElementById('form-stock').value = item.stock;
  document.getElementById('form-desc').value = item.description || '';
  document.getElementById('form-available').checked = !!item.is_available;
  document.getElementById('form-special').checked = !!item.is_daily_special;
  document.getElementById('menu-modal').classList.add('active');
}

function closeMenuModal() {
  document.getElementById('menu-modal').classList.remove('active');
}

async function toggleItemSetting(itemId, type, value) {
  try {
    const url = typeof apiUrl === 'function' ? apiUrl('menu_toggle.php') : '/api/menu_toggle.php';
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: itemId, type: type, value: value ? 1 : 0 })
    });
    const data = await res.json();
    if (data.success) {
      Cart.showToast('Item setting updated!', 'success');
    } else {
      alert(data.error || 'Failed to update setting');
    }
  } catch (e) {
    alert('Network error');
  }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
