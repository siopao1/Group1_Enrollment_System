-- Fresh database for the Add Student feature.
-- Run once (command line or phpMyAdmin's SQL tab) to create the database
-- and its tables. Matches the "Creation of the database" and "MySQL Create
-- Table" patterns from the course slides.
--
-- Normalized into 4 related tables instead of one wide students table:
--   programs                     — lookup table, so a program name is
--                                   stored once and referenced everywhere
--                                   instead of repeated on every student.
--   students                     — core identity, contact and enrollment
--                                   status; the table every screen needs.
--   student_personal_details     — demographic info split out from the
--                                   core row (one-to-one with students).
--   student_emergency_contacts   — a distinct sub-concept (someone else's
--                                   info), also one-to-one with students.

CREATE DATABASE IF NOT EXISTS enrollment_system_min;

USE enrollment_system_min;

CREATE TABLE IF NOT EXISTS programs (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
);

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
);

CREATE TABLE IF NOT EXISTS student_personal_details (
    student_id    VARCHAR(20) PRIMARY KEY,
    date_of_birth DATE        NULL,
    gender        VARCHAR(20) NULL,
    civil_status  VARCHAR(20) NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_emergency_contacts (
    student_id   VARCHAR(20)  PRIMARY KEY,
    name         VARCHAR(100) NULL,
    relationship VARCHAR(50)  NULL,
    number       VARCHAR(30)  NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

INSERT IGNORE INTO programs (name) VALUES
    ('BS Information Technology'),
    ('BS Computer Science'),
    ('BS Information System');
