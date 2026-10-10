SET NAMES utf8mb4;

CREATE TABLE inspection_acceptances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inspection_id BIGINT UNSIGNED NOT NULL,
  work_id BIGINT UNSIGNED NOT NULL,
  signer_user_id BIGINT UNSIGNED NOT NULL,
  signer_type ENUM('INSPECTOR','WORK_RESPONSIBLE','MANAGEMENT') NOT NULL,
  account_name_snapshot VARCHAR(150) NOT NULL,
  email_snapshot VARCHAR(190) NULL,
  typed_name VARCHAR(150) NOT NULL,
  declaration VARCHAR(500) NOT NULL,
  signature_method ENUM('SHA256','HMAC_SHA256') NOT NULL DEFAULT 'SHA256',
  signature_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent_hash CHAR(64) NULL,
  signed_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inspection_acceptance_user_type(inspection_id, signer_user_id, signer_type),
  INDEX idx_inspection_acceptance_inspection(inspection_id, signed_at),
  INDEX idx_inspection_acceptance_work(work_id, signed_at),
  CONSTRAINT fk_ia_inspection FOREIGN KEY(inspection_id) REFERENCES inspections(id),
  CONSTRAINT fk_ia_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_ia_signer FOREIGN KEY(signer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
