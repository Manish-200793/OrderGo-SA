<?php
/**
 * Authentication & Role Guard System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function require_login(string $redirect = null): void {
    if (!is_logged_in()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($uri, '/api/') || str_contains($accept, 'application/json')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Authentication required', 'code' => 'UNAUTHORIZED']);
            exit;
        }
        $target = $redirect ?: $uri ?: (ROOT_PATH . '/index.php');
        header('Location: ' . ROOT_PATH . '/login.php?redirect=' . urlencode($target));
        exit;
    }
}

function require_role($roles): void {
    require_login();
    $roles = (array) $roles;
    $currRole = user_role();

    // Admins have super-user privileges across staff/admin areas
    if ($currRole === 'admin') {
        return;
    }

    if (!in_array($currRole, $roles, true)) {
        http_response_code(403);
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($uri, '/api/') || str_contains($accept, 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Permission denied', 'code' => 'FORBIDDEN']);
            exit;
        }
        die("<h1>403 Forbidden</h1><p>You do not have permission to access this area. <a href='" . ROOT_PATH . "/index.php'>Return Home</a></p>");
    }
}

function login_user(array $userRecord, array $profileRecord = []): void {
    $_SESSION['user'] = [
        'user_id'     => (int)$userRecord['user_id'],
        'email'       => $userRecord['email'],
        'role'        => $userRecord['role'],
        'name'        => $profileRecord['name'] ?? ($userRecord['email']),
        'phone'       => $profileRecord['phone'] ?? '',
        'roll_number' => $profileRecord['roll_number'] ?? '',
    ];
}

function logout_user(): void {
    $_SESSION['user'] = null;
    unset($_SESSION['user']);
    session_destroy();
}
