-- ============================================================
-- TransactiWar Database Schema
-- CS6903: Network Security, IIT Hyderabad 2025-26
-- ============================================================

CREATE DATABASE IF NOT EXISTS transactiwar
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE transactiwar;

-- ============================================================
-- TABLE 1: users
-- (Task 1 - User Authentication & Session Management)
-- Your teammate will build the PHP for this, but we define it here
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    email         VARCHAR(255)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,             -- bcrypt hash, NEVER plaintext
    balance       DECIMAL(10,2) NOT NULL DEFAULT 100.00,  -- every new user gets Rs. 100
    bio           TEXT          DEFAULT NULL,          -- long content / biography
    profile_image VARCHAR(255)  DEFAULT NULL,          -- path to stored image file
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    -- CONSTRAINT chk_balance_non_negative CHECK (balance >= 0.00)   --- not availabel in older mysql version....
) ENGINE=InnoDB;


-- ============================================================
-- TABLE 2: sessions
-- (Task 1 - Session Management — optional but more secure than PHP default)
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    session_id   VARCHAR(128)  NOT NULL PRIMARY KEY,
    user_id      INT           NOT NULL,
    ip_address   VARCHAR(45)   NOT NULL,
    user_agent   TEXT          DEFAULT NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME      NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;


-- ============================================================
-- TABLE 3: transactions
-- (Task 3 - Money Transfer)
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT            NOT NULL,
    receiver_id  INT            NOT NULL,
    amount       DECIMAL(10,2)  NOT NULL,
    comment      TEXT           DEFAULT NULL,   -- optional comment, visible to receiver
    status       ENUM('success','failed','pending') DEFAULT 'success',
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE RESTRICT,

    -- Constraints
   -- CONSTRAINT chk_amount_positive     CHECK (amount > 0),
   -- CONSTRAINT chk_no_self_transfer    CHECK (sender_id <> receiver_id),

    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB;


-- ============================================================
-- TABLE 4: activity_log
-- (Required by spec: Webpage, Username, Timestamp, Client IP)
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    webpage     VARCHAR(255) NOT NULL,
    username    VARCHAR(100) NOT NULL DEFAULT 'guest',
    ip_address  VARCHAR(45)  NOT NULL,              -- VARCHAR(45) supports IPv6
    logged_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username  (username),
    INDEX idx_logged_at (logged_at),
    INDEX idx_ip        (ip_address)
) ENGINE=InnoDB;


-- ============================================================
-- DEMO / TEST ACCOUNTS (auto-created as required by spec)
-- Passwords below are bcrypt of: Password@123
-- Run create_accounts.php to generate fresh bcrypt hashes
-- ============================================================
INSERT INTO users (username, email, password_hash, balance) VALUES
('alice',   'alice@gmail.com',   '$2y$12$KIXxT3nR8hMrqWvYkL2GdOQ9v5z1Jb7UYxQpNmS6aRtE3cFwHlX4i', 100.00),
('bob',     'bob@gmail.com',     '$2y$12$KIXxT3nR8hMrqWvYkL2GdOQ9v5z1Jb7UYxQpNmS6aRtE3cFwHlX4i', 100.00),
('charlie', 'charlie@gmail.com', '$2y$12$KIXxT3nR8hMrqWvYkL2GdOQ9v5z1Jb7UYxQpNmS6aRtE3cFwHlX4i', 100.00);
