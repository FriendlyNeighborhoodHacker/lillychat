-- LillyChat Schema
-- Assumes database 'lillychat' already exists and you connect to it before running.
-- Example:
--   mysql -u lillychat -p -h mysql.brianrosenthal.org lillychat < lillychat/schema.sql;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Users: invite-only registration with optional admin role
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin   TINYINT(1) NOT NULL DEFAULT 0,
  profile_photo VARCHAR(255) DEFAULT NULL,

  -- Invitation flow
  invite_token CHAR(64) DEFAULT NULL,
  invite_expires_at DATETIME DEFAULT NULL,
  invited_by_user_id INT DEFAULT NULL,

  -- Email verification (set upon accepting invite)
  email_verified_at DATETIME DEFAULT NULL,

  -- Password reset flow
  password_reset_token_hash CHAR(64) DEFAULT NULL,
  password_reset_expires_at DATETIME DEFAULT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_users_invited_by FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_invite_token ON users(invite_token);
CREATE INDEX idx_users_pwreset_expires ON users(password_reset_expires_at);

-- Chats: threads created by members
CREATE TABLE IF NOT EXISTS chats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  created_by_user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_chats_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_chats_title ON chats(title);

-- Chat membership: which users are in which chats
CREATE TABLE IF NOT EXISTS chat_members (
  chat_id INT NOT NULL,
  user_id INT NOT NULL,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (chat_id, user_id),
  CONSTRAINT fk_cm_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Messages within chats
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chat_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_messages_chat_created ON messages(chat_id, created_at);

-- Application settings (key-value)
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(191) NOT NULL UNIQUE,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (key_name, value) VALUES
  ('site_title', 'LillyChat'),
  ('announcement', ''),
  ('time_zone', 'America/New_York')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Optional: seed an initial admin (update email and password hash, or leave commented)
-- INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verified_at)
-- VALUES ('Lilly','Rosenthal','you@example.com', '$2y$10$ReplaceWithBcryptHash', 1, NOW());
