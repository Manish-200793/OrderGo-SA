-- OrderGo Database Schema with odg_ Prefix
-- This file contains the exact table structures used by the OrderGo application.

CREATE TABLE IF NOT EXISTS odg_users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student', 'admin', 'staff') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS odg_students (
  student_id INT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(50),
  roll_number VARCHAR(50),
  favorites TEXT,
  FOREIGN KEY (student_id) REFERENCES odg_users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS odg_admins (
  admin_id INT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(50),
  FOREIGN KEY (admin_id) REFERENCES odg_users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS odg_staff (
  staff_id INT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(50),
  FOREIGN KEY (staff_id) REFERENCES odg_users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS odg_menu_items (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  category ENUM('breakfast', 'lunch', 'snacks', 'beverages', 'desserts') NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  stock INT DEFAULT 50,
  image_url VARCHAR(255),
  is_available BOOLEAN DEFAULT 1,
  is_daily_special BOOLEAN DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS odg_orders (
  order_id VARCHAR(50) PRIMARY KEY,
  user_id INT NOT NULL,
  guest_name VARCHAR(255) DEFAULT NULL,
  total_price DECIMAL(10, 2) NOT NULL,
  status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
  payment_method ENUM('cash', 'upi', 'card'),
  payment_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
  qr_code TEXT,
  pickup_type ENUM('pickup', 'delivery') DEFAULT 'pickup',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES odg_users(user_id)
);

CREATE TABLE IF NOT EXISTS odg_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id VARCHAR(50) NOT NULL,
  item_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price_at_order DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES odg_orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES odg_menu_items(item_id)
);

CREATE TABLE IF NOT EXISTS odg_transactions (
  transaction_id VARCHAR(50) PRIMARY KEY,
  order_id VARCHAR(50) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
  gateway_ref VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES odg_orders(order_id)
);

CREATE TABLE IF NOT EXISTS odg_feedback (
  feedback_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  order_id VARCHAR(50),
  rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES odg_users(user_id),
  FOREIGN KEY (item_id) REFERENCES odg_menu_items(item_id),
  FOREIGN KEY (order_id) REFERENCES odg_orders(order_id)
);

-- Password Reset Verification Tokens
CREATE TABLE IF NOT EXISTS odg_password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  code VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_odg_orders_user ON odg_orders(user_id);
CREATE INDEX idx_odg_orders_status ON odg_orders(status);
CREATE INDEX idx_odg_order_items_order ON odg_order_items(order_id);
CREATE INDEX idx_odg_feedback_item ON odg_feedback(item_id);
CREATE INDEX idx_odg_menu_category ON odg_menu_items(category);
CREATE INDEX idx_odg_password_resets_lookup ON odg_password_resets(email, code);

-- --------------------------------------------------------
-- DEFAULT DATA DUMP FOR OUT-OF-THE-BOX USAGE
-- --------------------------------------------------------

-- 1. Default Accounts
-- All accounts use the password: password123
INSERT INTO `odg_users` (`user_id`, `email`, `password_hash`, `role`) VALUES
(1, 'admin@ordergo.com', '$2y$10$hYxxNpdhguug46iPF.r13u2Z.nGvHh8P6oDqjtKtqNLrC7Ahgx0fy', 'admin'),
(2, 'kitchen@ordergo.com', '$2y$10$hYxxNpdhguug46iPF.r13u2Z.nGvHh8P6oDqjtKtqNLrC7Ahgx0fy', 'staff'),
(3, 'student@ordergo.com', '$2y$10$hYxxNpdhguug46iPF.r13u2Z.nGvHh8P6oDqjtKtqNLrC7Ahgx0fy', 'student');

INSERT INTO `odg_admins` (`admin_id`, `name`, `email`, `phone`) VALUES
(1, 'System Admin', 'admin@ordergo.com', '9876543210');

INSERT INTO `odg_staff` (`staff_id`, `name`, `email`, `phone`) VALUES
(2, 'Head Chef', 'kitchen@ordergo.com', '9876543211');

INSERT INTO `odg_students` (`student_id`, `name`, `email`, `phone`, `roll_number`, `cgpa`, `skills`, `preferred_domain`, `status`) VALUES
(3, 'Rahul Student', 'student@ordergo.com', '9876543212', '21N61A0501', 8.5, 'C++, Python', 'Software', 'approved');

-- 2. Default Menu Items (Local Images)
INSERT INTO `odg_menu_items` (`name`, `description`, `category`, `price`, `stock`, `image_url`, `is_available`, `is_daily_special`) VALUES
('Aloo Paratha', 'Stuffed flatbread with spiced potatoes served with curd and pickle.', 'breakfast', 45.00, 50, 'assets/images/aloo-paratha.jpg', 1, 0),
('Chicken Biryani', 'Aromatic basmati rice cooked with tender chicken and spices.', 'lunch', 120.00, 30, 'assets/images/biryani.jpg', 1, 1),
('Butter Naan', 'Soft and fluffy Indian flatbread brushed with butter.', 'lunch', 25.00, 100, 'assets/images/butter-naan.jpg', 1, 0),
('Masala Chai', 'Hot, sweet Indian tea brewed with milk and aromatic spices.', 'beverages', 15.00, 100, 'assets/images/chai.jpg', 1, 0),
('Chole Bhature', 'Spicy chickpea curry served with fried fluffy bread.', 'lunch', 70.00, 40, 'assets/images/chole.jpg', 1, 1),
('Cold Coffee', 'Creamy, thick, and refreshing chilled coffee.', 'beverages', 50.00, 50, 'assets/images/cold-coffee.jpg', 1, 0),
('Egg Fried Rice', 'Stir-fried rice with egg, vegetables, and soy sauce.', 'lunch', 80.00, 40, 'assets/images/egg-fried-rice.jpg', 1, 0),
('Ice Cream', 'Two scoops of classic vanilla/chocolate ice cream.', 'desserts', 40.00, 50, 'assets/images/ice-cream.jpg', 1, 0),
('Idli Sambar', 'Soft steamed rice cakes served with hot lentil soup and chutney.', 'breakfast', 40.00, 60, 'assets/images/idli.jpg', 1, 0),
('Sweet Lassi', 'Traditional yogurt-based cold, sweet drink.', 'beverages', 35.00, 50, 'assets/images/lassi.jpg', 1, 0),
('Fresh Lime Soda', 'Refreshing chilled sweet and salted lime soda.', 'beverages', 30.00, 60, 'assets/images/lime-soda.jpg', 1, 0),
('Masala Maggi', 'Classic hot instant noodles cooked with veggies and spices.', 'snacks', 40.00, 80, 'assets/images/maggi.jpg', 1, 0),
('Masala Dosa', 'Thin crispy crepe served with spicy potato filling and chutney.', 'breakfast', 50.00, 40, 'assets/images/masala-dosa.jpg', 1, 0),
('Paneer Butter Masala', 'Rich and creamy curry made with paneer, spices, onions, and tomatoes.', 'lunch', 110.00, 30, 'assets/images/paneer.jpg', 1, 1),
('Chocolate Pastry', 'Rich chocolate layered cake slice.', 'desserts', 60.00, 30, 'assets/images/pastry.jpg', 1, 0),
('Pav Bhaji', 'Spicy vegetable mash served with butter-toasted buns.', 'snacks', 60.00, 50, 'assets/images/pav-bhaji.jpg', 1, 1),
('Kanda Poha', 'Flattened rice cooked with onions, peanuts, and spices.', 'breakfast', 30.00, 50, 'assets/images/poha.jpg', 1, 0),
('Rajma Chawal', 'Red kidney beans in a thick gravy served with steamed rice.', 'lunch', 75.00, 40, 'assets/images/rajma.jpg', 1, 0),
('Rumali Roti', 'Extremely thin and soft Indian flatbread.', 'lunch', 15.00, 80, 'assets/images/rumali-roti.jpg', 1, 0),
('Samosa', 'Crispy pastry filled with spiced potatoes and peas.', 'snacks', 15.00, 100, 'assets/images/samosa.jpg', 1, 0),
('Upma', 'Thick porridge made from dry-roasted semolina with veggies.', 'breakfast', 35.00, 50, 'assets/images/upma.jpg', 1, 0),
('Veg Fried Rice', 'Stir-fried rice with mixed vegetables and soy sauce.', 'lunch', 70.00, 40, 'assets/images/veg-fried-rice.jpg', 1, 0),
('Veg Thali', 'A wholesome meal with chapati, dal, rice, two curries, and a sweet.', 'lunch', 90.00, 50, 'assets/images/veg-thali.jpg', 1, 0);
