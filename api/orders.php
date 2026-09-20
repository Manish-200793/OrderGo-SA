<?php
/**
 * OrderGo - Orders API Endpoint
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// 1. GET STATUS
if ($method === 'GET' && $action === 'get_status') {
    $orderId = $_GET['id'] ?? '';
    $stmt = $db->prepare("SELECT status, payment_status FROM odg_orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $res = $stmt->fetch();
    if ($res) {
        json_response(['success' => true, 'status' => $res['status'], 'payment_status' => $res['payment_status']]);
    } else {
        json_response(['success' => false, 'error' => 'Order not found'], 404);
    }
}

// 2. CONFIRM PAYMENT
if ($method === 'POST' && $action === 'pay') {
    require_login();
    $orderId = $_GET['id'] ?? '';
    $user = current_user();

    $stmt = $db->prepare("SELECT * FROM odg_orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        json_response(['success' => false, 'error' => 'Order not found'], 404);
    }

    if ($order['user_id'] !== $user['user_id'] && $user['role'] !== 'admin' && $user['role'] !== 'staff') {
        json_response(['success' => false, 'error' => 'Unauthorized'], 403);
    }

    $db->prepare("UPDATE odg_orders SET payment_status = 'success', updated_at = CURRENT_TIMESTAMP WHERE order_id = ?")
       ->execute([$orderId]);
    $db->prepare("UPDATE odg_transactions SET status = 'success' WHERE order_id = ?")
       ->execute([$orderId]);

    json_response(['success' => true, 'message' => 'Payment verified successfully']);
}

// 3. CREATE ORDER
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        json_response(['success' => false, 'error' => 'Invalid JSON input'], 400);
    }

    $items = $input['items'] ?? [];
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $pickupType = $input['pickup_type'] ?? 'pickup';
    $guestName = trim($input['guest_name'] ?? '');

    if (empty($items)) {
        json_response(['success' => false, 'error' => 'Order must contain at least one item.'], 400);
    }

    $user = current_user();
    // Allow guest walk-ins if staff creates it, otherwise require login
    if (!$user) {
        json_response(['success' => false, 'error' => 'Authentication required'], 401);
    }
    $userId = $user['user_id'];

    try {
        $db->beginTransaction();

        $totalPrice = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $itemId = (int)($item['item_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 1);

            $mStmt = $db->prepare("SELECT * FROM odg_menu_items WHERE item_id = ? FOR UPDATE");
            $mStmt->execute([$itemId]);
            $menuItem = $mStmt->fetch();

            if (!$menuItem) {
                $db->rollBack();
                json_response(['success' => false, 'error' => "Item not found."], 400);
            }
            if (!$menuItem['is_available']) {
                $db->rollBack();
                json_response(['success' => false, 'error' => "\"{$menuItem['name']}\" is currently unavailable."], 400);
            }
            if ($menuItem['stock'] < $qty) {
                $db->rollBack();
                json_response(['success' => false, 'error' => "Not enough stock for \"{$menuItem['name']}\". Available: {$menuItem['stock']}"], 400);
            }

            $lineTotal = floatval($menuItem['price']) * $qty;
            $totalPrice += $lineTotal;
            $orderItems[] = [
                'item_id'        => $menuItem['item_id'],
                'name'           => $menuItem['name'],
                'quantity'       => $qty,
                'price_at_order' => $menuItem['price']
            ];
        }

        $orderId = generate_order_id();
        $qrCode = "ORDERGO:{$orderId}:{$userId}:{$totalPrice}";
        $paymentStatus = ($paymentMethod === 'cash') ? 'pending' : 'pending';
        $orderStatus = 'pending';

        // Insert Order
        $oStmt = $db->prepare("
            INSERT INTO odg_orders (order_id, user_id, guest_name, total_price, status, payment_method, payment_status, qr_code, pickup_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $oStmt->execute([
            $orderId, 
            $userId, 
            $guestName ?: null, 
            $totalPrice, 
            $orderStatus, 
            $paymentMethod, 
            $paymentStatus, 
            $qrCode, 
            $pickupType
        ]);

        // Insert Items and deduct stock
        $oiStmt = $db->prepare("INSERT INTO odg_order_items (order_id, item_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
        $stkStmt = $db->prepare("UPDATE odg_menu_items SET stock = stock - ?, is_available = IF(stock - ? <= 0, 0, 1) WHERE item_id = ?");

        foreach ($orderItems as $oi) {
            $oiStmt->execute([$orderId, $oi['item_id'], $oi['quantity'], $oi['price_at_order']]);
            $stkStmt->execute([$oi['quantity'], $oi['quantity'], $oi['item_id']]);
        }

        // Insert Transaction Record
        $txnId = generate_txn_id();
        $tStmt = $db->prepare("INSERT INTO odg_transactions (transaction_id, order_id, payment_method, amount, status) VALUES (?, ?, ?, ?, ?)");
        $tStmt->execute([$txnId, $orderId, $paymentMethod, $totalPrice, $paymentStatus]);

        $db->commit();

        json_response([
            'success' => true,
            'message' => 'Order placed successfully!',
            'order'   => [
                'order_id'       => $orderId,
                'total_price'    => $totalPrice,
                'status'         => $orderStatus,
                'payment_method' => $paymentMethod,
                'qr_code'        => $qrCode
            ]
        ], 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json_response(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
