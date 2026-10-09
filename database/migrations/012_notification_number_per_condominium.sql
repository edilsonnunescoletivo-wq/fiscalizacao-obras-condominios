ALTER TABLE notifications
  ADD COLUMN condominium_id BIGINT UNSIGNED NULL AFTER work_id;

UPDATE notifications n
JOIN works w ON w.id = n.work_id
SET n.condominium_id = w.condominium_id
WHERE n.condominium_id IS NULL;

ALTER TABLE notifications
  MODIFY condominium_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE notifications
  DROP INDEX uq_notification_number;

ALTER TABLE notifications
  ADD UNIQUE KEY uq_notification_condo_number(condominium_id, number),
  ADD INDEX idx_notifications_condo_type_status(condominium_id, type, status),
  ADD CONSTRAINT fk_notification_condo FOREIGN KEY(condominium_id) REFERENCES condominiums(id);
