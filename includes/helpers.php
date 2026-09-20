<?php
/**
 * Global Utility & Formatting Helpers
 */

require_once __DIR__ . '/../config/config.php';

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function format_price($price): string {
    $val = floatval($price);
    return '₹' . number_format($val, (floor($val) == $val ? 0 : 2));
}

function generate_order_id(): string {
    return 'ORD-' . strtoupper(dechex(time())) . strtoupper(bin2hex(random_bytes(2)));
}

function generate_txn_id(): string {
    return 'TXN-' . strtoupper(dechex(time())) . strtoupper(bin2hex(random_bytes(2)));
}

function json_response($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function get_image_url(?string $path): string {
    if (empty($path)) {
        return ROOT_PATH . '/assets/images/placeholder.jpg';
    }
    // If it starts with http, return as is
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    // If it starts with /images/, map to /assets/images/
    if (str_starts_with($path, '/images/')) {
        return ROOT_PATH . '/assets' . $path;
    }
    // If it already starts with assets/ or uploads/
    if (str_starts_with($path, 'assets/') || str_starts_with($path, 'uploads/')) {
        return ROOT_PATH . '/' . $path;
    }
    if (str_starts_with($path, '/')) {
        return ROOT_PATH . $path;
    }
    return ROOT_PATH . '/' . $path;
}

function render_status_badge(string $status): string {
    $badges = [
        'pending'   => ['class' => 'badge-pending',   'label' => 'Pending',   'icon' => 'clock'],
        'preparing' => ['class' => 'badge-preparing', 'label' => 'Preparing', 'icon' => 'cooking-pot'],
        'ready'     => ['class' => 'badge-ready',     'label' => 'Ready',     'icon' => 'check-circle-2'],
        'completed' => ['class' => 'badge-completed', 'label' => 'Completed', 'icon' => 'package-check'],
        'cancelled' => ['class' => 'badge-cancelled', 'label' => 'Cancelled', 'icon' => 'x-circle'],
    ];

    $info = $badges[strtolower($status)] ?? ['class' => 'badge-default', 'label' => ucfirst($status), 'icon' => 'help-circle'];
    return "<span class=\"badge {$info['class']}\"><i data-lucide=\"{$info['icon']}\"></i> {$info['label']}</span>";
}
