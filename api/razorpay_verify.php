<?php
/**
 * OrderGo - Razorpay Payment Verification Endpoint
 * Validates HMAC-SHA256 signature from Razorpay checkout
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

require_login();
$user = current_user();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['success' => false, 'error' => 'Invalid JSON payload'], 400);
}

$orderId = trim($input['order_id'] ?? '');
$razorpayPaymentId = trim($input['razorpay_payment_id'] ?? '');
$razorpayOrderId = trim($input['razorpay_order_id'] ?? '');
$razorpaySignature = trim($input['razorpay_signature'] ?? '');
$isSimulated = !empty($input['simulate']);

if (empty($orderId)) {
    json_response(['success' => false, 'error' => 'Order reference ID is required'], 400);
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM odg_orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    json_response(['success' => false, 'error' => 'Order not found or unauthorized'], 404);
}

if ($order['payment_status'] === 'success') {
    json_response([
        'success'  => true,
        'message'  => 'Order is already paid and confirmed.',
        'order_id' => $orderId
    ]);
}

// 1. Verify Signature
$isValid = false;

if ($isSimulated && PAYMENT_MODE === 'test') {
    // Demo simulation fallback
    $isValid = true;
    $razorpayPaymentId = 'pay_sim_' . strtoupper(bin2hex(random_bytes(6)));
} else {
    // Official cryptographic HMAC-SHA256 verification
    $isValid = razorpay_verify_signature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);
}

if (!$isValid) {
    json_response([
        'success' => false,
        'error'   => 'Payment signature verification failed. Tampered or invalid transaction.'
    ], 400);
}

// 2. Mark order and transaction as success in database
try {
    $db->beginTransaction();

    $uStmt = $db->prepare("
        UPDATE odg_orders 
        SET payment_status = 'success', 
            payment_method = 'upi', 
            updated_at = CURRENT_TIMESTAMP 
        WHERE order_id = ?
    ");
    $uStmt->execute([$orderId]);

    // Update or insert transaction record
    $tCheck = $db->prepare("SELECT transaction_id FROM odg_transactions WHERE order_id = ?");
    $tCheck->execute([$orderId]);
    $existingTxn = $tCheck->fetch();

    if ($existingTxn) {
        $tUpdate = $db->prepare("
            UPDATE odg_transactions 
            SET status = 'success', 
                gateway_ref = ?, 
                payment_method = 'upi' 
            WHERE order_id = ?
        ");
        $tUpdate->execute([$razorpayPaymentId, $orderId]);
    } else {
        $txnId = generate_txn_id();
        $tInsert = $db->prepare("
            INSERT INTO odg_transactions (transaction_id, order_id, payment_method, amount, status, gateway_ref) 
            VALUES (?, ?, 'upi', ?, 'success', ?)
        ");
        $tInsert->execute([$txnId, $orderId, $order['total_price'], $razorpayPaymentId]);
    }

    $db->commit();

    json_response([
        'success'    => true,
        'message'    => 'Payment verified successfully!',
        'payment_id' => $razorpayPaymentId,
        'order_id'   => $orderId
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response([
        'success' => false, 
        'error'   => 'Database error while recording payment: ' . $e->getMessage()
    ], 500);
}
