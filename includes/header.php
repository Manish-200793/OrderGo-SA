<?php
/**
 * Common HTML Header Component
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' . APP_NAME : APP_NAME . ' - ' . APP_TAGLINE;
$currUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Core Design System -->
  <link rel="stylesheet" href="<?= ROOT_PATH ?>/assets/css/main.css?v=<?= file_exists(__DIR__ . '/../assets/css/main.css') ? filemtime(__DIR__ . '/../assets/css/main.css') : time() ?>">
  
  <?php if (!empty($extraCss)): ?>
    <?php foreach ((array)$extraCss as $cssFile): 
      $cssPath = __DIR__ . '/../assets/css/' . $cssFile;
      $cv = file_exists($cssPath) ? filemtime($cssPath) : time();
    ?>
      <link rel="stylesheet" href="<?= ROOT_PATH ?>/assets/css/<?= $cssFile ?>?v=<?= $cv ?>">
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Lucide Icons CDN -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Dark Theme & Global API URL Initialization -->
  <script>
    (function() {
      const savedTheme = localStorage.getItem('ordergo_theme') || 'light';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();

    window.ORDERGO_ROOT_PATH = '<?= ROOT_PATH ?>';
    window.ORDERGO_BASE_URL  = '<?= BASE_URL ?>';

    function apiUrl(endpoint) {
      const root = window.ORDERGO_ROOT_PATH || '';
      const clean = endpoint.replace(/^\/?api\//, '').replace(/^\//, '');
      return root + '/api/' + clean;
    }
  </script>
</head>
<body>
<?php require __DIR__ . '/navbar.php'; ?>
<div id="toast-container"></div>
