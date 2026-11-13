SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER active,
  ADD INDEX idx_users_super_admin (is_super_admin, active);

CREATE TABLE password_setup_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_setup_user (user_id, used_at, expires_at),
  CONSTRAINT fk_password_setup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
