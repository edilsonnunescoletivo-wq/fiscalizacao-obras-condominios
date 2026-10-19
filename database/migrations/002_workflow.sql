SET NAMES utf8mb4;

CREATE TABLE work_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_work_events_work(work_id, created_at),
  CONSTRAINT fk_we_work FOREIGN KEY(work_id) REFERENCES works(id),
  CONSTRAINT fk_we_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_types (condominium_id, name, required_default, active) VALUES
(NULL, 'Projeto arquitetônico', 1, 1),
(NULL, 'ART/RRT', 1, 1),
(NULL, 'Memorial descritivo', 1, 1),
(NULL, 'Cronograma da obra', 1, 1),
(NULL, 'Termo de responsabilidade', 1, 1),
(NULL, 'Lista de prestadores', 0, 1);
