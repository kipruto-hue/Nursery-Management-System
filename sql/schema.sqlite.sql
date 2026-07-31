-- SQLite mirror of schema.sql, used ONLY by the no-database demo mode.
-- MySQL remains the real schema; see sql/schema.sql. Differences are forced by
-- SQLite: INTEGER PRIMARY KEY AUTOINCREMENT instead of AUTO_INCREMENT, TEXT
-- instead of ENUM, no ENGINE/CHARSET clauses, no ON UPDATE CURRENT_TIMESTAMP,
-- and indexes declared separately rather than inline.

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

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  phone         TEXT NOT NULL UNIQUE,
  role          TEXT NOT NULL DEFAULT 'worker',
  password_hash TEXT NOT NULL,
  active        INTEGER NOT NULL DEFAULT 1,
  must_change_password INTEGER NOT NULL DEFAULT 0,
  last_login_at TEXT NULL,
  created_by    INTEGER NULL,
  created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE species (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  name               TEXT NOT NULL UNIQUE,
  category           TEXT NOT NULL DEFAULT 'other',
  default_sale_price NUMERIC NOT NULL DEFAULT 0,
  lead_time_days     INTEGER NULL,
  code_prefix        TEXT NOT NULL,
  active             INTEGER NOT NULL DEFAULT 1,
  created_by         INTEGER NULL,
  created_at         TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE batches (
  id                  INTEGER PRIMARY KEY AUTOINCREMENT,
  batch_code          TEXT NOT NULL UNIQUE,
  species_id          INTEGER NOT NULL,
  sown_date           TEXT NOT NULL,
  initial_qty         INTEGER NOT NULL,
  current_qty         INTEGER NOT NULL,
  stage               TEXT NOT NULL DEFAULT 'sown',
  expected_ready_date TEXT NULL,
  sale_price_override NUMERIC NULL,
  notes               TEXT NULL,
  deleted_at          TEXT NULL,
  created_by          INTEGER NULL,
  created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_batches_species ON batches (species_id);
CREATE INDEX ix_batches_stage ON batches (stage);

CREATE TABLE batch_events (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  batch_id    INTEGER NOT NULL,
  event_type  TEXT NOT NULL,
  qty         INTEGER NULL,
  loss_reason TEXT NULL,
  new_stage   TEXT NULL,
  event_date  TEXT NOT NULL,
  notes       TEXT NULL,
  created_by  INTEGER NULL,
  created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_events_batch ON batch_events (batch_id);
CREATE INDEX ix_events_date ON batch_events (event_date);

CREATE TABLE expenses (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  category     TEXT NOT NULL,
  description  TEXT NOT NULL,
  supplier     TEXT NULL,
  amount       NUMERIC NOT NULL,
  expense_date TEXT NOT NULL,
  batch_id     INTEGER NULL,
  created_by   INTEGER NULL,
  created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_expenses_batch ON expenses (batch_id);
CREATE INDEX ix_expenses_date ON expenses (expense_date);

CREATE TABLE labour (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  worker_name TEXT NOT NULL,
  task        TEXT NOT NULL,
  amount      NUMERIC NOT NULL,
  labour_date TEXT NOT NULL,
  batch_id    INTEGER NULL,
  created_by  INTEGER NULL,
  created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_labour_batch ON labour (batch_id);
CREATE INDEX ix_labour_date ON labour (labour_date);

CREATE TABLE customers (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL,
  phone      TEXT NULL,
  notes      TEXT NULL,
  deleted_at TEXT NULL,
  created_by INTEGER NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_customers_phone ON customers (phone);

CREATE TABLE sales (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id    INTEGER NULL,
  sale_date      TEXT NOT NULL,
  total_amount   NUMERIC NOT NULL DEFAULT 0,
  amount_paid    NUMERIC NOT NULL DEFAULT 0,
  payment_method TEXT NOT NULL DEFAULT 'cash',
  mpesa_ref      TEXT NULL,
  status         TEXT NOT NULL DEFAULT 'paid',
  notes          TEXT NULL,
  created_by     INTEGER NULL,
  created_at     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_sales_customer ON sales (customer_id);
CREATE INDEX ix_sales_date ON sales (sale_date);
CREATE INDEX ix_sales_status ON sales (status);

CREATE TABLE sale_items (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id    INTEGER NOT NULL,
  batch_id   INTEGER NOT NULL,
  qty        INTEGER NOT NULL,
  unit_price NUMERIC NOT NULL,
  line_total NUMERIC NOT NULL,
  created_by INTEGER NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_items_sale ON sale_items (sale_id);
CREATE INDEX ix_items_batch ON sale_items (batch_id);

CREATE TABLE payments (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id        INTEGER NOT NULL,
  amount         NUMERIC NOT NULL,
  payment_method TEXT NOT NULL DEFAULT 'cash',
  mpesa_ref      TEXT NULL,
  paid_at        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     INTEGER NULL,
  created_at     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_payments_sale ON payments (sale_id);

CREATE TABLE preorders (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id       INTEGER NOT NULL,
  species_id        INTEGER NOT NULL,
  qty               INTEGER NOT NULL,
  agreed_unit_price NUMERIC NOT NULL,
  deposit_paid      NUMERIC NOT NULL DEFAULT 0,
  expected_date     TEXT NULL,
  status            TEXT NOT NULL DEFAULT 'open',
  fulfilled_sale_id INTEGER NULL,
  created_by        INTEGER NULL,
  created_at        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_preorders_status ON preorders (status);

CREATE TABLE settings (
  k TEXT NOT NULL PRIMARY KEY,
  v TEXT NULL,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  phone        TEXT NOT NULL,
  ip           TEXT NULL,
  success      INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_attempts_phone_time ON login_attempts (phone, attempted_at);

CREATE TABLE activity_log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NULL,
  action      TEXT NOT NULL,
  entity      TEXT NOT NULL,
  entity_id   INTEGER NULL,
  before_json TEXT NULL,
  after_json  TEXT NULL,
  created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_activity_time ON activity_log (created_at);
CREATE INDEX ix_activity_entity ON activity_log (entity, entity_id);

CREATE TABLE sessions (
  id            TEXT NOT NULL PRIMARY KEY,
  data          BLOB NOT NULL,
  last_activity INTEGER NOT NULL
);
CREATE INDEX ix_sessions_activity ON sessions (last_activity);

INSERT INTO settings (k, v) VALUES ('nursery_name', 'My Nursery');
