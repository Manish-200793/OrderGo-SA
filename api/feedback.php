<?php
/**
 * OrderGo - Customer Feedback & Reviews API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

// 1. GET REVIEWS
if ($method === 'GET') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) {
        json_response(['success' => false, 'error' => 'Item ID required'], 400);
    }

    $stmt = $db->prepare("
        SELECT f.*, COALESCE(s.name, a.name, st.name, 'Student') as user_name
        FROM odg_feedback f
        JOIN odg_users u ON f.user_id = u.user_id
        LEFT JOIN odg_students s ON u.user_id = s.student_id
        LEFT JOIN odg_admins a ON u.user_id = a.admin_id
        LEFT JOIN odg_staff st ON u.user_id = st.staff_id
        WHERE f.item_id = ?
        ORDER BY f.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$itemId]);
    $reviews = $stmt->fetchAll();

    json_response(['success' => true, 'reviews' => $reviews]);
}

// 2. SUBMIT REVIEW
if ($method === 'POST') {
    require_login();
    $user = current_user();

    $input = json_decode(file_get_contents('php://input'), true);
    $itemId = (int)($input['item_id'] ?? 0);
    $rating = (int)($input['rating'] ?? 5);
    $comment = trim($input['comment'] ?? '');

    if (!$itemId || $rating < 1 || $rating > 5) {
        json_response(['success' => false, 'error' => 'Valid item and rating (1-5) are required'], 400);
    }

    $stmt = $db->prepare("INSERT INTO odg_feedback (user_id, item_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['user_id'], $itemId, $rating, $comment ?: null]);

    json_response(['success' => true, 'message' => 'Thank you for your feedback!']);
}

json_response(['error' => 'Method not allowed'], 405);
