-- À importer UNIQUEMENT si vous aviez déjà installé la version avec comptes clients (avant l'espace client complet).
SET NAMES utf8mb4;

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
