CREATE DATABASE IF NOT EXISTS glossa;
USE glossa;

CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL DEFAULT 18,
    birth_date DATE NULL,
    gender VARCHAR(30) NOT NULL DEFAULT 'not_specified',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_code (email, code)
);

CREATE TABLE IF NOT EXISTS scan_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    undertone VARCHAR(50) NOT NULL,
    skin_tone VARCHAR(50) NULL,
    accuracy INT NOT NULL DEFAULT 95,
    recommendations TEXT NULL,
    dominant_color VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_history (user_id, created_at DESC)
);
