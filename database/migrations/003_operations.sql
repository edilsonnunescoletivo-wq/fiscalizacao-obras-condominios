SET NAMES utf8mb4;

CREATE TABLE non_conformities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inspection_id BIGINT UNSIGNED NULL,
  work_id BIGINT UNSIGNED NOT NULL,
  severity ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  corrective_deadline DATETIME NULL,
  status ENUM('OPEN','CORRECTED','CLOSED') NOT NULL DEFAULT 'OPEN',
  created_by BIGINT UNSIGNED NOT NULL,
  resolved_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  INDEX idx_nc_work_status(work_id,status),
  CONSTRAINT fk_nc_inspection FOREIGN KEY(inspection_id) REFERENCES inspections(id),
  CONSTRAINT fk_nc_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_nc_creator FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_nc_resolver FOREIGN KEY(resolved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inspections
  ADD COLUMN checklist_json JSON NULL AFTER notes;
