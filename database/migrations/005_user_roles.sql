ALTER TABLE users
  ADD COLUMN role ENUM('admin','vendedor','manutencao') NOT NULL DEFAULT 'vendedor' AFTER code_prefix;

UPDATE users SET role = 'admin' WHERE username = 'admin';
UPDATE users SET role = 'vendedor' WHERE username <> 'admin' AND role = 'vendedor';

ALTER TABLE users
  ADD KEY idx_users_role_active (role, active);
