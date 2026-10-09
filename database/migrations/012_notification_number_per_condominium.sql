ALTER TABLE notifications
  DROP INDEX uq_notification_number;

ALTER TABLE notifications
  ADD INDEX idx_notification_number(number);
