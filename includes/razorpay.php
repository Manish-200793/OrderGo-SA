<?php
/**
 * OrderGo - Razorpay Payment Gateway Helper Library
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Create a new order on Razorpay servers
 * 
 * @param string $orderId Internal OrderGo order reference ID
 * @param float $amount Total amount in INR
 * @return array ['success' => bool, 'razorpay_order_id' => string|null, 'error' => string|null]
 */
function razorpay_create_order(string $orderId, float $amount): array {
    $amountInPaise = (int)round($amount * 100);

    $payload = json_encode([
        'amount'   => $amountInPaise,
        'currency' => RAZORPAY_CURRENCY,
        'receipt'  => $orderId,
        'notes'    => [
            'order_id' => $orderId,
            'system'   => 'OrderGo Canteen'
        ]
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'cURL Error: ' . $curlError];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['id'])) {
        return [
            'success'           => true,
            'razorpay_order_id' => $data['id'],
            'amount'            => $data['amount'],
            'currency'          => $data['currency']
        ];
    }

    $errMessage = $data['error']['description'] ?? 'Failed to initialize payment gateway order.';
    return ['success' => false, 'error' => $errMessage];
}

/**
 * Verify Razorpay payment signature
 * 
 * @param string $razorpayOrderId
 * @param string $razorpayPaymentId
 * @param string $razorpaySignature
 * @return bool
 */
function razorpay_verify_signature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool {
    if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
        return false;
    }

    $data = $razorpayOrderId . '|' . $razorpayPaymentId;
    $expectedSignature = hash_hmac('sha256', $data, RAZORPAY_KEY_SECRET);

    return hash_equals($expectedSignature, $razorpaySignature);
}
