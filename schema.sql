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

-- 2. Default Menu Items (with high-quality Unsplash image URLs)
INSERT INTO `odg_menu_items` (`name`, `description`, `category`, `price`, `stock`, `image_url`, `is_available`, `is_daily_special`) VALUES
('Veg Thali', 'A wholesome meal with chapati, dal, rice, and two curries.', 'lunch', 80.00, 50, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?q=80&w=2070&auto=format&fit=crop', 1, 1),
('Chicken Biryani', 'Aromatic basmati rice cooked with tender chicken and spices.', 'lunch', 120.00, 30, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?q=80&w=2010&auto=format&fit=crop', 1, 1),
('Samosa', 'Crispy pastry filled with spiced potatoes and peas.', 'snacks', 15.00, 100, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?q=80&w=2070&auto=format&fit=crop', 1, 0),
('Masala Dosa', 'Thin crepe served with spicy potato filling and chutney.', 'breakfast', 50.00, 40, 'https://images.unsplash.com/photo-1589301760014-d929f39ce9b0?q=80&w=2070&auto=format&fit=crop', 1, 0),
('Cold Coffee', 'Creamy and refreshing chilled coffee.', 'beverages', 40.00, 50, 'https://images.unsplash.com/photo-1572490122747-3968b75bb8fc?q=80&w=1974&auto=format&fit=crop', 1, 0),
('Gulab Jamun', 'Sweet milk-solid balls soaked in sugar syrup.', 'desserts', 30.00, 60, 'https://images.unsplash.com/photo-1589114471223-ecc2a5dfb064?q=80&w=1974&auto=format&fit=crop', 1, 0);
