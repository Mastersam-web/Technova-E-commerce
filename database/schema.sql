-- TechNova - schéma MySQL (MySQL 5.7+ / MariaDB 10.3+)
-- Importez ce fichier dans votre base (phpMyAdmin > Importer) OU utilisez install.php.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  reset_token_hash CHAR(64) NULL,
  reset_expires DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_reset (reset_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  old_price DECIMAL(10,2) NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  reviews INT UNSIGNED NOT NULL DEFAULT 0,
  image VARCHAR(500) NOT NULL DEFAULT '',
  stock INT UNSIGNED NOT NULL DEFAULT 100,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_cat FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_read (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  token CHAR(40) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(20) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(190) NULL,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT '',
  total DECIMAL(12,2) NOT NULL,
  status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status, created_at),
  INDEX idx_customer (customer_id),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'info',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL DEFAULT '',
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif (customer_id, is_read, id),
  CONSTRAINT fk_notif_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(40) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rl (bucket, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données initiales (catégories + produits du site)
INSERT IGNORE INTO categories (name, sort_order) VALUES
 ('Smartphones',1),('Ordinateurs',2),('Audio',3),('Montres connectées',4),('Gaming',5),('Accessoires',6);

INSERT IGNORE INTO products (slug,name,category_id,price,old_price,rating,reviews,image,is_featured) VALUES
('iphone-15-pro','iPhone 15 Pro',(SELECT id FROM categories WHERE name='Smartphones'),999.00,1180.00,5,124,'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=600&q=80',1),
('macbook-air-m2','MacBook Air M2',(SELECT id FROM categories WHERE name='Ordinateurs'),1299.00,1449.00,5,98,'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=600&q=80',1),
('airpods-pro','AirPods Pro',(SELECT id FROM categories WHERE name='Audio'),649.00,810.00,5,76,'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?auto=format&fit=crop&w=600&q=80',1),
('samsung-watch-6','Samsung Galaxy Watch 6',(SELECT id FROM categories WHERE name='Montres connectées'),699.00,790.00,5,63,'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?auto=format&fit=crop&w=600&q=80',1),
('ps5-controller','Manette DualSense PS5',(SELECT id FROM categories WHERE name='Gaming'),350.00,410.00,5,110,'https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=600&q=80',0),
('charger-magsafe','Chargeur Rapide MagSafe',(SELECT id FROM categories WHERE name='Accessoires'),180.00,220.00,4,42,'https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?auto=format&fit=crop&w=600&q=80',0),
('sony-wh1000xm5','Casque Sony WH-1000XM5',(SELECT id FROM categories WHERE name='Audio'),890.00,1030.00,5,89,'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=600&q=80',0),
('samsung-s24-ultra','Samsung Galaxy S24 Ultra',(SELECT id FROM categories WHERE name='Smartphones'),1450.00,1580.00,5,154,'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?auto=format&fit=crop&w=600&q=80',0);
