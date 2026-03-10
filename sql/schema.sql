-- ============================================================
-- TransactiWar Database Schema
-- CS6903: Network Security, IIT Hyderabad 2025-26
-- ============================================================

CREATE DATABASE IF NOT EXISTS transactiwar
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE transactiwar;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    email         VARCHAR(255)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    balance       DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    bio           TEXT          DEFAULT NULL,
    profile_image VARCHAR(255)  DEFAULT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_balance_non_negative CHECK (balance >= 0.00)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions (
    session_id   VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id      INT          NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    user_agent   TEXT         DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    expires_at   TIMESTAMP    NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT           NOT NULL,
    receiver_id  INT           NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    comment      TEXT          DEFAULT NULL,
    status       ENUM('success','failed','pending') DEFAULT 'success',
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_amount_positive  CHECK (amount > 0),
    CONSTRAINT chk_no_self_transfer CHECK (sender_id <> receiver_id),
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    webpage     VARCHAR(255) NOT NULL,
    username    VARCHAR(100) NOT NULL DEFAULT 'guest',
    ip_address  VARCHAR(45)  NOT NULL,
    logged_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username  (username),
    INDEX idx_logged_at (logged_at)
) ENGINE=InnoDB;
