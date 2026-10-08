SET NAMES utf8mb4;

CREATE TABLE schema_migrations (
  migration VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration) VALUES
  ('001_initial.sql'),
  ('002_workflow.sql'),
  ('003_operations.sql'),
  ('004_media_invites.sql'),
  ('005_completion.sql'),
  ('006_condominium_rules.sql'),
  ('007_fiscal_workflow_rules.sql'),
  ('008_notification_sequences.sql'),
  ('009_schema_migrations.sql')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
