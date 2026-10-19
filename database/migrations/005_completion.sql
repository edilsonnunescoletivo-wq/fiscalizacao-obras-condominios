SET NAMES utf8mb4;

CREATE TABLE work_completion_terms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id BIGINT UNSIGNED NOT NULL,
  inspector_user_id BIGINT UNSIGNED NOT NULL,
  result ENUM('APPROVED','CORRECTION_REQUIRED') NOT NULL,
  notes TEXT NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_completion_work(work_id),
  CONSTRAINT fk_completion_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_completion_inspector FOREIGN KEY(inspector_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
