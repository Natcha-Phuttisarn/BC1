-- Bizcomp Club (BC1) - Hosting installation schema
-- Generated from the structure of the current BC1 database.
-- Schema only: no users, passwords, personal information, payments, or activity data.
--
-- Import this file into an EMPTY database selected in the hosting control panel.
-- This file intentionally contains no CREATE DATABASE, USE, DROP DATABASE, or DROP TABLE.

SET NAMES utf8mb4;

-- 1. User accounts
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `account_status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `chk_users_role` CHECK (`role` IN ('user','admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Public site settings
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Membership applications
CREATE TABLE IF NOT EXISTS `membership_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `student_id` varchar(30) NOT NULL,
  `faculty` varchar(200) NOT NULL,
  `major` varchar(200) NOT NULL,
  `year_level` tinyint(3) unsigned NOT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `application_status` enum('pending','waiting_payment','checking_payment','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_membership_applications_user` (`user_id`),
  CONSTRAINT `fk_membership_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_membership_applications_phone` CHECK (`phone` IS NULL OR `phone` REGEXP '^\\+?[0-9]{9,15}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Membership payments
CREATE TABLE IF NOT EXISTS `membership_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_slip` varchar(500) DEFAULT NULL,
  `payment_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_membership_payments_application` (`application_id`),
  KEY `fk_membership_payments_user` (`user_id`),
  KEY `fk_membership_payments_admin` (`verified_by`),
  CONSTRAINT `fk_membership_payments_admin` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_membership_payments_application` FOREIGN KEY (`application_id`) REFERENCES `membership_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_membership_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Membership receipts
-- The current BC1 table has no foreign keys; this definition intentionally matches it.
CREATE TABLE IF NOT EXISTS `membership_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  UNIQUE KEY `unique_payment_receipt` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Password reset tokens
-- The current BC1 table has no foreign key; this definition intentionally matches it.
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Approved members
CREATE TABLE IF NOT EXISTS `members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `student_id` varchar(30) NOT NULL,
  `admission_year` smallint(5) unsigned DEFAULT NULL,
  `faculty` varchar(200) NOT NULL,
  `major` varchar(200) NOT NULL,
  `year_level` tinyint(3) unsigned NOT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `member_status` enum('active','inactive','expired','suspended','graduated') NOT NULL DEFAULT 'active',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expired_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `student_id` (`student_id`),
  CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_members_phone` CHECK (`phone` IS NULL OR `phone` REGEXP '^\\+?[0-9]{9,15}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Activities
CREATE TABLE IF NOT EXISTS `activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext NOT NULL,
  `cover_image` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `activity_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `registration_open` datetime NOT NULL,
  `registration_close` datetime NOT NULL,
  `max_participants` int(10) unsigned DEFAULT NULL,
  `publish_status` enum('draft','published','hidden') NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_activities_admin` (`created_by`),
  CONSTRAINT `fk_activities_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Activity attachments
CREATE TABLE IF NOT EXISTS `activity_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(200) DEFAULT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_activity_files_activity` (`activity_id`),
  CONSTRAINT `fk_activity_files_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Activity gallery albums
CREATE TABLE IF NOT EXISTS `activity_gallery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) NOT NULL,
  `activity_date` date DEFAULT NULL,
  `activity_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_activity` (`activity_id`),
  KEY `idx_gallery_display` (`status`,`created_at`,`id`),
  CONSTRAINT `fk_activity_gallery_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Activity gallery images
CREATE TABLE IF NOT EXISTS `activity_gallery_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gallery_id` int(10) unsigned NOT NULL,
  `image` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_images_gallery` (`gallery_id`,`id`),
  CONSTRAINT `fk_gallery_images_gallery` FOREIGN KEY (`gallery_id`) REFERENCES `activity_gallery` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Activity registrations
CREATE TABLE IF NOT EXISTS `activity_registrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `registration_status` enum('registered','cancelled') NOT NULL DEFAULT 'registered',
  `cancel_reason` varchar(500) DEFAULT NULL,
  `registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_activity_registration` (`activity_id`,`user_id`),
  KEY `fk_activity_registrations_user` (`user_id`),
  CONSTRAINT `fk_activity_registrations_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Activity attendance
CREATE TABLE IF NOT EXISTS `activity_attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `attendance_status` enum('present','absent','late','cancelled') NOT NULL DEFAULT 'present',
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_activity_attendance` (`activity_id`,`user_id`),
  KEY `fk_activity_attendance_user` (`user_id`),
  KEY `fk_activity_attendance_admin` (`recorded_by`),
  CONSTRAINT `fk_activity_attendance_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_attendance_admin` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_activity_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Club personnel
CREATE TABLE IF NOT EXISTS `personnel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('advisor','committee') NOT NULL DEFAULT 'committee',
  `name` varchar(200) NOT NULL,
  `position` varchar(200) NOT NULL,
  `department` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `started_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `show_phone` tinyint(1) NOT NULL DEFAULT 0,
  `show_email` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','hidden','former') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_personnel_phone` CHECK (`phone` IS NULL OR `phone` REGEXP '^\\+?[0-9]{9,15}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Finance expenses
CREATE TABLE IF NOT EXISTS `finance_expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `category` varchar(120) NOT NULL,
  `description` varchar(500) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `evidence_file` varchar(500) DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_finance_expense_date` (`expense_date`),
  KEY `idx_finance_expense_status` (`status`),
  KEY `fk_finance_expense_admin` (`created_by`),
  CONSTRAINT `fk_finance_expense_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_finance_expense_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Finance incomes
CREATE TABLE IF NOT EXISTS `finance_incomes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `income_date` date NOT NULL,
  `source` varchar(150) NOT NULL,
  `description` varchar(500) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `evidence_file` varchar(500) DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_finance_income_date` (`income_date`),
  KEY `idx_finance_income_status` (`status`),
  KEY `fk_finance_income_admin` (`created_by`),
  CONSTRAINT `fk_finance_income_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_finance_income_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Privacy consents
CREATE TABLE IF NOT EXISTS `privacy_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `policy_version` varchar(30) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `consented_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_privacy_consents_user` (`user_id`),
  CONSTRAINT `fk_privacy_consents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Administrator audit logs
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_user_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_admin_audit_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minimal non-secret initial settings.
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('club_name', 'Bizcomp Club'),
  ('club_name_th', 'ชมรมบิตคอม'),
  ('faculty', 'คณะการบัญชีและการจัดการ'),
  ('university', 'มหาวิทยาลัยมหาสารคาม'),
  ('membership_fee', '20.00');

-- Password reset OTP support (19-20).
-- BC1 password-reset OTP tables.
-- Run only after selecting the intended BC1 database and completing the
-- read-only preflight documented below. This migration never changes or
-- removes existing user/password-reset data.

-- Required preflight (read-only):
-- SELECT DATABASE();
-- SELECT TABLE_NAME FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('users', 'password_resets',
--                      'password_reset_otp_challenges', 'password_reset_otp_limits');
-- SHOW CREATE TABLE users;
-- SHOW CREATE TABLE password_resets;
-- If either OTP table already exists, compare SHOW CREATE TABLE with this
-- file and stop on any difference before running the CREATE statements.

CREATE TABLE IF NOT EXISTS password_reset_otp_challenges (
    email_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    code_hmac CHAR(64) NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    verified_at BIGINT UNSIGNED NULL,
    reset_token_hash CHAR(64) NULL,
    reset_expires_at BIGINT UNSIGNED NULL,
    reset_used_at BIGINT UNSIGNED NULL,
    updated_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (email_hash),
    UNIQUE KEY uq_password_reset_otp_token (reset_token_hash),
    KEY ix_password_reset_otp_user (user_id),
    KEY ix_password_reset_otp_expiry (expires_at),
    KEY ix_password_reset_otp_reset_expiry (reset_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_otp_limits (
    scope VARCHAR(8) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    window_started_at BIGINT UNSIGNED NOT NULL,
    request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_request_at BIGINT UNSIGNED NULL,
    PRIMARY KEY (scope, key_hash),
    KEY ix_password_reset_otp_limit_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Required postflight (read-only):
-- SHOW CREATE TABLE password_reset_otp_challenges;
-- SHOW CREATE TABLE password_reset_otp_limits;
