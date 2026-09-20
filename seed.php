<?php
/**
 * OrderGo Database Seeder (with odg_ prefix)
 * Run via CLI: php seed.php
 * Or via browser: http://localhost:8000/seed.php
 */

require_once __DIR__ . '/config/database.php';

$isCli = (php_sapi_name() === 'cli');

try {
    $db = get_db();

    // Disable foreign key checks
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Clean up legacy non-prefixed tables if they exist
    $legacyTables = ['feedback', 'transactions', 'order_items', 'orders', 'menu_items', 'students', 'admins', 'staff', 'users'];
    foreach ($legacyTables as $lTbl) {
        $db->exec("DROP TABLE IF EXISTS `{$lTbl}`");
    }

    // Ensure odg_ tables exist first
    require_once __DIR__ . '/install.php';

    $tables = ['odg_password_resets', 'odg_feedback', 'odg_transactions', 'odg_order_items', 'odg_orders', 'odg_menu_items', 'odg_students', 'odg_admins', 'odg_staff', 'odg_users'];
    foreach ($tables as $tbl) {
        $db->exec("TRUNCATE TABLE `{$tbl}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Default passwords hashed using standard bcrypt
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $studentPassword = password_hash('student123', PASSWORD_BCRYPT);
    $staffPassword = password_hash('staff123', PASSWORD_BCRYPT);

    // 1. Admin
    $stmt = $db->prepare("INSERT INTO odg_users (email, password_hash, role) VALUES (?, ?, 'admin')");
    $stmt->execute(['admin@ordergo.com', $adminPassword]);
    $adminId = $db->lastInsertId();
    $db->prepare("INSERT INTO odg_admins (admin_id, name, email, phone) VALUES (?, ?, ?, ?)")
       ->execute([$adminId, 'Admin User', 'admin@ordergo.com', '9876543210']);

    // 2. Students
    $students = [
        ['rahul@college.edu', 'Rahul Sharma', '9876543211', 'CS2024001'],
        ['priya@college.edu', 'Priya Patel', '9876543212', 'EC2024015'],
        ['amit@college.edu', 'Amit Kumar', '9876543213', 'ME2024042'],
        ['anirudh@college.edu', 'Anirudh', '9876543214', 'BK2024054'],
    ];

    $studentIds = [];
    foreach ($students as $stu) {
        $stmt = $db->prepare("INSERT INTO odg_users (email, password_hash, role) VALUES (?, ?, 'student')");
        $stmt->execute([$stu[0], $studentPassword]);
        $sId = $db->lastInsertId();
        $studentIds[] = $sId;
        $db->prepare("INSERT INTO odg_students (student_id, name, email, phone, roll_number) VALUES (?, ?, ?, ?, ?)")
           ->execute([$sId, $stu[1], $stu[0], $stu[2], $stu[3]]);
    }

    // 3. Staff
    $stmt = $db->prepare("INSERT INTO odg_users (email, password_hash, role) VALUES (?, ?, 'staff')");
    $stmt->execute(['staff@ordergo.com', $staffPassword]);
    $staffId = $db->lastInsertId();
    $db->prepare("INSERT INTO odg_staff (staff_id, name, email, phone) VALUES (?, ?, ?, ?)")
       ->execute([$staffId, 'Staff User', 'staff@ordergo.com', '9876543220']);

    // 4. Menu Items
    $menuItems = [
        // Breakfast
        ['Masala Dosa', 'Crispy rice crepe with potato filling, served with sambar and chutney', 'breakfast', 60, 40, 'assets/images/masala-dosa.jpg', 1, 1],
        ['Idli Sambar', 'Steamed rice cakes served with sambar and coconut chutney', 'breakfast', 40, 50, 'assets/images/idli.jpg', 1, 0],
        ['Poha', 'Flattened rice cooked with onions, peanuts, and spices', 'breakfast', 30, 45, 'assets/images/poha.jpg', 1, 0],
        ['Aloo Paratha', 'Stuffed potato flatbread served with curd and pickle', 'breakfast', 50, 35, 'assets/images/aloo-paratha.jpg', 1, 0],
        ['Upma', 'Savory semolina porridge with vegetables', 'breakfast', 35, 40, 'assets/images/upma.jpg', 1, 0],

        // Lunch
        ['Veg Thali', 'Complete meal with dal, sabzi, rice, roti, salad, and sweet', 'lunch', 120, 30, 'assets/images/veg-thali.jpg', 1, 1],
        ['Chicken Biryani', 'Fragrant basmati rice layered with spiced chicken', 'lunch', 150, 25, 'assets/images/biryani.jpg', 1, 0],
        ['Paneer Butter Masala', 'Creamy tomato-based curry with cottage cheese, served with naan', 'lunch', 130, 30, 'assets/images/paneer.jpg', 1, 0],
        ['Rajma Chawal', 'Kidney bean curry served with steamed rice', 'lunch', 90, 35, 'assets/images/rajma.jpg', 1, 0],
        ['Chole Bhature', 'Spicy chickpea curry with deep-fried bread', 'lunch', 100, 30, 'assets/images/chole.jpg', 1, 0],
        ['Rumali Roti', 'Thin and soft Indian flatbread', 'lunch', 15, 100, 'assets/images/rumali-roti.jpg', 1, 0],
        ['Butter Naan', 'Soft traditional Indian flatbread baked in tandoor with butter', 'lunch', 35, 80, 'assets/images/butter-naan.jpg', 1, 0],
        ['Egg Fried Rice', 'Wok-tossed rice with egg, vegetables, and soy sauce', 'lunch', 90, 40, 'assets/images/egg-fried-rice.jpg', 1, 0],
        ['Veg Fried Rice', 'Classic Indo-Chinese fried rice with fresh veggies', 'lunch', 80, 50, 'assets/images/veg-fried-rice.jpg', 1, 0],

        // Snacks
        ['Samosa', 'Crispy pastry filled with spiced potatoes and peas', 'snacks', 20, 60, 'assets/images/samosa.jpg', 1, 0],
        ['Pav Bhaji', 'Mashed vegetable curry served with buttered bread rolls', 'snacks', 70, 35, 'assets/images/pav-bhaji.jpg', 1, 0],
        ['Maggi Noodles', 'Quick-cooked instant noodles with vegetables', 'snacks', 40, 45, 'assets/images/maggi.jpg', 1, 0],

        // Beverages
        ['Masala Chai', 'Hot spiced Indian tea with milk', 'beverages', 15, 80, 'assets/images/chai.jpg', 1, 0],
        ['Cold Coffee', 'Chilled coffee blended with milk and ice cream', 'beverages', 60, 40, 'assets/images/cold-coffee.jpg', 1, 1],
        ['Fresh Lime Soda', 'Refreshing lemon soda, sweet or salty', 'beverages', 35, 50, 'assets/images/lime-soda.jpg', 1, 0],
        ['Mango Lassi', 'Thick mango yogurt drink', 'beverages', 50, 35, 'assets/images/lassi.jpg', 1, 0],

        // Desserts
        ['Vanilla Ice Cream', 'Classic creamy vanilla scoop', 'desserts', 40, 50, 'assets/images/ice-cream.jpg', 1, 0],
        ['Black Forest Pastry', 'Classic chocolate sponge cake layered with cherry and cream', 'desserts', 60, 20, 'assets/images/pastry.jpg', 1, 0],
    ];

    $itemStmt = $db->prepare("
        INSERT INTO odg_menu_items (name, description, category, price, stock, image_url, is_available, is_daily_special) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($menuItems as $item) {
        $itemStmt->execute($item);
    }

    // 5. Sample Initial Orders
    $order1Id = 'ORD-' . strtoupper(dechex(time())) . '001';
    $order2Id = 'ORD-' . strtoupper(dechex(time())) . '002';
    $order3Id = 'ORD-' . strtoupper(dechex(time())) . '003';

    // Order 1 (Completed)
    $db->prepare("INSERT INTO odg_orders (order_id, user_id, total_price, status, payment_method, payment_status, qr_code, pickup_type) VALUES (?, ?, ?, 'completed', 'upi', 'success', ?, 'pickup')")
       ->execute([$order1Id, $studentIds[0], 210, "ORDERGO:{$order1Id}:{$studentIds[0]}:210"]);
    $db->prepare("INSERT INTO odg_order_items (order_id, item_id, quantity, price_at_order) VALUES (?, 6, 1, 120), (?, 19, 1, 60), (?, 18, 2, 15)")
       ->execute([$order1Id, $order1Id, $order1Id]);
    $db->prepare("INSERT INTO odg_transactions (transaction_id, order_id, payment_method, amount, status) VALUES (?, ?, 'upi', 210, 'success')")
       ->execute(['TXN-' . strtoupper(dechex(time())) . '001', $order1Id]);

    // Order 2 (Preparing)
    $db->prepare("INSERT INTO odg_orders (order_id, user_id, total_price, status, payment_method, payment_status, qr_code, pickup_type) VALUES (?, ?, ?, 'preparing', 'cash', 'success', ?, 'pickup')")
       ->execute([$order2Id, $studentIds[1], 100, "ORDERGO:{$order2Id}:{$studentIds[1]}:100"]);
    $db->prepare("INSERT INTO odg_order_items (order_id, item_id, quantity, price_at_order) VALUES (?, 1, 1, 60), (?, 15, 2, 20)")
       ->execute([$order2Id, $order2Id]);
    $db->prepare("INSERT INTO odg_transactions (transaction_id, order_id, payment_method, amount, status) VALUES (?, ?, 'cash', 100, 'success')")
       ->execute(['TXN-' . strtoupper(dechex(time())) . '002', $order2Id]);

    // Order 3 (Ready for pickup)
    $db->prepare("INSERT INTO odg_orders (order_id, user_id, total_price, status, payment_method, payment_status, qr_code, pickup_type) VALUES (?, ?, ?, 'ready', 'upi', 'success', ?, 'pickup')")
       ->execute([$order3Id, $studentIds[0], 95, "ORDERGO:{$order3Id}:{$studentIds[0]}:95"]);
    $db->prepare("INSERT INTO odg_order_items (order_id, item_id, quantity, price_at_order) VALUES (?, 16, 1, 70), (?, 18, 1, 25)")
       ->execute([$order3Id, $order3Id]);
    $db->prepare("INSERT INTO odg_transactions (transaction_id, order_id, payment_method, amount, status) VALUES (?, ?, 'upi', 95, 'success')")
       ->execute(['TXN-' . strtoupper(dechex(time())) . '003', $order3Id]);

    // 6. Sample Feedback
    $db->prepare("INSERT INTO odg_feedback (user_id, item_id, order_id, rating, comment) VALUES (?, 6, ?, 5, 'Amazing Veg Thali! Best value meal on campus.')")
       ->execute([$studentIds[0], $order1Id]);
    $db->prepare("INSERT INTO odg_feedback (user_id, item_id, order_id, rating, comment) VALUES (?, 19, ?, 5, 'Cold coffee was smooth, refreshing, and delicious!')")
       ->execute([$studentIds[0], $order1Id]);
    $db->prepare("INSERT INTO odg_feedback (user_id, item_id, order_id, rating, comment) VALUES (?, 1, ?, 4, 'Crispy Masala Dosa, loved the chutney.')")
       ->execute([$studentIds[1], $order2Id]);

    $out = "🎉 OrderGo Database Seeded Successfully (All tables using 'odg_' prefix)!\n\n"
         . "📋 Test Accounts:\n"
         . "   Admin:   admin@ordergo.com / admin123\n"
         . "   Staff:   staff@ordergo.com / staff123\n"
         . "   Student: rahul@college.edu / student123\n"
         . "   Student: priya@college.edu / student123\n";

    if ($isCli) {
        echo $out;
    } else {
        echo "<pre style='font-family:monospace;background:#0d1117;color:#58a6ff;padding:20px;border-radius:10px;'>{$out}</pre>"
           . "<p><a href='index.php' style='font-family:sans-serif;font-weight:bold;'>Go to OrderGo Portal &rarr;</a></p>";
    }
} catch (Exception $e) {
    $err = "❌ Seeding Failed: " . $e->getMessage() . "\n";
    if ($isCli) {
        echo $err;
    } else {
        echo "<h2 style='color:red;'>{$err}</h2>";
    }
}
