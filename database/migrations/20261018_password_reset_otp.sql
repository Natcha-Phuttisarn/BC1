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
