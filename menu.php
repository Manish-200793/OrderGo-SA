<?php
/**
 * OrderGo - Full Menu Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Menu';
$extraCss = ['menu.css'];

$db = get_db();

// Fetch all menu items with ratings
$sql = "
    SELECT m.*, 
           COALESCE(AVG(f.rating), 0) as avg_rating,
           COUNT(f.feedback_id) as review_count
    FROM odg_menu_items m
    LEFT JOIN odg_feedback f ON m.item_id = f.item_id
    GROUP BY m.item_id
    ORDER BY m.is_daily_special DESC, m.category ASC, m.name ASC
";
$stmt = $db->query($sql);
$menuItems = $stmt->fetchAll();

$categories = [
    'all'       => 'All Items',
    'breakfast' => 'Breakfast',
    'lunch'     => 'Lunch & Meals',
    'snacks'    => 'Snacks',
    'beverages' => 'Beverages',
    'desserts'  => 'Desserts',
];

$activeCat = $_GET['cat'] ?? 'all';

require __DIR__ . '/includes/header.php';
?>

<div class="page container">
  <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 class="page-title">Campus Cafeteria Menu</h1>
      <p class="page-subtitle">Freshly prepared, hygiene-certified meals for students and campus staff</p>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="openRouletteModal()">
      🎲 Surprise Me (Budget Roulette)
    </button>
  </div>

  <!-- Search Bar -->
  <div class="menu-search-bar">
    <i data-lucide="search" class="search-icon" style="width: 20px; height: 20px;"></i>
    <input type="text" id="menu-search" class="search-input" placeholder="Search by food name, category, or ingredients..." oninput="filterMenuItems()">
  </div>

  <!-- Category Filter Tabs -->
  <div class="category-tabs">
    <?php foreach ($categories as $catKey => $catLabel): ?>
      <button class="category-tab <?= $activeCat === $catKey ? 'active' : '' ?>" onclick="selectCategory('<?= $catKey ?>', this)">
        <?= $catLabel ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Menu Items Grid -->
  <div class="menu-grid" id="menu-grid-container">
    <?php foreach ($menuItems as $item): ?>
      <?php 
        $isSoldOut = (!$item['is_available'] || $item['stock'] <= 0);
        $rating = round($item['avg_rating'], 1);
        $imgUrl = get_image_url($item['image_url']);
      ?>
      <div class="glass-card menu-card <?= $isSoldOut ? 'unavailable' : '' ?>" 
           data-cart-item-id="<?= $item['item_id'] ?>"
           data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>"
           data-category="<?= $item['category'] ?>"
           data-desc="<?= htmlspecialchars(strtolower($item['description'])) ?>">
        
        <?php if ($item['is_daily_special']): ?>
          <span class="badge badge-special menu-card-badge">⭐ Special</span>
        <?php endif; ?>

        <div class="menu-card-image" onclick="openItemModal(<?= htmlspecialchars(json_encode($item)) ?>)">
          <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="menu-card-img" loading="lazy">
          <?php if ($isSoldOut): ?>
            <div class="menu-card-sold-out">Sold Out</div>
          <?php endif; ?>
        </div>

        <div class="menu-card-content">
          <div class="menu-card-header">
            <h3 class="menu-card-name" onclick="openItemModal(<?= htmlspecialchars(json_encode($item)) ?>)">
              <?= htmlspecialchars($item['name']) ?>
            </h3>
            <span class="menu-card-price"><?= format_price($item['price']) ?></span>
          </div>

          <p class="menu-card-desc"><?= htmlspecialchars($item['description']) ?></p>

          <div class="menu-card-meta">
            <span style="text-transform: capitalize; font-weight: 500;"><?= $item['category'] ?></span>
            <div class="menu-card-rating">
              <i data-lucide="star" style="width: 13px; height: 13px; fill: currentColor;"></i>
              <span><?= $rating > 0 ? $rating : 'New' ?></span>
              <span class="rating-count">(<?= $item['review_count'] ?>)</span>
            </div>
          </div>

          <div class="menu-card-actions">
            <?php if (!$isSoldOut): ?>
              <button class="btn btn-primary btn-sm w-full btn-add-cart" 
                      onclick="Cart.addItem({item_id: <?= $item['item_id'] ?>, name: '<?= addslashes($item['name']) ?>', price: <?= $item['price'] ?>, image_url: '<?= addslashes($imgUrl) ?>'})">
                <i data-lucide="plus"></i> Add to Cart
              </button>
              <div class="quantity-control" style="display: none;">
                <button class="qty-btn" onclick="Cart.updateQuantity(<?= $item['item_id'] ?>, (Cart.getItems().find(i => i.item_id === <?= $item['item_id'] ?>)?.quantity || 1) - 1)">-</button>
                <span class="qty-value">1</span>
                <button class="qty-btn" onclick="Cart.updateQuantity(<?= $item['item_id'] ?>, (Cart.getItems().find(i => i.item_id === <?= $item['item_id'] ?>)?.quantity || 0) + 1)">+</button>
              </div>
            <?php else: ?>
              <button class="btn btn-secondary btn-sm w-full" disabled>Out of Stock</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Item Details & Reviews Modal -->
<div class="modal-overlay" id="item-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="modal-item-name">Item Details</h2>
      <button class="btn-close" onclick="closeItemModal()"><i data-lucide="x"></i></button>
    </div>
    <div id="modal-item-body">
      <div style="height: 200px; border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 1rem; background: var(--bg-secondary);">
        <img id="modal-item-img" src="" style="width: 100%; height: 100%; object-fit: cover;">
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <span id="modal-item-price" style="font-size: 1.5rem; font-weight: 800; color: var(--accent-primary);"></span>
        <span id="modal-item-stock" class="badge badge-preparing">In Stock</span>
      </div>
      <p id="modal-item-desc" style="color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.6;"></p>

      <!-- Reviews list -->
      <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-top: 1px solid var(--border-subtle); padding-top: 1rem;">
        Customer Feedback & Reviews
      </h3>
      <div id="modal-reviews-list" style="margin-bottom: 1.5rem;">
        <p style="color: var(--text-muted); font-size: 0.85rem;">Loading reviews...</p>
      </div>

      <!-- Add Review Section -->
      <?php if (is_logged_in()): ?>
        <div style="background: var(--bg-secondary); border-radius: var(--radius-md); padding: 1rem;">
          <h4 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;">Leave a Review</h4>
          <form id="review-form" onsubmit="submitReview(event)">
            <input type="hidden" id="review-item-id" name="item_id">
            <div style="margin-bottom: 0.5rem;">
              <label style="font-size: 0.75rem; color: var(--text-muted); display: block;">Rating (1 to 5 Stars)</label>
              <select id="review-rating" name="rating" class="form-input" style="padding: 0.4rem;" required>
                <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                <option value="4">⭐⭐⭐⭐ (4 - Very Good)</option>
                <option value="3">⭐⭐⭐ (3 - Average)</option>
                <option value="2">⭐⭐ (2 - Needs Improvement)</option>
                <option value="1">⭐ (1 - Poor)</option>
              </select>
            </div>
            <div style="margin-bottom: 0.75rem;">
              <textarea id="review-comment" name="comment" class="form-input" rows="2" placeholder="Write your thoughts on the taste and portion size..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Submit Review</button>
          </form>
        </div>
      <?php else: ?>
        <p style="font-size: 0.85rem; color: var(--text-muted);">
          <a href="<?= ROOT_PATH ?>/login.php">Log in</a> to submit your rating and review.
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Budget Roulette Modal -->
<div class="modal-overlay" id="roulette-modal">
  <div class="modal-content text-center">
    <div class="modal-header">
      <h2>🎲 Budget Roulette</h2>
      <button class="btn-close" onclick="closeRouletteModal()"><i data-lucide="x"></i></button>
    </div>
    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">
      Can't decide? Enter your budget and let OrderGo craft the perfect meal combo for you!
    </p>
    <div style="margin-bottom: 1.5rem;">
      <input type="number" id="roulette-budget" class="form-input text-center" placeholder="Enter Budget (e.g. 100)" value="100" min="20" max="500" style="font-size: 1.5rem; font-weight: 800;">
    </div>
    <button class="btn btn-primary btn-lg w-full" onclick="spinRoulette()">
      Spin the Wheel! 🎰
    </button>
    <div id="roulette-result" style="margin-top: 1.5rem; text-align: left;"></div>
  </div>
</div>

<script>
let currentCategory = 'all';
const allMenuItems = <?= json_encode($menuItems) ?>;

function selectCategory(cat, btn) {
  currentCategory = cat;
  document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  filterMenuItems();
}

function filterMenuItems() {
  const search = document.getElementById('menu-search').value.toLowerCase().trim();
  const cards = document.querySelectorAll('.menu-card');

  cards.forEach(card => {
    const name = card.getAttribute('data-name') || '';
    const cat = card.getAttribute('data-category') || '';
    const desc = card.getAttribute('data-desc') || '';

    const matchCat = (currentCategory === 'all' || cat === currentCategory);
    const matchSearch = (!search || name.includes(search) || desc.includes(search));

    card.style.display = (matchCat && matchSearch) ? 'flex' : 'none';
  });
}

function openItemModal(item) {
  document.getElementById('modal-item-name').textContent = item.name;
  document.getElementById('modal-item-img').src = item.image_url ? (item.image_url.startsWith('assets/') || item.image_url.startsWith('uploads/') ? item.image_url : 'assets/' + item.image_url.replace('/images/', 'images/')) : 'assets/images/placeholder.jpg';
  document.getElementById('modal-item-price').textContent = '₹' + parseFloat(item.price).toFixed(0);
  document.getElementById('modal-item-desc').textContent = item.description || 'No description available.';
  document.getElementById('modal-item-stock').textContent = (item.is_available && item.stock > 0) ? `In Stock (${item.stock})` : 'Sold Out';
  
  if (document.getElementById('review-item-id')) {
    document.getElementById('review-item-id').value = item.item_id;
  }

  // Load reviews via AJAX
  loadReviews(item.item_id);

  document.getElementById('item-modal').classList.add('active');
}

function closeItemModal() {
  document.getElementById('item-modal').classList.remove('active');
}

async function loadReviews(itemId) {
  const list = document.getElementById('modal-reviews-list');
  try {
    const url = typeof apiUrl === 'function' ? apiUrl(`feedback.php?item_id=${itemId}`) : `/api/feedback.php?item_id=${itemId}`;
    const res = await fetch(url);
    const data = await res.json();
    if (data.reviews && data.reviews.length > 0) {
      list.innerHTML = data.reviews.map(r => `
        <div class="review-item">
          <div class="review-header">
            <strong>${r.user_name || 'Student'}</strong>
            <span style="color:#D97706; font-size: 0.8rem;">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</span>
          </div>
          <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);">${r.comment || 'No comment provided.'}</p>
        </div>
      `).join('');
    } else {
      list.innerHTML = '<p style="color: var(--text-muted); font-size: 0.85rem;">No reviews yet. Be the first to review!</p>';
    }
  } catch (e) {
    list.innerHTML = '<p style="color: var(--text-muted); font-size: 0.85rem;">Failed to load reviews.</p>';
  }
}

async function submitReview(e) {
  e.preventDefault();
  const itemId = document.getElementById('review-item-id').value;
  const rating = document.getElementById('review-rating').value;
  const comment = document.getElementById('review-comment').value;

  try {
    const url = typeof apiUrl === 'function' ? apiUrl('feedback.php') : '/api/feedback.php';
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: itemId, rating: parseInt(rating), comment: comment })
    });
    const data = await res.json();
    if (data.success) {
      Cart.showToast('Feedback submitted successfully!', 'success');
      document.getElementById('review-comment').value = '';
      loadReviews(itemId);
    } else {
      alert(data.error || 'Failed to submit review.');
    }
  } catch (e) {
    alert('Network error while submitting review.');
  }
}

function openRouletteModal() {
  document.getElementById('roulette-modal').classList.add('active');
}

function closeRouletteModal() {
  document.getElementById('roulette-modal').classList.remove('active');
}

function spinRoulette() {
  const budget = parseFloat(document.getElementById('roulette-budget').value) || 100;
  const avail = allMenuItems.filter(i => i.is_available && i.stock > 0);
  const resultDiv = document.getElementById('roulette-result');
  resultDiv.innerHTML = '<div style="text-align: center; padding: 1rem;">🎲 Selecting best delicious combo...</div>';

  setTimeout(() => {
    let combo = [];
    let spent = 0;
    const shuffled = [...avail].sort(() => 0.5 - Math.random());

    for (const item of shuffled) {
      const price = parseFloat(item.price);
      if (spent + price <= budget) {
        combo.push(item);
        spent += price;
      }
    }

    if (combo.length === 0) {
      resultDiv.innerHTML = `<div style="color: #dc2626; padding: 1rem; text-align: center;">No items found within budget of ₹${budget}. Try increasing your budget!</div>`;
      return;
    }

    resultDiv.innerHTML = `
      <div class="glass-card" style="padding: 1rem; border: 1.5px solid var(--accent-primary);">
        <h4 style="color: var(--accent-primary); margin-bottom: 0.5rem;">🎉 Chef's Special Combo:</h4>
        <ul style="margin-bottom: 0.75rem;">
          ${combo.map(i => `<li style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.9rem;"><span>${i.name}</span><strong>₹${i.price}</strong></li>`).join('')}
        </ul>
        <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-subtle); padding-top: 0.5rem; font-weight: 800;">
          <span>Total:</span>
          <span style="color: var(--accent-primary);">₹${spent}</span>
        </div>
        <button class="btn btn-primary btn-sm w-full" style="margin-top: 0.75rem;" onclick="addComboToCart(${JSON.stringify(combo).replace(/"/g, '&quot;')})">
          Add Combo to Cart 🛒
        </button>
      </div>
    `;
  }, 400);
}

function addComboToCart(combo) {
  combo.forEach(item => {
    Cart.addItem({
      item_id: item.item_id,
      name: item.name,
      price: parseFloat(item.price),
      image_url: item.image_url
    });
  });
  closeRouletteModal();
  Cart.showToast('Combo added to cart!', 'success');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
