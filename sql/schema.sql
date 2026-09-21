-- Billing & PRA Digital Invoicing System
-- MySQL 5.7+ / MariaDB 10.3+

CREATE DATABASE IF NOT EXISTS billing_pra
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE billing_pra;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS pra_invoice_log;
DROP TABLE IF EXISTS invoice_d;
DROP TABLE IF EXISTS invoice_m;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS pra_config;
DROP TABLE IF EXISTS company_settings;
DROP TABLE IF EXISTS doc_sequences;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(80) NOT NULL DEFAULT '',
  ntn VARCHAR(30) NOT NULL DEFAULT '',
  strn VARCHAR(30) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(120) NOT NULL DEFAULT '',
  logo_path VARCHAR(255) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pra_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  api_base_url VARCHAR(255) NOT NULL DEFAULT '',
  client_id VARCHAR(120) NOT NULL DEFAULT '',
  client_secret VARCHAR(255) NOT NULL DEFAULT '',
  sandbox_mode TINYINT(1) NOT NULL DEFAULT 1,
  seller_ntn VARCHAR(30) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  ntn_cnic VARCHAR(30) NOT NULL DEFAULT '',
  customer_type ENUM('Registered','Unregistered','Individual') NOT NULL DEFAULT 'Unregistered',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_customers_name (name),
  KEY idx_customers_ntn (ntn_cnic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  default_rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  default_tax_rate DECIMAL(6,2) NOT NULL DEFAULT 16.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_items_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE doc_sequences (
  name VARCHAR(50) NOT NULL,
  next_val INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_m (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no VARCHAR(30) NOT NULL,
  doc_date DATE NOT NULL,
  customer_id INT UNSIGNED DEFAULT NULL,
  customer_name VARCHAR(160) NOT NULL,
  ntn_cnic VARCHAR(30) NOT NULL DEFAULT '',
  customer_type VARCHAR(30) NOT NULL DEFAULT 'Unregistered',
  payment_mode ENUM('Cash','Bank','Card') NOT NULL DEFAULT 'Cash',
  sub_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status ENUM('PREPARED','SUBMITTED','PRA_APPROVED','PRA_REJECTED','PENDING','CANCELED') NOT NULL DEFAULT 'PREPARED',
  pra_status VARCHAR(40) NOT NULL DEFAULT '',
  pra_invoice_no VARCHAR(60) NOT NULL DEFAULT '',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_invoice_m_doc_no (doc_no),
  KEY idx_invoice_m_date (doc_date),
  KEY idx_invoice_m_status (status),
  KEY idx_invoice_m_customer (customer_id),
  CONSTRAINT fk_invoice_m_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoice_m_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_d (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  m_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED DEFAULT NULL,
  item_name VARCHAR(160) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  qty DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_invoice_d_m (m_id),
  CONSTRAINT fk_invoice_d_m FOREIGN KEY (m_id) REFERENCES invoice_m (id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_d_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pra_invoice_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED NOT NULL,
  request_data LONGTEXT NOT NULL,
  response_data LONGTEXT NOT NULL,
  pra_status VARCHAR(40) NOT NULL DEFAULT '',
  pra_invoice_no VARCHAR(60) NOT NULL DEFAULT '',
  error_message VARCHAR(500) NOT NULL DEFAULT '',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pra_log_invoice (invoice_id),
  KEY idx_pra_log_status (pra_status),
  CONSTRAINT fk_pra_log_invoice FOREIGN KEY (invoice_id) REFERENCES invoice_m (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: default login admin / admin123
INSERT INTO users (username, password_hash, full_name, is_active) VALUES
('admin', '$2y$10$yeXcADyrBIyXHwMk1Rp65ecRW9XGOeks7Dw8yBn3X9fNGIw/XChR6', 'System Administrator', 1);

INSERT INTO company_settings (name, address, city, ntn, strn, phone, email) VALUES
('Zubair Enterprises', 'Office 12, Commercial Plaza, MM Alam Road', 'Lahore', '1234567-8', '03-00-1234-567-89', '+92 42 3578 0000', 'billing@zubairenterprises.pk');

INSERT INTO pra_config (api_base_url, client_id, client_secret, sandbox_mode, seller_ntn) VALUES
('https://sandbox.pra.punjab.gov.pk/api', 'SANDBOX_CLIENT', 'change-me-in-production', 1, '1234567-8');

INSERT INTO doc_sequences (name, next_val) VALUES ('invoice', 1);

INSERT INTO customers (name, ntn_cnic, customer_type, phone, address) VALUES
('Lahore Traders', '4234567-1', 'Registered', '+92 42 111 222 333', 'Hall Road, Lahore'),
('Ahmad Khan', '35202-1234567-1', 'Individual', '+92 300 1234567', 'Model Town, Lahore'),
('Multan Supplies Co.', '', 'Unregistered', '+92 61 111 444 555', 'Bosan Road, Multan');

INSERT INTO items (code, name, default_rate, default_tax_rate) VALUES
('SVC-001', 'Professional Consulting', 25000.00, 16.00),
('SVC-002', 'Software Development', 80000.00, 16.00),
('SVC-003', 'Annual Maintenance', 15000.00, 16.00),
('SVC-004', 'Training Session', 12000.00, 16.00),
('SVC-005', 'Support Retainer', 18000.00, 16.00);
