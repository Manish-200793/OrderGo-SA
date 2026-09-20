<?php
/**
 * OrderGo - College Canteen Management System
 * Global Application Configuration
 */

// Report errors in development
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_name('ORDERGO_SESSION');
    session_start();
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'ordergo');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// App Settings
define('APP_NAME', 'OrderGo');
define('APP_TAGLINE', 'Campus Canteen, Simplified');

// Base URL & Root Path calculation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$appDir  = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');

if (!empty($docRoot) && !empty($appDir) && str_starts_with($appDir, $docRoot)) {
    $subDir = substr($appDir, strlen($docRoot));
    $rootPath = '/' . trim(str_replace('\\', '/', $subDir), '/');
    $rootPath = ($rootPath === '/') ? '' : $rootPath;
} else {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = preg_replace('#/(admin|staff|api|includes)$#i', '', $scriptDir);
    $rootPath = trim(str_replace('\\', '/', $scriptDir), '/');
    $rootPath = ($rootPath === '' || $rootPath === '.') ? '' : '/' . $rootPath;
}

define('ROOT_PATH', $rootPath);
define('BASE_URL', $protocol . $host . $rootPath);

// Uploads directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', ROOT_PATH . '/uploads/');

// Email / SMTP Configuration
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');
define('SMTP_USER', getenv('SMTP_USER') ?: 'admin@specanciens.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'QU6Sob0doq@1');
define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'admin@specanciens.com');
define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'OrderGo Canteen');
