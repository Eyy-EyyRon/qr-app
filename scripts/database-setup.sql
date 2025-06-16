-- Create database and tables for QR Code Generator Application

CREATE DATABASE IF NOT EXISTS qr_generator_app;
USE qr_generator_app;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('pending', 'approved', 'blocked') DEFAULT 'pending',
    profile_image VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#3B82F6',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    qr_code_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- QR Code scans tracking table
CREATE TABLE qr_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    location VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert demo admin user (password: password)
INSERT INTO users (name, email, password, role, status, phone, address) VALUES 
('Admin User', 'admin@qrgen.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved', '+63 912 345 6789', 'Manila, Philippines');

-- Insert demo regular user (password: password)
INSERT INTO users (name, email, password, role, status, phone, address) VALUES 
('Demo User', 'user@qrgen.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'approved', '+63 987 654 3210', 'Cebu City, Philippines');

-- Insert demo categories
INSERT INTO categories (user_id, name, description, color) VALUES 
(2, 'Electronics', 'Electronic products and gadgets', '#3B82F6'),
(2, 'Clothing', 'Fashion and apparel items', '#10B981'),
(2, 'Food & Beverages', 'Food products and drinks', '#F59E0B'),
(2, 'Home & Garden', 'Home improvement and garden supplies', '#8B5CF6');

-- Insert demo products
INSERT INTO products (user_id, category_id, name, price, description) VALUES 
(2, 1, 'Smartphone Pro Max', 65000.00, 'Latest flagship smartphone with advanced camera system and 5G connectivity'),
(2, 1, 'Gaming Laptop', 85000.00, 'High-performance gaming laptop with RTX graphics and RGB keyboard'),
(2, 1, 'Wireless Earbuds', 8500.00, 'Premium wireless earbuds with noise cancellation'),
(2, 2, 'Premium T-Shirt', 1200.00, 'Comfortable cotton t-shirt with modern design'),
(2, 2, 'Denim Jeans', 2500.00, 'Classic fit denim jeans with premium quality'),
(2, 3, 'Artisan Coffee Beans', 450.00, 'Premium single-origin coffee beans, freshly roasted'),
(2, 3, 'Organic Honey', 350.00, 'Pure organic honey from local beekeepers'),
(2, 4, 'Smart Plant Pot', 1800.00, 'Self-watering smart plant pot with mobile app control');

-- Insert system settings
INSERT INTO settings (setting_key, setting_value) VALUES 
('app_name', 'QR Generator Pro'),
('app_description', 'Professional QR Code Solutions for Modern Businesses'),
('contact_email', 'support@qrgen.com'),
('max_products_per_user', '100');
