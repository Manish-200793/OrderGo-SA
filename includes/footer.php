<?php
/**
 * Common HTML Footer Component
 */
?>
<footer class="site-footer">
  <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
        Order<strong style="color: var(--accent-primary);">Go</strong> Canteen
      </div>
      <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">
        Smart digital cafeteria management & real-time queue tracker for college campuses.
      </p>
    </div>
    <div style="font-size: 0.8rem;">
      &copy; <?= date('Y') ?> OrderGo. All Rights Reserved.
    </div>
  </div>
</footer>

<!-- Cart Management -->
<script src="<?= ROOT_PATH ?>/assets/js/cart.js"></script>

<!-- Lucide Icons Initialization -->
<script>
  function initIcons() {
    if (window.lucide) {
      lucide.createIcons();
    }
  }
  initIcons();
  document.addEventListener('DOMContentLoaded', initIcons);
  window.addEventListener('load', initIcons);
</script>

<?php if (!empty($extraJs)): ?>
  <?php foreach ((array)$extraJs as $jsFile): 
    $jsPath = __DIR__ . '/../assets/js/' . $jsFile;
    $v = file_exists($jsPath) ? filemtime($jsPath) : time();
  ?>
    <script src="<?= ROOT_PATH ?>/assets/js/<?= $jsFile ?>?v=<?= $v ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
