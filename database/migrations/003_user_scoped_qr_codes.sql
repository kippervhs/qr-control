ALTER TABLE users
  ADD COLUMN code_prefix CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER username,
  ADD UNIQUE KEY uq_users_code_prefix (code_prefix);

UPDATE users
SET code_prefix = UPPER(SUBSTRING(REGEXP_REPLACE(username, '[^A-Za-z0-9]', ''), 1, 3))
WHERE code_prefix IS NULL;

ALTER TABLE batches
  ADD COLUMN user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER id;

UPDATE batches
SET user_id = (SELECT id FROM users WHERE username = 'admin' LIMIT 1)
WHERE user_id IS NULL;

ALTER TABLE batches
  DROP CHECK chk_batch_range,
  DROP INDEX uq_batches_start_code,
  DROP INDEX uq_batches_end_code,
  MODIFY start_code VARCHAR(20) NOT NULL,
  MODIFY end_code VARCHAR(20) NOT NULL,
  MODIFY user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ADD UNIQUE KEY uq_batches_start_code (start_code),
  ADD UNIQUE KEY uq_batches_end_code (end_code),
  ADD KEY idx_batches_user_created (user_id, created_at DESC, id),
  ADD CONSTRAINT fk_batches_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE qr_codes
  ADD COLUMN user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER id;UPDATE qr_codes q
JOIN batches b ON b.id = q.batch_id
SET q.user_id = b.user_id
WHERE q.user_id IS NULL;

ALTER TABLE qr_codes
  DROP CHECK chk_qr_code_positive,
  DROP INDEX uq_qr_codes_code,
  DROP INDEX idx_qr_codes_batch_code,
  DROP INDEX idx_qr_codes_created,
  MODIFY code VARCHAR(20) NOT NULL,
  MODIFY user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ADD UNIQUE KEY uq_qr_codes_code (code),
  ADD KEY idx_qr_codes_user_code (user_id, code),
  ADD KEY idx_qr_codes_batch_code (batch_id, code),
  ADD KEY idx_qr_codes_created (created_at DESC),
  ADD CONSTRAINT fk_qr_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

CREATE TABLE qr_code_sequences (
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  next_number BIGINT UNSIGNED NOT NULL,
  CONSTRAINT chk_qr_sequence_range CHECK (next_number BETWEEN 1 AND 1679616),
  CONSTRAINT fk_qr_sequence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO qr_code_sequences (user_id, next_number)
SELECT u.id,
       COALESCE(
         MAX(CASE WHEN q.code REGEXP '^[0-9]+$' THEN CAST(q.code AS UNSIGNED) END) + 1,
         1
       )
FROM users u
LEFT JOIN qr_codes q ON q.user_id = u.id
GROUP BY u.id
ON DUPLICATE KEY UPDATE next_number = VALUES(next_number);

DROP TABLE qr_code_sequence;

ALTER TABLE users
  MODIFY code_prefix CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;