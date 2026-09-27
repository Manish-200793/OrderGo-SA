<?php
/**
 * OrderGo - Home / Landing Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$extraCss = ['home.css', 'menu.css'];

$db = get_db();
// Fetch daily specials
$stmt = $db->query("SELECT * FROM odg_menu_items WHERE is_daily_special = 1 AND is_available = 1 LIMIT 4");
$specials = $stmt->fetchAll();

// Fetch all available menu items for Surprise Me feature
$allItemsStmt = $db->query("SELECT item_id, name, description, category, price, stock, image_url, is_available FROM odg_menu_items WHERE is_available = 1");
$allItems = $allItemsStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="home-page">
  <!-- Hero Section -->
  <section class="hero">
    <div class="container hero-content">
      <div class="hero-text">
        <span class="hero-badge">🚀 College Canteen, Reimagined</span>
        <h1 class="hero-title">
          Order Food <br />
          <span class="hero-gradient">Without the Wait</span>
        </h1>
        <p class="hero-subtitle">
          Skip the queue, order freshly prepared campus meals directly from your device, and pick up with your QR code when it's ready.
        </p>
        <div class="hero-actions">
          <?php if (!is_logged_in()): ?>
            <a href="<?= ROOT_PATH ?>/register.php" class="btn btn-primary btn-lg">
              Get Started <i data-lucide="arrow-right"></i>
            </a>
            <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-secondary btn-lg">View Menu</a>
          <?php elseif (user_role() === 'admin'): ?>
            <a href="<?= ROOT_PATH ?>/admin/index.php" class="btn btn-primary btn-lg">
              Admin Portal <i data-lucide="arrow-right"></i>
            </a>
          <?php elseif (user_role() === 'staff'): ?>
            <a href="<?= ROOT_PATH ?>/staff/index.php" class="btn btn-primary btn-lg">
              Kitchen Dashboard <i data-lucide="arrow-right"></i>
            </a>
          <?php else: ?>
            <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-lg">
              Order Food Now <i data-lucide="arrow-right"></i>
            </a>
            <a href="<?= ROOT_PATH ?>/orders.php" class="btn btn-secondary btn-lg">My Orders</a>
          <?php endif; ?>
        </div>
        <div class="hero-stats">
          <div class="hero-stat">
            <strong>20+</strong>
            <span>Menu Items</span>
          </div>
          <div class="hero-stat-divider"></div>
          <div class="hero-stat">
            <strong>4.8 ★</strong>
            <span>Avg Rating</span>
          </div>
          <div class="hero-stat-divider"></div>
          <div class="hero-stat">
            <strong>~5min</strong>
            <span>Avg Wait</span>
          </div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="hero-card-stack">
          <div class="hero-float-card card-1">
            <span>🍕</span>
            <div><strong>Order Placed</strong><small>Just now</small></div>
          </div>
          <div class="hero-float-card card-2">
            <span>👨‍🍳</span>
            <div><strong>Preparing...</strong><small>Your Veg Thali</small></div>
          </div>
          <div class="hero-float-card card-3">
            <span>✅</span>
            <div><strong>Ready!</strong><small>Counter #3</small></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Daily Specials -->
  <?php if (!empty($specials)): ?>
    <section class="container" style="padding: 3rem 1.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
          <span style="color: var(--accent-primary); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">Chef Recommends</span>
          <h2 style="font-family: var(--font-display); font-size: 2rem; font-weight: 800; color: var(--text-primary);">Today's Specials</h2>
        </div>
        <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-secondary btn-sm">Explore All &rarr;</a>
      </div>

      <div class="menu-grid">
        <?php foreach ($specials as $item): ?>
          <div class="glass-card menu-card" data-cart-item-id="<?= $item['item_id'] ?>">
            <span class="badge badge-special menu-card-badge">⭐ Daily Special</span>
            <div class="menu-card-image" onclick="window.location.href='menu.php?item=<?= $item['item_id'] ?>'">
              <img src="<?= get_image_url($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="menu-card-img" loading="lazy">
            </div>
            <div class="menu-card-content">
              <div class="menu-card-header">
                <h3 class="menu-card-name" onclick="window.location.href='menu.php?item=<?= $item['item_id'] ?>'"><?= htmlspecialchars($item['name']) ?></h3>
                <span class="menu-card-price"><?= format_price($item['price']) ?></span>
              </div>
              <p class="menu-card-desc"><?= htmlspecialchars($item['description']) ?></p>
              <div class="menu-card-actions">
                <?php if (is_logged_in()): ?>
                  <button class="btn-add-cart" 
                          onclick="Cart.addItem({item_id: <?= $item['item_id'] ?>, name: '<?= addslashes($item['name']) ?>', price: <?= $item['price'] ?>, image_url: '<?= addslashes(get_image_url($item['image_url'])) ?>'})">
                    <span class="show-on-mobile">ADD</span>
                    <span class="hide-on-mobile"><i data-lucide="plus" style="width:16px;height:16px;vertical-align:middle;"></i> Add to Cart</span>
                  </button>
                  <div class="quantity-control" style="display: none;">
                    <button class="qty-btn" onclick="Cart.updateQuantity(<?= $item['item_id'] ?>, Cart.getItems().find(i => i.item_id === <?= $item['item_id'] ?>)?.quantity - 1 || 0)">-</button>
                    <span class="qty-value">1</span>
                    <button class="qty-btn" onclick="Cart.updateQuantity(<?= $item['item_id'] ?>, (Cart.getItems().find(i => i.item_id === <?= $item['item_id'] ?>)?.quantity || 0) + 1)">+</button>
                  </div>
                <?php else: ?>
                  <a href="<?= ROOT_PATH ?>/login.php" class="btn-login-order">
                    <span class="show-on-mobile">ADD</span>
                    <span class="hide-on-mobile"><i data-lucide="log-in" style="width:16px;height:16px;vertical-align:middle;"></i> Login to Order</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Features Section -->
  <section class="features-section">
    <div class="container">
      <div class="text-center" style="margin-bottom: 3rem;">
        <h2 class="page-title">Why Choose <span class="hero-gradient">OrderGo</span>?</h2>
        <p class="page-subtitle">A friction-free campus dining experience built for students and kitchen staff</p>
      </div>
      <div class="features-grid">
        <div class="glass-card feature-card">
          <div class="feature-icon"><i data-lucide="zap"></i></div>
          <h3>Skip the Queue</h3>
          <p>No more standing in packed canteen lines during short recess breaks. Order straight from your seat.</p>
        </div>
        <div class="glass-card feature-card">
          <div class="feature-icon"><i data-lucide="qr-code"></i></div>
          <h3>Fast QR Pickup</h3>
          <p>Receive an encrypted order QR pass. Counter staff scan your screen and hand you your warm meal in seconds.</p>
        </div>
        <div class="glass-card feature-card">
          <div class="feature-icon"><i data-lucide="clock"></i></div>
          <h3>Live Order Tracking</h3>
          <p>Real-time status progression from preparation to ready-for-pickup with public canteen TV sync.</p>
        </div>
        <div class="glass-card feature-card">
          <div class="feature-icon"><i data-lucide="star"></i></div>
          <h3>Ratings & Reviews</h3>
          <p>Rate each food item after tasting to help kitchen staff improve recipes and reward popular dishes.</p>
        </div>
        <div class="glass-card feature-card">
          <div class="feature-icon"><i data-lucide="credit-card"></i></div>
          <h3>UPI & Cash Payments</h3>
          <p>Seamless support for instant digital payments via UPI or counter cash payment upon collection.</p>
        </div>
        <div class="glass-card feature-card">
          <div class="feature-icon"><i data-lucide="tv"></i></div>
          <h3>Public Kitchen TV Queue</h3>
          <p>Digital display board for canteen counters to call out order numbers ready for pick up.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="cta-section">
    <div class="container text-center">
      <div class="glass-card cta-card">
        <h2>Hungry? Skip the wait today!</h2>
        <p>Browse our fresh campus cafeteria menu and order your favorites now.</p>
        <a href="<?= ROOT_PATH ?>/menu.php" class="btn btn-primary btn-lg cta-btn">
          Browse Menu <i data-lucide="arrow-right"></i>
        </a>
      </div>
    </div>
  </section>
</div>

<?php if (is_logged_in()): ?>
  <!-- Floating Surprise Me / Budget Roulette Button (Bottom Right) -->
  <button class="floating-roulette-btn" onclick="openSurpriseModal()" title="Surprise Me! Budget Roulette">
    <i data-lucide="dices" style="width: 20px; height: 20px;"></i> Surprise Me!
  </button>

  <!-- Budget Roulette / Surprise Modal -->
  <div id="surprise-modal" class="modal-overlay" onclick="if(event.target===this) closeSurpriseModal()">
    <div class="modal-content text-center surprise-modal-content">
      <div class="modal-header" style="margin-bottom: 0.5rem; justify-content: space-between; align-items: center; display: flex;">
        <h3 style="font-family: var(--font-display); font-weight: 800; color: var(--accent-primary-hover); display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: 1.5rem;">
          🎲 Budget Roulette
        </h3>
        <button class="btn-close" onclick="closeSurpriseModal()">&times;</button>
      </div>
      <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem; margin-bottom: 1.25rem;">
        Can't decide? Enter your budget and let OrderGo craft the perfect meal combo for you!
      </p>

      <div style="margin-bottom: 1.25rem; position: relative;">
        <input type="number" id="surprise-budget" class="form-input text-center" placeholder="Enter Budget (e.g. 100)" value="100" min="20" max="500" style="font-size: 1.5rem; font-weight: 800; text-align: center;">
      </div>

      <button class="btn btn-primary btn-lg w-full" style="width: 100%; font-weight: 700;" onclick="spinSurpriseRoulette()">
        Spin the Wheel! 🎰
      </button>

      <div id="surprise-roulette-result" style="margin-top: 1.25rem; text-align: left;"></div>
    </div>
  </div>

  <script>
    const allMenuItems = <?= json_encode($allItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function openSurpriseModal() {
      document.getElementById('surprise-modal').classList.add('active');
    }

    function closeSurpriseModal() {
      document.getElementById('surprise-modal').classList.remove('active');
    }

    function spinSurpriseRoulette() {
      const budget = parseFloat(document.getElementById('surprise-budget').value) || 100;
      const avail = allMenuItems.filter(i => (i.is_available == 1 || i.is_available === true) && parseInt(i.stock) > 0);
      const resultDiv = document.getElementById('surprise-roulette-result');
      resultDiv.innerHTML = '<div style="text-align: center; padding: 1.25rem; color: var(--text-secondary);"><span style="font-size: 1.5rem;">🎲</span><br>Selecting best delicious combo...</div>';

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

        // 3. If still nothing fits, just find single items within budget
        if (validCombos.length === 0) {
          avail.forEach(m => {
            const total = parseFloat(m.price);
            if (total <= budget) validCombos.push({ items: [m], total: total });
          });
        }

        if (validCombos.length === 0) {
          resultDiv.innerHTML = `<div style="color: #dc2626; padding: 1rem; text-align: center; font-weight: 600;">No items found within budget of ₹${budget}. Try increasing your budget!</div>`;
          return;
        }

        // Sort valid combos by total price descending (closest to budget first)
        validCombos.sort((a, b) => b.total - a.total);

        // Filter top combos within ₹25 of best price (max 25 pool)
        const bestPrice = validCombos[0].total;
        const topCombos = validCombos.filter(c => c.total >= bestPrice - 25).slice(0, 25);
        
        // Prevent repeating the same items from the last spin
        window.lastRouletteItems = window.lastRouletteItems || [];
        
        topCombos.forEach(c => {
          c.overlap = c.items.filter(i => window.lastRouletteItems.includes(i.item_id)).length;
        });

        topCombos.sort((a, b) => a.overlap - b.overlap);
        const minOverlap = topCombos[0].overlap;
        const bestFreshCombos = topCombos.filter(c => c.overlap === minOverlap);

        const bestCombo = bestFreshCombos[Math.floor(Math.random() * bestFreshCombos.length)];
        const combo = bestCombo.items;
        const spent = bestCombo.total;
        
        window.lastRouletteItems = combo.map(i => i.item_id);

        const comboJson = JSON.stringify(combo).replace(/"/g, '&quot;');

        resultDiv.innerHTML = `
          <div class="glass-card" style="padding: 1rem; border: 1.5px solid var(--accent-primary); border-radius: var(--radius-lg);">
            <h4 style="color: var(--accent-primary); margin-bottom: 0.5rem; font-weight: 700;">🎉 Chef's Special Combo:</h4>
            <ul style="margin-bottom: 0.75rem; padding: 0; list-style: none;">
              ${combo.map(i => `<li style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:0.9rem; color: var(--text-primary);"><span>${i.name}</span><strong>₹${parseFloat(i.price).toFixed(0)}</strong></li>`).join('')}
            </ul>
            <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-subtle); padding-top: 0.5rem; font-weight: 800; font-size: 1rem; color: var(--text-primary);">
              <span>Total Spent:</span>
              <span style="color: var(--accent-primary);">₹${spent.toFixed(0)}</span>
            </div>
            <button class="btn btn-primary btn-sm w-full" style="width: 100%; margin-top: 0.75rem; font-weight: 700;" onclick="addSurpriseComboToCart(${comboJson})">
              Add Combo to Cart 🛒
            </button>
          </div>
        `;
      }, 400);
    }

    function addSurpriseComboToCart(combo) {
      if (Array.isArray(combo) && window.Cart) {
        combo.forEach(item => {
          Cart.addItem({
            item_id: parseInt(item.item_id),
            name: item.name,
            price: parseFloat(item.price),
            image_url: item.image_url
          });
        });
        closeSurpriseModal();
      }
    }
  </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
