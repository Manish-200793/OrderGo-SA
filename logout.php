<?php
/**
 * OrderGo - Logout Action
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
header('Location: ' . ROOT_PATH . '/index.php');
exit;
