SET NAMES utf8mb4;

CREATE TABLE inspection_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inspection_id BIGINT UNSIGNED NOT NULL,
  work_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_insp_photo_work(work_id),
  CONSTRAINT fk_ip_inspection FOREIGN KEY(inspection_id) REFERENCES inspections(id),
  CONSTRAINT fk_ip_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_ip_user FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_access_invites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_invite_work_email(work_id,email),
  CONSTRAINT fk_invite_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_invite_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
