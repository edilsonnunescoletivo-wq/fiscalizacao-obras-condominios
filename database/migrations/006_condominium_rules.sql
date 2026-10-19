SET NAMES utf8mb4;

CREATE TABLE condominium_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condominium_id BIGINT UNSIGNED NOT NULL UNIQUE,
  default_notification_days INT UNSIGNED NOT NULL DEFAULT 5,
  warning_days INT UNSIGNED NOT NULL DEFAULT 3,
  adjustment_days INT UNSIGNED NOT NULL DEFAULT 5,
  suspension_days INT UNSIGNED NOT NULL DEFAULT 0,
  embargo_days INT UNSIGNED NOT NULL DEFAULT 0,
  require_photo_on_inspection TINYINT(1) NOT NULL DEFAULT 0,
  require_photo_on_non_conformity TINYINT(1) NOT NULL DEFAULT 0,
  require_final_inspection TINYINT(1) NOT NULL DEFAULT 1,
  block_completion_with_open_nc TINYINT(1) NOT NULL DEFAULT 1,
  allow_inspector_warning TINYINT(1) NOT NULL DEFAULT 1,
  allow_inspector_adjustment TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rules_condo FOREIGN KEY(condominium_id) REFERENCES condominiums(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condominium_id BIGINT UNSIGNED NOT NULL,
  type ENUM('IRREGULARITY','WARNING','ADJUSTMENT','SUSPENSION','EMBARGO','RELEASE') NOT NULL,
  title VARCHAR(180) NOT NULL,
  default_reason VARCHAR(255) NULL,
  default_body TEXT NULL,
  default_deadline_days INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_template(condominium_id,type),
  CONSTRAINT fk_nt_condo FOREIGN KEY(condominium_id) REFERENCES condominiums(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspection_checklist_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condominium_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(180) NOT NULL,
  category VARCHAR(100) NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_checklist_condo(condominium_id,active,sort_order),
  CONSTRAINT fk_ic_condo FOREIGN KEY(condominium_id) REFERENCES condominiums(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
