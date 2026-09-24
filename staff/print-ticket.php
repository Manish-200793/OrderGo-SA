<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'staff']);

$orderId = $_GET['id'] ?? '';
if (empty($orderId)) {
    die("Invalid Order ID");
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM odg_orders WHERE order_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found");
}

$itemStmt = $db->prepare("
    SELECT oi.*, m.name 
    FROM odg_order_items oi 
    JOIN odg_menu_items m ON oi.item_id = m.item_id 
    WHERE oi.order_id = ?
");
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Ticket - <?= htmlspecialchars($orderId) ?></title>
    <style>
        body {
            font-family: monospace;
            width: 300px;
            margin: 0 auto;
            padding: 20px;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .totals {
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9em;
        }
        @media print {
            body { margin: 0; padding: 10px; width: 100%; }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(() => window.close(), 1000);">
    <div class="header">
        <h2 style="margin:0;">ORDERGO</h2>
        <p style="margin:5px 0;">Campus Canteen</p>
        <p style="margin:5px 0;">Order ID: <?= substr($orderId, -6) ?></p>
        <p style="margin:5px 0;">Date: <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
        <?php if (!empty($order['guest_name'])): ?>
            <p style="margin:5px 0;">Customer: <?= htmlspecialchars($order['guest_name']) ?></p>
        <?php endif; ?>
    </div>

    <div class="items">
        <div class="item-row" style="font-weight: bold; border-bottom: 1px solid #000; margin-bottom:10px;">
            <span style="flex: 2;">Item</span>
            <span style="flex: 1; text-align: center;">Qty</span>
            <span style="flex: 1; text-align: right;">Amount</span>
        </div>
        <?php foreach ($items as $item): ?>
            <div class="item-row">
                <span style="flex: 2;"><?= htmlspecialchars($item['name']) ?></span>
                <span style="flex: 1; text-align: center;"><?= $item['quantity'] ?></span>
                <span style="flex: 1; text-align: right;">Rs.<?= $item['price_at_order'] * $item['quantity'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="totals item-row">
        <span>TOTAL DUE</span>
        <span>Rs.<?= $order['total_price'] ?></span>
    </div>

    <div class="footer">
        <p>Thank you for eating with us!</p>
        <p>**** Walk-in POS Ticket ****</p>
    </div>
</body>
</html>
