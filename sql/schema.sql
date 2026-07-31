-- Seedling Nursery Management System — schema v1.0
-- Re-runnable: drops and recreates every table.
-- MySQL 8 / MariaDB, utf8mb4.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS preorders;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS labour;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS batch_events;
DROP TABLE IF EXISTS batches;
DROP TABLE IF EXISTS species;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,
  role          ENUM('owner','worker') NOT NULL DEFAULT 'worker',
  password_hash VARCHAR(255) NOT NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE species (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  category           ENUM('fruit','indigenous','ornamental','vegetable','other') NOT NULL DEFAULT 'other',
  default_sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  lead_time_days     INT UNSIGNED NULL,          -- typical sowing → ready duration
  code_prefix        VARCHAR(5) NOT NULL,        -- used in batch codes, e.g. AVO
  active             TINYINT(1) NOT NULL DEFAULT 1,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_species_name (name),
  CONSTRAINT fk_species_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE batches (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  batch_code          VARCHAR(30) NOT NULL,
  species_id          INT UNSIGNED NOT NULL,
  sown_date           DATE NOT NULL,
  initial_qty         INT UNSIGNED NOT NULL,
  current_qty         INT UNSIGNED NOT NULL,
  stage               ENUM('sown','germinating','potted','hardening','ready','sold_out','written_off') NOT NULL DEFAULT 'sown',
  expected_ready_date DATE NULL,
  sale_price_override DECIMAL(12,2) NULL,
  notes               TEXT NULL,
  deleted_at          DATETIME NULL,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_batches_code (batch_code),
  KEY ix_batches_species (species_id),
  KEY ix_batches_stage (stage),
  CONSTRAINT fk_batches_species FOREIGN KEY (species_id) REFERENCES species(id),
  CONSTRAINT fk_batches_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE batch_events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  batch_id    INT UNSIGNED NOT NULL,
  event_type  ENUM('watering','potting','loss','stage_change','input_usage','note') NOT NULL,
  qty         INT UNSIGNED NULL,                 -- for loss / potting
  loss_reason ENUM('dried','disease','pests','damage','other') NULL,
  new_stage   VARCHAR(20) NULL,                  -- for stage_change
  event_date  DATE NOT NULL,
  notes       TEXT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_events_batch (batch_id),
  KEY ix_events_date (event_date),
  CONSTRAINT fk_events_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category     ENUM('seed','media_soil','bags','fertilizer','chemical','water','transport','equipment','other') NOT NULL,
  description  VARCHAR(255) NOT NULL,
  supplier     VARCHAR(100) NULL,
  amount       DECIMAL(12,2) NOT NULL,
  expense_date DATE NOT NULL,
  batch_id     INT UNSIGNED NULL,                -- NULL = general overhead
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_expenses_batch (batch_id),
  KEY ix_expenses_date (expense_date),
  CONSTRAINT fk_expenses_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_expenses_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE labour (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  worker_name VARCHAR(100) NOT NULL,             -- casuals are not system users
  task        VARCHAR(255) NOT NULL,
  amount      DECIMAL(12,2) NOT NULL,
  labour_date DATE NOT NULL,
  batch_id    INT UNSIGNED NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_labour_batch (batch_id),
  KEY ix_labour_date (labour_date),
  CONSTRAINT fk_labour_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_labour_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  phone      VARCHAR(20) NULL,
  notes      TEXT NULL,
  deleted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_customers_phone (phone),
  CONSTRAINT fk_customers_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NULL,              -- NULL = walk-in
  sale_date      DATE NOT NULL,
  total_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_paid    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('mpesa','cash','credit','mixed') NOT NULL DEFAULT 'cash',
  mpesa_ref      VARCHAR(30) NULL,
  status         ENUM('paid','partial','credit') NOT NULL DEFAULT 'paid',
  notes          TEXT NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_sales_customer (customer_id),
  KEY ix_sales_date (sale_date),
  KEY ix_sales_status (status),
  CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_sales_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id    INT UNSIGNED NOT NULL,
  batch_id   INT UNSIGNED NOT NULL,
  qty        INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_items_sale (sale_id),
  KEY ix_items_batch (batch_id),
  CONSTRAINT fk_items_sale  FOREIGN KEY (sale_id)  REFERENCES sales(id),
  CONSTRAINT fk_items_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_items_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id        INT UNSIGNED NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  payment_method ENUM('mpesa','cash') NOT NULL DEFAULT 'cash',
  mpesa_ref      VARCHAR(30) NULL,
  paid_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_payments_sale (sale_id),
  CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
  CONSTRAINT fk_payments_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE preorders (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id       INT UNSIGNED NOT NULL,
  species_id        INT UNSIGNED NOT NULL,
  qty               INT UNSIGNED NOT NULL,
  agreed_unit_price DECIMAL(12,2) NOT NULL,
  deposit_paid      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  expected_date     DATE NULL,
  status            ENUM('open','fulfilled','cancelled') NOT NULL DEFAULT 'open',
  fulfilled_sale_id INT UNSIGNED NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_preorders_status (status),
  CONSTRAINT fk_preorders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_preorders_species  FOREIGN KEY (species_id)  REFERENCES species(id),
  CONSTRAINT fk_preorders_sale     FOREIGN KEY (fulfilled_sale_id) REFERENCES sales(id),
  CONSTRAINT fk_preorders_creator  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key-value app settings (nursery name, etc.)
CREATE TABLE settings (
  k VARCHAR(50) NOT NULL PRIMARY KEY,
  v TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login rate limiting (§6.1): 5 attempts per 15 min per phone
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone        VARCHAR(20) NOT NULL,
  ip           VARCHAR(45) NULL,
  success      TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_attempts_phone_time (phone, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every mutation: who, what, when, before/after (§7)
CREATE TABLE activity_log (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(30) NOT NULL,              -- create / update / delete / login …
  entity      VARCHAR(30) NOT NULL,              -- table or concept name
  entity_id   INT UNSIGNED NULL,
  before_json TEXT NULL,
  after_json  TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_activity_time (created_at),
  KEY ix_activity_entity (entity, entity_id),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session storage. Serverless instances share no filesystem and are recycled
-- when idle, so PHP's default file sessions log users out unpredictably.
-- Harmless on Apache/Hostinger, which uses the same handler.
CREATE TABLE sessions (
  id            VARCHAR(128) NOT NULL PRIMARY KEY,
  data          MEDIUMBLOB   NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  KEY ix_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (k, v) VALUES ('nursery_name', 'My Nursery');
