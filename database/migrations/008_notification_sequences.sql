SET NAMES utf8mb4;

ALTER TABLE notifications
  DROP INDEX uq_notification_number,
  ADD INDEX idx_notifications_number (number);

CREATE TABLE notification_sequences (
  condominium_id BIGINT UNSIGNED NOT NULL,
  sequence_year SMALLINT UNSIGNED NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (condominium_id, sequence_year),
  CONSTRAINT fk_notification_sequence_condo FOREIGN KEY (condominium_id) REFERENCES condominiums(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
