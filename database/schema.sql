CREATE DATABASE IF NOT EXISTS argotesia_ops
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE argotesia_ops;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  worker_key VARCHAR(40) NOT NULL,
  username VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
  worker_token VARCHAR(96) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','paused') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_worker_key (worker_key),
  UNIQUE KEY uk_users_username (username),
  UNIQUE KEY uk_users_email (email),
  UNIQUE KEY uk_users_worker_token (worker_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_key VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  client_name VARCHAR(180) NULL,
  aliases TEXT NULL,
  client_phones TEXT NULL,
  local_path_ivan VARCHAR(500) NULL,
  local_path_oscar VARCHAR(500) NULL,
  server_ssh VARCHAR(255) NULL,
  server_ssh_ivan VARCHAR(255) NULL,
  server_ssh_oscar VARCHAR(255) NULL,
  repo_url VARCHAR(255) NULL,
  codex_rules TEXT NULL,
  operational_context MEDIUMTEXT NULL,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_projects_key (project_key),
  KEY idx_projects_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intake_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_channel ENUM('whatsapp','audio','email','phone','manual','web','other') NOT NULL DEFAULT 'manual',
  external_ref VARCHAR(190) NULL,
  client_name VARCHAR(180) NULL,
  client_contact VARCHAR(190) NULL,
  title VARCHAR(180) NOT NULL,
  transcript MEDIUMTEXT NOT NULL,
  raw_notes TEXT NULL,
  project_id BIGINT UNSIGNED NULL,
  assigned_user_id INT UNSIGNED NULL,
  detected_intent ENUM('incident','bug','change','question','billing','unknown') NOT NULL DEFAULT 'unknown',
  urgency ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  status ENUM('nuevo','clasificado','ticket_creado','descartado') NOT NULL DEFAULT 'nuevo',
  ai_summary TEXT NULL,
  codex_prompt MEDIUMTEXT NULL,
  client_reply_draft TEXT NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_intake_source_external_ref (source_channel, external_ref),
  KEY idx_intake_status (status),
  KEY idx_intake_project (project_id),
  KEY idx_intake_assigned_user (assigned_user_id),
  CONSTRAINT fk_intake_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_intake_assigned_user
    FOREIGN KEY (assigned_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_intake_creator
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  intake_id BIGINT UNSIGNED NULL,
  project_id BIGINT UNSIGNED NULL,
  assigned_user_id INT UNSIGNED NULL,
  code VARCHAR(40) NULL,
  title VARCHAR(180) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  client_name VARCHAR(180) NULL,
  client_contact VARCHAR(190) NULL,
  source_channel ENUM('whatsapp','audio','email','phone','manual','web','other') NOT NULL DEFAULT 'manual',
  intent ENUM('incident','bug','change','question','billing','unknown') NOT NULL DEFAULT 'unknown',
  urgency ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  status ENUM('nuevo','asignado','en_propuesta','en_revision','aprobado','en_progreso','resuelto','cerrado','descartado') NOT NULL DEFAULT 'nuevo',
  client_reply_draft TEXT NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  closed_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tickets_code (code),
  UNIQUE KEY uk_tickets_intake_id (intake_id),
  KEY idx_tickets_status (status),
  KEY idx_tickets_assigned_user (assigned_user_id),
  KEY idx_tickets_project (project_id),
  CONSTRAINT fk_tickets_intake
    FOREIGN KEY (intake_id) REFERENCES intake_items(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_tickets_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_tickets_assigned_user
    FOREIGN KEY (assigned_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_tickets_creator
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  body TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_events_ticket (ticket_id),
  CONSTRAINT fk_ticket_events_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_events_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_proposals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  worker_user_id INT UNSIGNED NOT NULL,
  source ENUM('local_model','codex','manual') NOT NULL DEFAULT 'local_model',
  model_name VARCHAR(120) NULL,
  status ENUM('ready','approved','rejected','superseded') NOT NULL DEFAULT 'ready',
  body MEDIUMTEXT NOT NULL,
  client_reply_draft TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_proposals_ticket (ticket_id),
  KEY idx_ticket_proposals_worker (worker_user_id),
  KEY idx_ticket_proposals_status (status),
  CONSTRAINT fk_ticket_proposals_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_proposals_worker
    FOREIGN KEY (worker_user_id) REFERENCES users(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  worker_user_id INT UNSIGNED NOT NULL,
  ticket_id BIGINT UNSIGNED NULL,
  status ENUM('started','completed','failed') NOT NULL DEFAULT 'started',
  message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_worker_runs_worker (worker_user_id),
  KEY idx_worker_runs_ticket (ticket_id),
  CONSTRAINT fk_worker_runs_worker
    FOREIGN KEY (worker_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_worker_runs_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (id, worker_key, username, name, email, password_hash, role, worker_token, must_change_password, status) VALUES
  (1, 'ivan', 'ivan', 'Ivan Argote', 'ivan@argotes.com', '$2y$12$QNkmGXVxHi8gt.M6xgvMaOTzxYKJOdTqOUt1MOgxc97LNgMm2iR/6', 'admin', '269a9f3235d419f9d7fb3575f9bf92e0e9fe18d598be8f6a', 0, 'active'),
  (2, 'oscar', 'oscar', 'Oscar Argote', 'oscar@argotes.com', '$2y$12$QNkmGXVxHi8gt.M6xgvMaOTzxYKJOdTqOUt1MOgxc97LNgMm2iR/6', 'admin', 'aceb2f3c29b5e9db32e917936d4a4135d8b002e233d9f507', 0, 'active')
ON DUPLICATE KEY UPDATE
  username = VALUES(username),
  name = VALUES(name),
  email = VALUES(email),
  role = VALUES(role),
  status = VALUES(status);

INSERT INTO projects
  (id, project_key, name, client_name, aliases, client_phones, local_path_ivan, local_path_oscar, server_ssh, server_ssh_ivan, server_ssh_oscar, repo_url, codex_rules, operational_context, status)
VALUES
  (1, 'argotesia-ops', 'ArgotesIA Ops', 'ArgotesIA', 'ArgotesIA Ops\nArgotesIA', NULL, '/Users/iargote/projects/Tec/argotesia-ops', NULL, NULL, NULL, NULL, NULL, 'Codex/modelo local debe diagnosticar y proponer. No implementar sin autorizacion humana.', NULL, 'active')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  client_name = VALUES(client_name),
  aliases = VALUES(aliases),
  client_phones = VALUES(client_phones),
  local_path_ivan = VALUES(local_path_ivan),
  codex_rules = VALUES(codex_rules),
  status = VALUES(status);
