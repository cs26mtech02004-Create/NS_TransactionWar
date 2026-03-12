-- FILE: init.sql

CREATE DATABASE IF NOT EXISTS securepay
    CHARACTER SET  utf8mb4
    COLLATE        utf8mb4_unicode_ci;

USE securepay;

-- GRANT SELECT, INSERT, UPDATE, DELETE ON securepay.* TO '${DB_USER}'@'%';
-- FLUSH PRIVILEGES;

CREATE TABLE IF NOT EXISTS users (
    -- Internal auto-increment PK. NEVER shown to users. Used only for
    -- foreign keys and internal JOINs. Stays on the server.
    id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,

    -- Public identifier shown to users and used for transfers.
    -- Generated in PHP as bin2hex(random_bytes(6)) = 12 hex chars.
    -- Not sequential, not guessable. A user who knows ID "3" cannot
    -- derive that "4" or "5" exist and start probing those accounts.
    public_id     VARCHAR(12)    NOT NULL,

    username      VARCHAR(30)    NOT NULL,
    email         VARCHAR(254)   NOT NULL,
    password_hash VARCHAR(255)   NOT NULL,
    balance       DECIMAL(12,2)  NOT NULL DEFAULT 100.00,
    full_name     VARCHAR(100)   DEFAULT NULL,
    bio           TEXT           DEFAULT NULL,
    profile_image VARCHAR(64)    DEFAULT NULL,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY   (id),
    UNIQUE KEY    uq_public_id (public_id),
    UNIQUE KEY    uq_username  (username),
    UNIQUE KEY    uq_email     (email),
    KEY           idx_username (username),

    CONSTRAINT chk_balance_non_negative CHECK (balance >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    sender_id   INT UNSIGNED   NOT NULL,
    receiver_id INT UNSIGNED   NOT NULL,
    amount      DECIMAL(12,2)  NOT NULL,
    comment     VARCHAR(500)   DEFAULT NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_sender   (sender_id,   created_at),
    KEY idx_receiver (receiver_id, created_at),
    CONSTRAINT chk_amount_positive     CHECK (amount > 0),
    CONSTRAINT chk_no_self_transfer    CHECK (sender_id != receiver_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(50)  NOT NULL,
    attempt_time DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY          idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(30)  NOT NULL DEFAULT 'GUEST',
    page       VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    logged_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logged_at (logged_at),
    KEY idx_username  (username),
    KEY idx_ip        (ip_address)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(30)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;