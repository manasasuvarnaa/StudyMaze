-- StudyMaze Safe Database Migration
USE `studymaze`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME NULL DEFAULT NULL AFTER `password_hash`;

CREATE TABLE IF NOT EXISTS `email_verifications` (
  `verification_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME DEFAULT NULL,
  KEY `idx_ev_user` (`user_id`),
  KEY `idx_ev_token` (`token_hash`),
  CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME DEFAULT NULL,
  KEY `idx_pr_user` (`user_id`),
  KEY `idx_pr_code` (`code_hash`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(255) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_la_identifier` (`identifier`),
  KEY `idx_la_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
