-- PWI Weight Tracker — schema for MyDataWorld
-- Run this in phpMyAdmin's SQL tab against the MyDataWorld database.
--
-- `users` and `sessions` are shared across every app in MyDataWorld — one
-- login works for all of them (create it once via My Apps Hub). This file's
-- own table is `pwi_entries`, prefixed so it can't collide with another
-- app's tables in the same database.

CREATE TABLE IF NOT EXISTS users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(100) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  display_name   VARCHAR(100) NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  token       CHAR(64) PRIMARY KEY,
  user_id     INT NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at  TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per person per day. UNIQUE(user_id, entry_date) is what lets a
-- check-in for a date that already has an entry overwrite it instead of
-- creating a duplicate (same behavior the old Google Sheet version had,
-- now enforced by the database instead of a linear row scan).
CREATE TABLE IF NOT EXISTS pwi_entries (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  entry_date      DATE NOT NULL,
  weight          DECIMAL(5,1) NULL,
  blood_pressure  VARCHAR(20) NULL,
  blood_sugar     DECIMAL(5,1) NULL,
  gym             TINYINT(1) NOT NULL DEFAULT 0,
  walk            TINYINT(1) NOT NULL DEFAULT 0,
  walk_distance   DECIMAL(5,2) NULL,
  zumba           TINYINT(1) NOT NULL DEFAULT 0,
  steps           INT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_date (user_id, entry_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- SCHEMA CHANGE: medications (2026-11-05) ----------
-- Run once. Each person manages their own medication list and dose log.
-- A medication is deactivated, never deleted, so its past doses keep their
-- name (hence ON DELETE RESTRICT from the dose side).
CREATE TABLE IF NOT EXISTS pwi_medications (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  name            VARCHAR(100) NOT NULL,
  default_dosage  VARCHAR(50) NULL,           -- free text, e.g. "2.5 mg"
  is_injection    TINYINT(1) NOT NULL DEFAULT 0,
  active          TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_med (user_id, name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pwi_med_doses (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  medication_id   INT NOT NULL,
  taken_at        DATETIME NOT NULL,          -- local date/time the dose was taken
  dosage          VARCHAR(50) NULL,
  injection_site  VARCHAR(50) NULL,           -- only for injections
  notes           VARCHAR(500) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pwi_med_doses_user (user_id, taken_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (medication_id) REFERENCES pwi_medications(id) ON DELETE RESTRICT
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
