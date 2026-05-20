-- ============================================================
-- ERP SYSTEM DATABASE SCHEMA
-- MySQL 8.x / MariaDB 10.4+
-- ============================================================

-- ============================================================
-- USERS & AUTH
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  role ENUM('admin','manager','staff') DEFAULT 'staff',
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) UNIQUE,
  key_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  type ENUM('product','service'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50),
  short_name VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80),
  rate DECIMAL(5,2),
  is_default TINYINT DEFAULT 0,
  status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CUSTOMERS & VENDORS
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  phone VARCHAR(20),
  email VARCHAR(100),
  address TEXT,
  opening_balance DECIMAL(15,2) DEFAULT 0,
  balance_type ENUM('debit','credit') DEFAULT 'debit',
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  phone VARCHAR(20),
  email VARCHAR(100),
  address TEXT,
  opening_balance DECIMAL(15,2) DEFAULT 0,
  balance_type ENUM('debit','credit') DEFAULT 'debit',
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE,
  name VARCHAR(200),
  category_id INT,
  unit_id INT,
  purchase_price DECIMAL(15,2) DEFAULT 0,
  sale_price DECIMAL(15,2) DEFAULT 0,
  tax_rate_id INT NULL,
  product_type ENUM('single','pair') DEFAULT 'single',
  min_stock INT DEFAULT 0,
  current_stock DECIMAL(15,3) DEFAULT 0,
  description TEXT,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (unit_id) REFERENCES units(id),
  FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_pairs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pair_product_id INT NOT NULL,
  component_a_id INT NOT NULL,
  component_b_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pair_product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (component_a_id) REFERENCES products(id),
  FOREIGN KEY (component_b_id) REFERENCES products(id),
  UNIQUE KEY unique_pair (component_a_id, component_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS price_lists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  sale_price DECIMAL(15,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY unique_price (customer_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK IN (PURCHASE / DELIVERY RECEIPT)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_in (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(50) UNIQUE,
  vendor_id INT,
  date DATE,
  subtotal DECIMAL(15,2) NULL,
  discount DECIMAL(15,2) DEFAULT 0,
  discount_type ENUM('percent','flat') DEFAULT 'percent',
  tax DECIMAL(15,2) NULL,
  total DECIMAL(15,2) NULL,
  paid DECIMAL(15,2) DEFAULT 0,
  due DECIMAL(15,2) DEFAULT 0,
  notes TEXT,
  is_locked TINYINT DEFAULT 0,
  status ENUM('draft','confirmed','cancelled') DEFAULT 'confirmed',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_in_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_in_id INT,
  product_id INT,
  item_type ENUM('single','pair') DEFAULT 'single',
  quantity DECIMAL(15,3),
  unit_price DECIMAL(15,2) NULL,
  tax_rate_id INT NULL,
  total DECIMAL(15,2) NULL,
  FOREIGN KEY (stock_in_id) REFERENCES stock_in(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK IN RETURNS
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_in_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_no VARCHAR(50) UNIQUE,
  stock_in_id INT NOT NULL,
  vendor_id INT,
  date DATE,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (stock_in_id) REFERENCES stock_in(id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_in_return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT,
  product_id INT,
  item_type ENUM('single','pair') DEFAULT 'single',
  quantity DECIMAL(15,3),
  FOREIGN KEY (return_id) REFERENCES stock_in_returns(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK OUT (SALE / DELIVERY)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_out (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(50) UNIQUE,
  customer_id INT,
  date DATE,
  subtotal DECIMAL(15,2) NULL,
  discount DECIMAL(15,2) DEFAULT 0,
  discount_type ENUM('percent','flat') DEFAULT 'percent',
  tax DECIMAL(15,2) NULL,
  total DECIMAL(15,2) NULL,
  paid DECIMAL(15,2) DEFAULT 0,
  due DECIMAL(15,2) DEFAULT 0,
  type ENUM('sale','delivery') DEFAULT 'sale',
  notes TEXT,
  is_locked TINYINT DEFAULT 0,
  status ENUM('draft','confirmed','delivered','cancelled') DEFAULT 'confirmed',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_out_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_out_id INT,
  product_id INT,
  item_type ENUM('single','pair') DEFAULT 'single',
  quantity DECIMAL(15,3),
  unit_price DECIMAL(15,2) NULL,
  tax_rate_id INT NULL,
  total DECIMAL(15,2) NULL,
  FOREIGN KEY (stock_out_id) REFERENCES stock_out(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK OUT RETURNS
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_out_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_no VARCHAR(50) UNIQUE,
  stock_out_id INT NOT NULL,
  customer_id INT,
  date DATE,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (stock_out_id) REFERENCES stock_out(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_out_return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT,
  product_id INT,
  item_type ENUM('single','pair') DEFAULT 'single',
  quantity DECIMAL(15,3),
  FOREIGN KEY (return_id) REFERENCES stock_out_returns(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK OPENING BALANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_opening (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  quantity DECIMAL(15,3),
  purchase_price DECIMAL(15,2) DEFAULT 0,
  date DATE,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STOCK MOVEMENTS (AUDIT TRAIL)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  item_type ENUM('single','pair') DEFAULT 'single',
  type ENUM('in','out','adjustment','pair_created','return_in','return_out','opening'),
  quantity DECIMAL(15,3),
  reference_type VARCHAR(50),
  reference_id INT,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- QUOTATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS quotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_no VARCHAR(50) UNIQUE,
  customer_id INT,
  date DATE,
  valid_until DATE,
  subtotal DECIMAL(15,2),
  discount DECIMAL(15,2) DEFAULT 0,
  discount_type ENUM('percent','flat') DEFAULT 'percent',
  tax DECIMAL(15,2) DEFAULT 0,
  total DECIMAL(15,2),
  notes TEXT,
  terms TEXT,
  status ENUM('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  converted_to_sale INT NULL,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT,
  product_id INT,
  item_type ENUM('single','pair') DEFAULT 'single',
  quantity DECIMAL(15,3),
  unit_price DECIMAL(15,2) NOT NULL,
  tax_rate_id INT NULL,
  total DECIMAL(15,2),
  FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WORK ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS work_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wo_no VARCHAR(50) UNIQUE,
  customer_id INT,
  title VARCHAR(255),
  description TEXT,
  start_date DATE,
  due_date DATE,
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  status ENUM('pending','in_progress','on_hold','completed','cancelled') DEFAULT 'pending',
  assigned_to INT,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_order_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT,
  task_name VARCHAR(255),
  assigned_to INT,
  due_date DATE,
  status ENUM('pending','in_progress','done') DEFAULT 'pending',
  notes TEXT,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_order_timeline (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT,
  action VARCHAR(255),
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BILLING (TAX INVOICE)
-- ============================================================
CREATE TABLE IF NOT EXISTS bills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_no VARCHAR(50) UNIQUE,
  customer_id INT,
  stock_out_id INT NULL,
  date DATE,
  due_date DATE,
  subtotal DECIMAL(15,2),
  discount DECIMAL(15,2) DEFAULT 0,
  discount_type ENUM('percent','flat') DEFAULT 'percent',
  tax DECIMAL(15,2) DEFAULT 0,
  total DECIMAL(15,2),
  paid DECIMAL(15,2) DEFAULT 0,
  due DECIMAL(15,2) DEFAULT 0,
  notes TEXT,
  is_locked TINYINT DEFAULT 0,
  repeat_interval ENUM('none','weekly','monthly','quarterly') DEFAULT 'none',
  repeat_next_date DATE NULL,
  status ENUM('unpaid','partial','paid','overdue','cancelled') DEFAULT 'unpaid',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bill_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_id INT,
  product_id INT NULL,
  item_type ENUM('single','pair') DEFAULT 'single',
  description VARCHAR(255),
  quantity DECIMAL(15,3),
  unit_price DECIMAL(15,2) NOT NULL,
  tax_rate_id INT NULL,
  total DECIMAL(15,2),
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENTS (DUE COLLECTION)
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_no VARCHAR(50) UNIQUE,
  type ENUM('received','paid') DEFAULT 'received',
  party_type ENUM('customer','vendor'),
  party_id INT,
  reference_type ENUM('stock_in','stock_out','bill','manual'),
  reference_id INT NULL,
  amount DECIMAL(15,2),
  method ENUM('cash','bank','mobile','check','other') DEFAULT 'cash',
  date DATE,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LEDGER (AUTO-GENERATED ENTRIES)
-- ============================================================
CREATE TABLE IF NOT EXISTS ledger_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  party_type ENUM('customer','vendor'),
  party_id INT,
  date DATE,
  description VARCHAR(255),
  debit DECIMAL(15,2) DEFAULT 0,
  credit DECIMAL(15,2) DEFAULT 0,
  reference_type VARCHAR(50),
  reference_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GENERIC ATTACHMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference_type VARCHAR(50),
  reference_id INT,
  filename VARCHAR(255),
  original_name VARCHAR(255),
  mime_type VARCHAR(100),
  file_size INT,
  uploaded_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS (IN-APP)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  type ENUM('low_stock','overdue_bill','payment_received','system'),
  title VARCHAR(200),
  message TEXT,
  reference_type VARCHAR(50) NULL,
  reference_id INT NULL,
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action ENUM('create','update','delete','login','logout'),
  table_name VARCHAR(80),
  record_id INT NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
