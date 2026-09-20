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

