CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(100) PRIMARY KEY,
  applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_code_sequence (
  id TINYINT UNSIGNED PRIMARY KEY,
  next_code BIGINT UNSIGNED NOT NULL,
  CONSTRAINT chk_single_sequence CHECK (id = 1),
  CONSTRAINT chk_next_code CHECK (next_code > 0)
) ENGINE=InnoDB;

INSERT IGNORE INTO qr_code_sequence (id, next_code) VALUES (1, 1);

CREATE TABLE IF NOT EXISTS batches (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  start_code BIGINT UNSIGNED NOT NULL,
  end_code BIGINT UNSIGNED NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_batches_start_code (start_code),
  UNIQUE KEY uq_batches_end_code (end_code),
  KEY idx_batches_created (created_at DESC, id),
  CONSTRAINT chk_batch_quantity CHECK (quantity BETWEEN 1 AND 500),
  CONSTRAINT chk_batch_range CHECK (end_code = start_code + quantity - 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_codes (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  code BIGINT UNSIGNED NOT NULL,
  destination_url VARCHAR(2048) NULL,
  batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_qr_codes_code (code),
  KEY idx_qr_codes_batch_code (batch_id, code),
  KEY idx_qr_codes_created (created_at DESC),
  CONSTRAINT fk_qr_codes_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  CONSTRAINT chk_qr_code_positive CHECK (code > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  ip_hash BINARY(32) PRIMARY KEY,
  attempt_count TINYINT UNSIGNED NOT NULL,
  reset_at DATETIME NOT NULL,
  blocked_until DATETIME NULL,
  KEY idx_login_attempts_expiry (reset_at)
) ENGINE=InnoDB;
