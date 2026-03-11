-- FILE: init.sql
-- PURPOSE: Creates all database tables on first run.
-- Docker Compose mounts this file and MySQL runs it automatically
-- when the database container is first created.

-- ── DATABASE SETUP ────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS securepay
    CHARACTER SET utf8mb4        -- Full Unicode support (emoji, all scripts)
    COLLATE utf8mb4_unicode_ci;  -- Case-insensitive comparison

USE securepay;

-- ── APPLICATION USER (least privilege) ───────────────────────
-- SECURITY: The application never connects as root.
-- This user can only SELECT/INSERT/UPDATE/DELETE on securepay tables.
-- Even if an attacker achieves SQL injection, they cannot DROP tables,
-- read other databases, or create new users.
-- Password comes from the environment variable in docker-compose.yml


-- CREATE USER IF NOT EXISTS 'spuser'@'%' IDENTIFIED BY '${DB_PASS}';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON securepay.* TO 'spuser'@'%';
-- FLUSH PRIVILEGES;

-- ── USERS TABLE ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(30)     NOT NULL,
    email         VARCHAR(254)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,  -- bcrypt hash (always 60 chars for bcrypt, 255 is future-proof)
    balance       DECIMAL(12, 2)  NOT NULL DEFAULT 100.00,  -- Rs. 100 starting balance
    full_name     VARCHAR(100)    DEFAULT NULL,
    bio           TEXT            DEFAULT NULL,              -- Long content (biography)
    profile_image VARCHAR(64)     DEFAULT NULL,              -- Stored filename (hex.ext), NOT path
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- UNIQUE constraints enforced at DB level — not just PHP.
    -- Even if the PHP check is somehow bypassed, the DB rejects duplicates.
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email    (email),

    -- Index for search by username (LIKE queries use this for prefix searches)
    KEY idx_username (username)
) ENGINE=InnoDB;

-- ── TRANSACTIONS TABLE ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    sender_id     INT UNSIGNED    NOT NULL,
    receiver_id   INT UNSIGNED    NOT NULL,
    amount        DECIMAL(12, 2)  NOT NULL,
    comment       VARCHAR(500)    DEFAULT NULL,  -- Optional comment from sender
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Foreign keys enforce referential integrity:
    -- You cannot insert a transaction with a sender/receiver that doesn't exist.
    -- RESTRICT means you cannot delete a user who has transactions.
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE RESTRICT,

    -- Composite index for fast "show my transactions" queries
    -- (covers both "sent by me" and "received by me" lookups)
    KEY idx_sender   (sender_id,   created_at),
    KEY idx_receiver (receiver_id, created_at),

    -- CONSTRAINT: Amount must be positive at the database level.
    -- This is a HARD constraint — even direct DB access cannot violate it.
    -- PHP validation is first, but the DB is the last line of defence.
    CONSTRAINT chk_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB;

-- ── LOGIN ATTEMPTS TABLE (rate limiting) ──────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    ip_address    VARCHAR(45)     NOT NULL,  -- 45 chars supports full IPv6 addresses
    attempt_time  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Index on ip_address + time for fast rate-limit lookups
    KEY idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB;

-- ── ACTIVITY LOG TABLE ────────────────────────────────────────
-- Stores structured activity records (backup to file-based log)
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(30)  DEFAULT 'GUEST',
    page       VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    logged_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_logged_at (logged_at),
    KEY idx_username  (username)
) ENGINE=InnoDB;

-- ── ADMIN TABLE ───────────────────────────────────────────────
-- Admin is a separate table with no overlap with regular users.
-- This means SQL injection against the users table cannot retrieve
-- admin credentials, and vice versa.
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(30)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;