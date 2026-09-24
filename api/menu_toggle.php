<?php
/**
 * OrderGo - Admin Menu Item Quick Toggle API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_role(['admin', 'staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($input['item_id'] ?? 0);
$type = $input['type'] ?? '';
$val = (int)($input['value'] ?? 0);

if (!$itemId || !in_array($type, ['available', 'special'])) {
    json_response(['success' => false, 'error' => 'Invalid parameters'], 400);
}

$column = ($type === 'available') ? 'is_available' : 'is_daily_special';
$db = get_db();
$stmt = $db->prepare("UPDATE odg_menu_items SET {$column} = ? WHERE item_id = ?");
$stmt->execute([$val, $itemId]);

json_response(['success' => true, 'message' => 'Item updated successfully']);
