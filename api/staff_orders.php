<?php
/**
 * OrderGo - Staff Kitchen Live Orders API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

// 1. GET ORDERS & STATS
if ($method === 'GET') {
    $filter = $_GET['status'] ?? 'active';

    $sql = "
        SELECT o.*, u.email as customer_email,
               COALESCE(o.guest_name, s.name, a.name, st.name) as customer_name,
               s.roll_number as customer_roll_number,
               COALESCE(s.phone, a.phone, st.phone) as customer_phone
        FROM odg_orders o
        LEFT JOIN odg_users u ON o.user_id = u.user_id
        LEFT JOIN odg_students s ON u.user_id = s.student_id
        LEFT JOIN odg_admins a ON u.user_id = a.admin_id
        LEFT JOIN odg_staff st ON u.user_id = st.staff_id
        WHERE 1=1
    ";
    $params = [];

    if ($filter === 'active') {
        $sql .= " AND o.status IN ('pending', 'preparing', 'ready')";
    } elseif (!empty($filter)) {
        $sql .= " AND o.status = ?";
        $params[] = $filter;
    }

    $sql .= " ORDER BY o.created_at DESC LIMIT 50";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Attach items to each order
    foreach ($orders as &$ord) {
        $iStmt = $db->prepare("
            SELECT oi.*, mi.name, mi.image_url 
            FROM odg_order_items oi 
            JOIN odg_menu_items mi ON oi.item_id = mi.item_id 
            WHERE oi.order_id = ?
        ");
        $iStmt->execute([$ord['order_id']]);
        $ord['items'] = $iStmt->fetchAll();
    }
    unset($ord);

    // Compute live stats
    $stats = [
        'pending'         => (int)$db->query("SELECT COUNT(*) FROM odg_orders WHERE status = 'pending'")->fetchColumn(),
        'preparing'       => (int)$db->query("SELECT COUNT(*) FROM odg_orders WHERE status = 'preparing'")->fetchColumn(),
        'ready'           => (int)$db->query("SELECT COUNT(*) FROM odg_orders WHERE status = 'ready'")->fetchColumn(),
        'completed_today' => (int)$db->query("SELECT COUNT(*) FROM odg_orders WHERE status = 'completed' AND DATE(updated_at) = CURDATE()")->fetchColumn(),
        'cancelled_today' => (int)$db->query("SELECT COUNT(*) FROM odg_orders WHERE status = 'cancelled' AND DATE(updated_at) = CURDATE()")->fetchColumn(),
    ];

    json_response(['success' => true, 'orders' => $orders, 'stats' => $stats]);
}

// 2. PUT - UPDATE STATUS
if ($method === 'PUT') {
    require_role(['staff', 'admin']);

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = trim($input['order_id'] ?? '');
    $status = trim($input['status'] ?? '');

    $validStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        json_response(['success' => false, 'error' => 'Invalid status transition'], 400);
    }

    // Staff cannot bypass QR verification to complete an order; must scan customer QR
    if ($status === 'completed' && user_role() === 'staff') {
        json_response([
            'success' => false, 
            'error' => 'Orders cannot be completed manually. Please scan the student QR code to verify and complete pickup.'
        ], 403);
    }

    $oStmt = $db->prepare("SELECT * FROM odg_orders WHERE order_id = ?");
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch();

    if (!$order) {
        json_response(['success' => false, 'error' => 'Order not found'], 404);
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE odg_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?");
        $stmt->execute([$status, $orderId]);

        // If cancelled, restore inventory
        if ($status === 'cancelled') {
            $iStmt = $db->prepare("SELECT item_id, quantity FROM odg_order_items WHERE order_id = ?");
            $iStmt->execute([$orderId]);
            $items = $iStmt->fetchAll();

            $rStmt = $db->prepare("UPDATE odg_menu_items SET stock = stock + ?, is_available = 1 WHERE item_id = ?");
            foreach ($items as $item) {
                $rStmt->execute([$item['quantity'], $item['item_id']]);
            }
        }

        // If completed and cash, mark payment success
        if ($status === 'completed' && $order['payment_method'] === 'cash') {
            $db->prepare("UPDATE odg_orders SET payment_status = 'success' WHERE order_id = ?")->execute([$orderId]);
            $db->prepare("UPDATE odg_transactions SET status = 'success' WHERE order_id = ?")->execute([$orderId]);
        }

        $db->commit();
        json_response(['success' => true, 'message' => "Order status updated to {$status}!"]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
