-- StudyMaze Database Schema
-- Study & Productivity Management Website

CREATE DATABASE IF NOT EXISTS `studymaze` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `studymaze`;

-- 1. users table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. subjects table
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `colour` CHAR(7) NOT NULL DEFAULT '#ea580c',
  `description` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_subjects_user` (`user_id`),
  CONSTRAINT `fk_subjects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. study_sessions table
CREATE TABLE IF NOT EXISTS `study_sessions` (
  `session_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED DEFAULT NULL,
  `session_label` VARCHAR(150) DEFAULT NULL,
  `session_type` ENUM('focus','pomodoro','stopwatch','break') NOT NULL DEFAULT 'focus',
  `status` ENUM('running','paused','completed','cancelled') NOT NULL DEFAULT 'completed',
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `planned_duration_seconds` INT UNSIGNED DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `accumulated_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_resumed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sessions_user_date` (`user_id`, `started_at`),
  KEY `idx_sessions_subject` (`subject_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sessions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. tasks table
CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED DEFAULT NULL,
  `task_name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `priority` ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` ENUM('pending','completed') NOT NULL DEFAULT 'pending',
  `deadline` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_tasks_user_status` (`user_id`, `status`),
  KEY `idx_tasks_subject` (`subject_id`),
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. user_settings table
CREATE TABLE IF NOT EXISTS `user_settings` (
  `setting_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `theme_name` ENUM('default','ghibli','crimson','forest','midnight') NOT NULL DEFAULT 'default',
  `appearance` ENUM('light','dark') NOT NULL DEFAULT 'light',
  `accent_colour` CHAR(7) NOT NULL DEFAULT '#ea580c',
  `daily_goal_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 180,
  `focus_duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  `short_break_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `long_break_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `pomodoros_before_long_break` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `auto_start_breaks` BOOLEAN NOT NULL DEFAULT 1,
  `sound_enabled` BOOLEAN NOT NULL DEFAULT 1,
  `selected_sound` VARCHAR(50) DEFAULT 'rain',
  `sound_volume` TINYINT UNSIGNED NOT NULL DEFAULT 70,
  `timer_notifications` BOOLEAN NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_settings_user` (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. quotes table
CREATE TABLE IF NOT EXISTS `quotes` (
  `quote_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `quote_text` VARCHAR(500) NOT NULL,
  `author` VARCHAR(150) DEFAULT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'motivation',
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. achievements table
CREATE TABLE IF NOT EXISTS `achievements` (
  `achievement_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `achievement_key` VARCHAR(60) NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(50) NOT NULL,
  `criteria_type` ENUM('sessions','study_minutes','streak_days') NOT NULL,
  `criteria_value` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_achievement_key` (`achievement_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. user_achievements table
CREATE TABLE IF NOT EXISTS `user_achievements` (
  `user_achievement_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `achievement_id` INT UNSIGNED NOT NULL,
  `unlocked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_achievement` (`user_id`, `achievement_id`),
  KEY `idx_achievements_id` (`achievement_id`),
  CONSTRAINT `fk_user_achievements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_achievements_achievement` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`achievement_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default motivational quotes
INSERT INTO `quotes` (`quote_text`, `author`, `category`, `is_active`) VALUES
('An investment in knowledge pays the best interest.', 'Benjamin Franklin', 'study', 1),
('The expert in anything was once a beginner.', 'Helen Hayes', 'motivation', 1),
('Focus is a muscle. The more you practice, the stronger it gets.', 'Anonymous', 'focus', 1),
('Small daily improvements over time lead to stunning results.', 'Robin Sharma', 'productivity', 1),
('Success is the sum of small efforts, repeated day in and day out.', 'Robert Collier', 'study', 1),
('Do what you have to do until you can do what you want to do.', 'Oprah Winfrey', 'motivation', 1),
('Discipline is choosing between what you want now and what you want most.', 'Abraham Lincoln', 'focus', 1),
('It always seems impossible until it is done.', 'Nelson Mandela', 'study', 1)
ON DUPLICATE KEY UPDATE `quote_id`=`quote_id`;

-- Default achievements definitions
INSERT INTO `achievements` (`achievement_key`, `title`, `description`, `icon`, `criteria_type`, `criteria_value`) VALUES
('first_step', 'First Step', 'Completed your first study session', 'award', 'sessions', 1),
('focus_apprentice', 'Focus Apprentice', 'Completed 5 study sessions', 'target', 'sessions', 5),
('study_master', 'Study Master', 'Completed 25 study sessions', 'medal', 'sessions', 25),
('hour_power', 'Power Hour', 'Accumulated 60 minutes of focused study', 'clock', 'study_minutes', 60),
('deep_diver', 'Deep Diver', 'Accumulated 300 minutes of focused study', 'zap', 'study_minutes', 300),
('century_club', 'Century Club', 'Accumulated 1000 minutes of focused study', 'crown', 'study_minutes', 1000),
('streak_starter', 'Consistent Spark', 'Maintained a 3-day study streak', 'flame', 'streak_days', 3),
('streak_champion', 'Streak Champion', 'Maintained a 7-day study streak', 'fire', 'streak_days', 7)
ON DUPLICATE KEY UPDATE `achievement_id`=`achievement_id`;

-- 9. email_verifications table
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

-- 10. password_resets table
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

-- 11. login_attempts table
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(255) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_la_identifier` (`identifier`),
  KEY `idx_la_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
