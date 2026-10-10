SET NAMES utf8mb4;

ALTER TABLE non_conformities
  ADD COLUMN priority ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL' AFTER severity,
  ADD COLUMN assigned_user_id BIGINT UNSIGNED NULL AFTER priority,
  ADD INDEX idx_nc_assignee_status(assigned_user_id,status),
  ADD CONSTRAINT fk_nc_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id);

UPDATE non_conformities
SET priority = CASE severity
  WHEN 'CRITICAL' THEN 'URGENT'
  WHEN 'HIGH' THEN 'HIGH'
  WHEN 'MEDIUM' THEN 'NORMAL'
  ELSE 'LOW'
END;
