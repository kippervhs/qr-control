ALTER TABLE users
  ADD COLUMN email VARCHAR(254) NULL AFTER username,
  ADD COLUMN full_name VARCHAR(160) NULL AFTER email,
  ADD UNIQUE KEY uq_users_email (email);

CREATE TABLE subscriptions (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asaas_customer_id VARCHAR(80) NULL,
  asaas_subscription_id VARCHAR(80) NULL,
  asaas_checkout_id VARCHAR(80) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
  current_period_end DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_subscriptions_user (user_id),
  UNIQUE KEY uq_subscriptions_customer (asaas_customer_id),
  UNIQUE KEY uq_subscriptions_subscription (asaas_subscription_id),
  KEY idx_subscriptions_status_period (status, current_period_end),
  CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asaas_webhook_events (
  event_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  event_type VARCHAR(100) NOT NULL,
  received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  processed_at DATETIME(6) NULL,
  KEY idx_asaas_webhook_events_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
