-- Database for the University Enrollment & Records System.
-- Run once (command line or phpMyAdmin's SQL tab) to create the database
-- and its tables. Matches the "Creation of the database" and "MySQL Create
-- Table" patterns from the course slides.
--
-- Student data — normalized into 4 related tables instead of one wide table:
--   programs                     — lookup table, so a program name is
--                                   stored once and referenced everywhere
--                                   instead of repeated on every student.
--   students                     — core identity, contact and enrollment
--                                   status; the table every screen needs.
--   student_personal_details     — demographic info split out from the
--                                   core row (one-to-one with students).
--   student_emergency_contacts   — a distinct sub-concept (someone else's
--                                   info), also one-to-one with students.
--
-- Security (final term):
--   users                        — accounts that can sign in; passwords are
--                                   stored only as password_hash() output.
--   api_tokens                   — bearer tokens for the REST API; only the
--                                   SHA-256 hash of each token is stored.
--   sso_tokens                   — one-time codes for signing in to (and out
--                                   of) both apps at once; hash only.
--   login_attempts               — failed sign-ins, used for rate limiting.
--   settings                     — system preferences (default term, rows per page).
--
-- Subjects, course offerings and enrollments are created by the Laravel
-- module's migrations, not here.
--
-- Every table uses utf8mb4_unicode_ci (Laravel's default) so that foreign
-- keys between these tables and the Laravel tables have matching collations.
-- After importing this file, run:  php backend/database/seed_users.php

-- This file is UTF-8 (e.g. the "–" in '2026–2027'); read it as such even when
-- the mysql command line defaults to another character set.
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS enrollment_system_min
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE enrollment_system_min;

CREATE TABLE IF NOT EXISTS programs (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
    student_id     VARCHAR(20)  PRIMARY KEY,
    first_name     VARCHAR(60)  NOT NULL,
    middle_name    VARCHAR(60)  NULL,
    last_name      VARCHAR(60)  NOT NULL,
    suffix         VARCHAR(20)  NULL,
    email          VARCHAR(120) NULL,
    contact_number VARCHAR(30)  NULL,
    address        VARCHAR(255) NULL,
    program_id     INT          NOT NULL,
    year_level     VARCHAR(20)  NOT NULL,
    academic_year  VARCHAR(20)  NULL,
    status         VARCHAR(20)  NOT NULL DEFAULT 'Active',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_personal_details (
    student_id    VARCHAR(20) PRIMARY KEY,
    date_of_birth DATE        NULL,
    gender        VARCHAR(20) NULL,
    civil_status  VARCHAR(20) NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_emergency_contacts (
    student_id   VARCHAR(20)  PRIMARY KEY,
    name         VARCHAR(100) NULL,
    relationship VARCHAR(50)  NULL,
    number       VARCHAR(30)  NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounts. The role is set by the server (seeder or an admin), never by
-- the person signing up. VARCHAR(255) leaves room for future hash formats.
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin', 'registrar', 'faculty') NOT NULL DEFAULT 'faculty',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bearer tokens for API clients such as Postman. The plain token is shown to
-- the client once; only its SHA-256 hash is kept, so a leaked table cannot be
-- used to sign in. Deleting a user deletes their tokens.
CREATE TABLE IF NOT EXISTS api_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT       NOT NULL,
    token_hash CHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME  NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single sign-on between the main app and the Laravel module. A one-time code
-- (valid 60 seconds) carries a signed-in user from one app to the other
-- (purpose 'login'), or ends the user's session in the other app at logout
-- (purpose 'logout'). Only the SHA-256 hash is stored; a code is deleted when used.
CREATE TABLE IF NOT EXISTS sso_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT                     NOT NULL,
    purpose    ENUM('login', 'logout') NOT NULL,
    token_hash CHAR(64)                NOT NULL UNIQUE,
    expires_at DATETIME                NOT NULL,
    created_at TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-in attempts. Too many recent rows for one username or IP
-- address blocks further attempts for a while (brute-force protection).
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_username (username, attempted_at),
    INDEX idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System preferences set by an administrator on the Settings page, e.g. the
-- default term used by the Dashboard and Reports. One row per setting.
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(50)  PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_by    INT          NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('academic_year', '2026–2027'),
    ('semester', '1st Semester'),
    ('rows_per_page', '10');

INSERT IGNORE INTO programs (name) VALUES
    ('BS Information Technology'),
    ('BS Computer Science'),
    ('BS Information System');
