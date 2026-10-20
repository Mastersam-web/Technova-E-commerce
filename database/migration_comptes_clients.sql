-- À importer UNIQUEMENT si vous aviez déjà installé la première version (sans comptes clients).
SET NAMES utf8mb4;

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

ALTER TABLE orders ADD COLUMN customer_id INT UNSIGNED NULL AFTER reference;
ALTER TABLE orders ADD INDEX idx_customer (customer_id);
ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
