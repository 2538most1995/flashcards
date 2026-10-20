-- SQL Schema for Flashcards 500 ครูผู้ช่วย 2569
CREATE DATABASE IF NOT EXISTS `flashcards_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `flashcards_db`;

-- Flashcards table
CREATE TABLE IF NOT EXISTS `flashcards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `num` INT NOT NULL UNIQUE,
    `word` VARCHAR(150) NOT NULL,
    `pos` VARCHAR(50) DEFAULT '',
    `meaning` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `reading` VARCHAR(150) DEFAULT '',
    `example` TEXT DEFAULT NULL,
    `example_th` TEXT DEFAULT NULL,
    `cloze` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT '📖',
    `synonyms` TEXT DEFAULT NULL,
    `antonyms` TEXT DEFAULT NULL,
    `memory_hook` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cat` (`category`),
    INDEX `idx_word` (`word`),
    INDEX `idx_num` (`num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Card progress table
CREATE TABLE IF NOT EXISTS `card_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `card_num` INT NOT NULL,
    `status` ENUM('unseen', 'learning', 'review', 'mastered') NOT NULL DEFAULT 'unseen',
    `review_count` INT NOT NULL DEFAULT 0,
    `last_reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_user_card` (`user_id`, `card_num`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default User
INSERT IGNORE INTO `users` (`id`, `username`, `display_name`) VALUES
(1, 'student1', 'ผู้สอบครูผู้ช่วย 2569');
