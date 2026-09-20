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
    'all'       => '🍲 All',
    'breakfast' => '🌅 Breakfast',
    'lunch'     => '🍛 Lunch',
    'snacks'    => '🍿 Snacks',
    'beverages' => '☕ Beverages',
    'desserts'  => '🍰 Desserts',
];

$activeCat = $_GET['cat'] ?? 'all';

require __DIR__ . '/includes/header.php';
?>

<div class="page container">
  <div class="page-header" style="margin-bottom: 2rem;">
    <h1 class="page-title" style="font-size: 2.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">Our Menu</h1>
    <p class="page-subtitle" style="color: #64748b; font-size: 1.1rem;">Fresh, delicious food made with love</p>
  </div>

  <?php if (!is_logged_in()): ?>
  <!-- Guest Banner -->
  <div class="guest-banner">
    <div class="guest-banner-left">
      <div class="guest-icon"><i data-lucide="utensils" style="width: 24px; height: 24px;"></i></div>
      <div>
        <h3 class="guest-title">Browsing as Guest</h3>
        <p class="guest-text">Sign in or create an account to view prices, customize items, and place orders.</p>
      </div>
    </div>
    <div class="guest-buttons">
      <a href="<?= ROOT_PATH ?>/login.php" class="btn-guest-login"><i data-lucide="log-in" style="width: 16px; height: 16px;"></i> Login</a>
      <a href="<?= ROOT_PATH ?>/register.php" class="btn-guest-signup"><i data-lucide="user-plus" style="width: 16px; height: 16px;"></i> Sign Up</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Search Bar -->
  <div class="menu-search-bar">
    <i data-lucide="search" class="search-icon" style="width: 20px; height: 20px;"></i>
    <input type="text" id="menu-search" class="search-input" placeholder="Search for dishes..." oninput="filterMenuItems()">
  </div>

  <!-- Category Filter Tabs -->
  <div class="category-tabs">
    <?php foreach ($categories as $catKey => $catLabel): ?>
      <button class="category-tab <?= $activeCat === $catKey ? 'active' : '' ?>" onclick="selectCategory('<?= $catKey ?>', this)">
        <?= $catLabel ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Section Title -->
  <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
    <i data-lucide="sparkles" style="width: 20px; height: 20px;"></i> Recommended For You
  </h2>

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
          <span class="badge badge-special menu-card-badge">⭐ TODAY'S SPECIAL</span>
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
            <?php if (is_logged_in()): ?>
              <span class="menu-card-price"><?= format_price($item['price']) ?></span>
            <?php endif; ?>
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
              <?php $u = current_user(); if ($u && !in_array($u['role'], ['admin', 'staff'])): ?>
                <button class="btn-add-cart" 
                        onclick="Cart.addItem({item_id: <?= $item['item_id'] ?>, name: '<?= addslashes($item['name']) ?>', price: <?= $item['price'] ?>, image_url: '<?= addslashes($imgUrl) ?>'})">
                  <span class="show-on-mobile">ADD</span>
                  <span class="hide-on-mobile"><i data-lucide="plus" style="width:16px;height:16px;vertical-align:middle;"></i> Add to Cart</span>
                </button>
                <div class="quantity-control" style="display: none;">
                  <button class="qty-btn" onclick="Cart.updateQuantity(<?= $item['item_id'] ?>, (Cart.getItems().find(i => i.item_id === <?= $item['item_id'] ?>)?.quantity || 1) - 1)">-</button>
                  <span class="qty-value">1</span>
                  <button class="qty-btn" onclick="Cart.updateQuantity(<?= $item['item_id'] ?>, (Cart.getItems().find(i => i.item_id === <?= $item['item_id'] ?>)?.quantity || 0) + 1)">+</button>
                </div>
              <?php elseif (!$u): ?>
                <a href="<?= ROOT_PATH ?>/login.php" class="btn-login-order">
                  <span class="show-on-mobile">ADD</span>
                  <span class="hide-on-mobile"><i data-lucide="log-in" style="width:16px;height:16px;vertical-align:middle;"></i> Login to Order</span>
                </a>
              <?php endif; ?>
            <?php else: ?>
              <button class="btn btn-secondary btn-sm w-full" disabled style="opacity: 0.7; cursor: not-allowed; border-radius: 8px;">Out of Stock</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Floating Surprise Me Button -->
<button class="floating-roulette-btn" onclick="openRouletteModal()">
  <i data-lucide="dices" style="width: 20px; height: 20px;"></i> Surprise Me!
</button>

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
    // Categorize items
    const mains = avail.filter(i => ['breakfast', 'lunch', 'snacks'].includes(i.category));
    const bevs = avail.filter(i => i.category === 'beverages');
    const desserts = avail.filter(i => i.category === 'desserts');

    let validCombos = [];

    // 1. Try 3-item combos (Main + Beverage + Dessert)
    mains.forEach(m => {
      bevs.forEach(b => {
        desserts.forEach(d => {
          const total = parseFloat(m.price) + parseFloat(b.price) + parseFloat(d.price);
          if (total <= budget) validCombos.push({ items: [m, b, d], total: total });
        });
      });
    });

    // 2. If no 3-item combo fits, try 2-item combos (Main + Bev OR Main + Dessert)
    if (validCombos.length === 0) {
      mains.forEach(m => {
        bevs.forEach(b => {
          const total = parseFloat(m.price) + parseFloat(b.price);
          if (total <= budget) validCombos.push({ items: [m, b], total: total });
        });
        desserts.forEach(d => {
          const total = parseFloat(m.price) + parseFloat(d.price);
          if (total <= budget) validCombos.push({ items: [m, d], total: total });
        });
      });
    }

    // 3. If still nothing fits, just find the best single Main Course
    if (validCombos.length === 0) {
      mains.forEach(m => {
        const total = parseFloat(m.price);
        if (total <= budget) validCombos.push({ items: [m], total: total });
      });
    }

    if (validCombos.length === 0) {
      resultDiv.innerHTML = `<div style="color: #dc2626; padding: 1rem; text-align: center;">No items found within budget of ₹${budget}. Try increasing your budget!</div>`;
      return;
    }

    // Sort valid combos by total price descending (closest to budget first)
    validCombos.sort((a, b) => b.total - a.total);

    // To guarantee the items change every spin, grab a large pool of the best combos
    // Anything within ₹25 of the absolute best possible price, capped at 25 combos.
    const bestPrice = validCombos[0].total;
    const topCombos = validCombos.filter(c => c.total >= bestPrice - 25).slice(0, 25);
    
    // Prevent repeating the same items from the last spin
    window.lastRouletteItems = window.lastRouletteItems || [];
    
    // Calculate how many items each combo shares with the last spin
    topCombos.forEach(c => {
      c.overlap = c.items.filter(i => window.lastRouletteItems.includes(i.item_id)).length;
    });

    // Sort by lowest overlap first to ensure maximum variety
    topCombos.sort((a, b) => a.overlap - b.overlap);
    const minOverlap = topCombos[0].overlap;
    const bestFreshCombos = topCombos.filter(c => c.overlap === minOverlap);

    // Pick randomly from the combos with the least overlap
    const bestCombo = bestFreshCombos[Math.floor(Math.random() * bestFreshCombos.length)];
    const combo = bestCombo.items;
    const spent = bestCombo.total;
    
    // Save these items so we don't repeat them next time
    window.lastRouletteItems = combo.map(i => i.item_id);

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
