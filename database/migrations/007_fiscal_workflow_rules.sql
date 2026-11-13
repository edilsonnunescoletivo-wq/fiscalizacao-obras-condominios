SET NAMES utf8mb4;

CREATE TABLE severity_action_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condominium_id BIGINT UNSIGNED NOT NULL,
  severity ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL,
  suggested_action ENUM('NONE','WARNING','ADJUSTMENT','SUSPENSION','EMBARGO') NOT NULL DEFAULT 'NONE',
  auto_fill_notification TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_severity_rule(condominium_id,severity),
  CONSTRAINT fk_sar_condo FOREIGN KEY(condominium_id) REFERENCES condominiums(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE non_conformities
  ADD COLUMN correction_notes TEXT NULL AFTER description,
  ADD COLUMN correction_submitted_by BIGINT UNSIGNED NULL AFTER created_by,
  ADD COLUMN correction_submitted_at DATETIME NULL AFTER resolved_at,
  ADD CONSTRAINT fk_nc_correction_user FOREIGN KEY(correction_submitted_by) REFERENCES users(id);

CREATE TABLE non_conformity_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  non_conformity_id BIGINT UNSIGNED NOT NULL,
  work_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  caption VARCHAR(255) NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nce_nc(non_conformity_id),
  CONSTRAINT fk_nce_nc FOREIGN KEY(non_conformity_id) REFERENCES non_conformities(id),
  CONSTRAINT fk_nce_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_nce_user FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
