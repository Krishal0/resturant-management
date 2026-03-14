-- ============================================================
--  NepDine Restaurant Management System - Database Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS nepdine_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nepdine_db;

-- ─── USERS (admin/staff login) ────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    username   VARCHAR(100) NOT NULL UNIQUE,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','staff','user') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin  (password: password)
INSERT INTO users (name, username, email, password, role) VALUES
('Admin', 'admin', 'admin@nepdine.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    role = VALUES(role);

-- ─── WAITERS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS waiters (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    phone      VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── TABLES ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS restaurant_tables (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50)  NOT NULL,
    capacity   INT          DEFAULT 4,
    status     ENUM('available','occupied','reserved') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO restaurant_tables (table_name, capacity)
SELECT * FROM (
    SELECT 'Table 1' AS table_name, 4 AS capacity UNION ALL
    SELECT 'Table 2', 4 UNION ALL
    SELECT 'Table 3', 6 UNION ALL
    SELECT 'Table 4', 2 UNION ALL
    SELECT 'Table 5', 8
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM restaurant_tables LIMIT 1);

-- ─── MENU CATEGORIES ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

INSERT IGNORE INTO categories (category_name) VALUES ('Starter'), ('Main Course'), ('Drinks'), ('Dessert');

-- ─── MENU ITEMS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS menu_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    item_name   VARCHAR(150) NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    available   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

INSERT INTO menu_items (category_id, item_name, price)
SELECT * FROM (
    SELECT 1, 'Spring Rolls', 150.00 UNION ALL
    SELECT 1, 'Chicken Soup', 130.00 UNION ALL
    SELECT 1, 'Garlic Bread', 100.00 UNION ALL
    SELECT 1, 'Fried Momos', 180.00 UNION ALL
    SELECT 1, 'Paneer Tikka', 200.00 UNION ALL
    SELECT 2, 'Chicken Biryani', 350.00 UNION ALL
    SELECT 2, 'Veg Thali', 280.00 UNION ALL
    SELECT 2, 'Grilled Chicken', 420.00 UNION ALL
    SELECT 2, 'Masala Pasta', 300.00 UNION ALL
    SELECT 2, 'Buff Sekuwa', 380.00 UNION ALL
    SELECT 3, 'Fresh Lemon Soda', 80.00 UNION ALL
    SELECT 3, 'Iced Coffee', 120.00 UNION ALL
    SELECT 3, 'Mango Lassi', 130.00 UNION ALL
    SELECT 3, 'Chocolate Shake', 160.00 UNION ALL
    SELECT 3, 'Mineral Water', 50.00
) AS seed(category_id, item_name, price)
WHERE NOT EXISTS (SELECT 1 FROM menu_items LIMIT 1);

-- ─── ORDERS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    table_id     INT NOT NULL,
    waiter_id    INT,
    guest_count  INT DEFAULT 1,
    status       ENUM('open','billed','paid','cancelled') DEFAULT 'open',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id)  REFERENCES restaurant_tables(id),
    FOREIGN KEY (waiter_id) REFERENCES waiters(id) ON DELETE SET NULL
);

-- ─── ORDER ITEMS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity    INT NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)     REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

-- ─── BILLS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bills (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL UNIQUE,
    subtotal      DECIMAL(10,2) NOT NULL,
    tax_percent   DECIMAL(5,2)  DEFAULT 13.00,
    tax_amount    DECIMAL(10,2) NOT NULL,
    total_amount  DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','card','esewa','khalti') DEFAULT 'cash',
    payment_status ENUM('pending','success','failed') DEFAULT 'success',
    esewa_pid      VARCHAR(100) UNIQUE,
    esewa_ref_id   VARCHAR(100),
    generated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
