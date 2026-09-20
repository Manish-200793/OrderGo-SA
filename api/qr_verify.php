<?php
/**
 * OrderGo - QR Verification Endpoint
 * Scans customer QR code at counter and completes pickup
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_role(['staff', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$qrData = trim($input['qr_data'] ?? '');

if (empty($qrData)) {
    json_response(['success' => false, 'error' => 'QR data is required'], 400);
}

$db = get_db();

// 1. Try exact match on qr_code or order_id
$stmt = $db->prepare("
    SELECT o.*, u.email as customer_email,
           COALESCE(o.guest_name, s.name, a.name, st.name) as customer_name
    FROM odg_orders o
    LEFT JOIN odg_users u ON o.user_id = u.user_id
    LEFT JOIN odg_students s ON u.user_id = s.student_id
    LEFT JOIN odg_admins a ON u.user_id = a.admin_id
    LEFT JOIN odg_staff st ON u.user_id = st.staff_id
    WHERE o.qr_code = ? OR o.order_id = ?
");
$stmt->execute([$qrData, $qrData]);
$order = $stmt->fetch();

// 2. If not matched, try parsing ORDERGO:order_id:user_id:total
if (!$order && str_starts_with($qrData, 'ORDERGO:')) {
    $parts = explode(':', $qrData);
    if (isset($parts[1])) {
        $extractedId = $parts[1];
        $stmt->execute([$extractedId, $extractedId]);
        $order = $stmt->fetch();
    }
}

// 3. If still not matched, check if input matches voucher suffix (e.g. #CA75FB or CA75FB)
if (!$order) {
    $cleanCode = ltrim($qrData, '#');
    if (strlen($cleanCode) >= 4) {
        $sStmt = $db->prepare("
            SELECT o.*, u.email as customer_email,
                   COALESCE(o.guest_name, s.name, a.name, st.name) as customer_name
            FROM odg_orders o
            LEFT JOIN odg_users u ON o.user_id = u.user_id
            LEFT JOIN odg_students s ON u.user_id = s.student_id
            LEFT JOIN odg_admins a ON u.user_id = a.admin_id
            LEFT JOIN odg_staff st ON u.user_id = st.staff_id
            WHERE o.order_id = ? 
               OR o.order_id LIKE ? 
               OR o.qr_code LIKE ?
            LIMIT 1
        ");
        $sStmt->execute(['ORD-' . $cleanCode, '%' . $cleanCode, '%' . $cleanCode . '%']);
        $order = $sStmt->fetch();
    }
}

if (!$order) {
    json_response(['success' => false, 'error' => 'Order not found for this QR voucher code.'], 404);
}

if ($order['status'] === 'completed') {
    json_response(['success' => false, 'error' => 'This order has already been picked up and completed.'], 400);
}

if ($order['status'] === 'cancelled') {
    json_response(['success' => false, 'error' => 'This order was cancelled.'], 400);
}

try {
    $db->beginTransaction();

    $uStmt = $db->prepare("
        UPDATE odg_orders 
        SET status = 'completed', 
            payment_status = IF(payment_method = 'cash', 'success', payment_status),
            updated_at = CURRENT_TIMESTAMP 
        WHERE order_id = ?
    ");
    $uStmt->execute([$order['order_id']]);

    if ($order['payment_method'] === 'cash') {
        $db->prepare("UPDATE odg_transactions SET status = 'success' WHERE order_id = ?")
           ->execute([$order['order_id']]);
    }

    $db->commit();

    json_response([
        'success' => true,
        'message' => 'Pickup verified successfully! Order completed.',
        'order'   => [
            'order_id'      => $order['order_id'],
            'customer_name' => $order['customer_name'],
            'total_price'   => $order['total_price'],
            'status'        => 'completed'
        ]
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
