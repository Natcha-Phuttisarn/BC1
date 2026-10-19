-- BC1.sql
-- ไฟล์นี้ใช้สำหรับสร้างโครงสร้างและข้อมูลตั้งต้นของฐานข้อมูล BC1
-- =========================================================
-- DATABASE : BC1
-- BIZCOMP CLUB MANAGEMENT SYSTEM
-- =========================================================

CREATE DATABASE IF NOT EXISTS BC1
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE BC1;


-- =========================================================
-- 1. USERS
-- บัญชีผู้ใช้งานสำหรับ Login
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    profile_image VARCHAR(255) DEFAULT NULL,

    role ENUM(
        'user',
        'admin'
    ) NOT NULL DEFAULT 'user',

    account_status ENUM(
        'active',
        'inactive',
        'blocked'
    ) NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_users_role
        CHECK (role IN ('user', 'admin'))

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 2. MEMBERSHIP APPLICATIONS
-- ใบสมัครเข้าเป็นสมาชิกชมรม
-- =========================================================

CREATE TABLE IF NOT EXISTS membership_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    student_id VARCHAR(30) NOT NULL,

    faculty VARCHAR(200) NOT NULL,
    major VARCHAR(200) NOT NULL,

    year_level TINYINT UNSIGNED NOT NULL,

    gender VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,

    application_status ENUM(
        'pending',
        'waiting_payment',
        'checking_payment',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    admin_note TEXT DEFAULT NULL,

    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_membership_applications_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_membership_applications_phone
        CHECK (phone IS NULL OR phone REGEXP '^\\+?[0-9]{9,15}$')

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 3. MEMBERSHIP PAYMENTS
-- การชำระค่าสมัครสมาชิก
-- =========================================================

CREATE TABLE IF NOT EXISTS membership_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    application_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    payment_method VARCHAR(100) DEFAULT NULL,

    payment_slip VARCHAR(500) DEFAULT NULL,

    payment_status ENUM(
        'pending',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    admin_note TEXT DEFAULT NULL,

    paid_at DATETIME DEFAULT NULL,

    verified_by INT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_membership_payments_application
        FOREIGN KEY (application_id)
        REFERENCES membership_applications(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_membership_payments_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_membership_payments_admin
        FOREIGN KEY (verified_by)
        REFERENCES users(id)
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
CREATE TABLE IF NOT EXISTS finance_expenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    evidence_file VARCHAR(500) DEFAULT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_finance_expense_date (expense_date),
    KEY idx_finance_expense_status (status),
    CONSTRAINT chk_finance_expense_amount CHECK (amount > 0),
    CONSTRAINT fk_finance_expense_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_incomes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    income_date DATE NOT NULL,
    source VARCHAR(150) NOT NULL,
    description VARCHAR(500) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    evidence_file VARCHAR(500) DEFAULT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_finance_income_date (income_date), KEY idx_finance_income_status (status),
    CONSTRAINT chk_finance_income_amount CHECK (amount > 0),
    CONSTRAINT fk_finance_income_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. MEMBERS
-- สมาชิกชมรมที่ได้รับการอนุมัติแล้ว
-- =========================================================

CREATE TABLE IF NOT EXISTS members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    student_id VARCHAR(30) NOT NULL UNIQUE,

    faculty VARCHAR(200) NOT NULL,
    major VARCHAR(200) NOT NULL,

    year_level TINYINT UNSIGNED NOT NULL,

    gender VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,

    member_status ENUM(
        'active',
        'inactive',
        'expired',
        'suspended'
    ) NOT NULL DEFAULT 'active',

    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    expired_at DATETIME DEFAULT NULL,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_members_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_members_phone
        CHECK (phone IS NULL OR phone REGEXP '^\\+?[0-9]{9,15}$')

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 5. ACTIVITIES
-- กิจกรรมที่ Admin โพสต์
-- =========================================================

CREATE TABLE IF NOT EXISTS activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,

    short_description VARCHAR(500) DEFAULT NULL,

    description LONGTEXT NOT NULL,

    cover_image VARCHAR(500) DEFAULT NULL,

    location VARCHAR(255) DEFAULT NULL,

    activity_date DATE NOT NULL,

    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,

    registration_open DATETIME NOT NULL,
    registration_close DATETIME NOT NULL,

    max_participants INT UNSIGNED DEFAULT NULL,

    publish_status ENUM(
        'draft',
        'published',
        'hidden'
    ) NOT NULL DEFAULT 'draft',

    created_by INT UNSIGNED DEFAULT NULL,

    published_at DATETIME DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_activities_admin
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 6. ACTIVITY FILES
-- ไฟล์แนบกิจกรรม
-- 1 กิจกรรมสามารถแนบได้หลายไฟล์
-- =========================================================

CREATE TABLE IF NOT EXISTS activity_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    activity_id INT UNSIGNED NOT NULL,

    original_name VARCHAR(255) NOT NULL,

    stored_name VARCHAR(255) NOT NULL,

    file_path VARCHAR(500) NOT NULL,

    mime_type VARCHAR(200) DEFAULT NULL,

    file_extension VARCHAR(50) DEFAULT NULL,

    file_size BIGINT UNSIGNED DEFAULT NULL,

    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_files_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 7. ACTIVITY REGISTRATIONS
-- การสมัครเข้าร่วมกิจกรรม
-- =========================================================

CREATE TABLE IF NOT EXISTS activity_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    activity_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    registration_status ENUM(
        'registered',
        'cancelled'
    ) NOT NULL DEFAULT 'registered',

    cancel_reason VARCHAR(500) DEFAULT NULL,

    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    cancelled_at DATETIME DEFAULT NULL,

    CONSTRAINT fk_activity_registrations_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_activity_registrations_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    UNIQUE KEY unique_activity_registration (
        activity_id,
        user_id
    )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 8. ACTIVITY ATTENDANCE
-- บันทึกการมาเข้าร่วมกิจกรรม
-- =========================================================

CREATE TABLE IF NOT EXISTS activity_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    activity_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    attendance_status ENUM(
        'present',
        'absent',
        'late',
        'cancelled'
    ) NOT NULL DEFAULT 'present',

    check_in_time DATETIME DEFAULT NULL,

    check_out_time DATETIME DEFAULT NULL,

    note TEXT DEFAULT NULL,

    recorded_by INT UNSIGNED DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_attendance_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_activity_attendance_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_activity_attendance_admin
        FOREIGN KEY (recorded_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    UNIQUE KEY unique_activity_attendance (
        activity_id,
        user_id
    )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 9. PERSONNEL
-- บุคลากร / คณะกรรมการชมรม
-- ใช้แสดงในหน้า about.php
-- =========================================================

CREATE TABLE IF NOT EXISTS personnel (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    type ENUM(
        'advisor',
        'committee'
    ) NOT NULL DEFAULT 'committee',

    name VARCHAR(200) NOT NULL,

    position VARCHAR(200) NOT NULL,

    department VARCHAR(200) DEFAULT NULL,

    description TEXT DEFAULT NULL,

    phone VARCHAR(30) DEFAULT NULL,

    email VARCHAR(150) DEFAULT NULL,

    image VARCHAR(500) DEFAULT NULL,

    started_at DATE DEFAULT NULL,

    ended_at DATE DEFAULT NULL,

    show_phone TINYINT(1) NOT NULL DEFAULT 0,

    show_email TINYINT(1) NOT NULL DEFAULT 0,

    display_order INT NOT NULL DEFAULT 0,

    status ENUM(
        'active',
        'hidden',
        'former'
    ) NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_personnel_phone
        CHECK (phone IS NULL OR phone REGEXP '^\\+?[0-9]{9,15}$')

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- 10. SITE SETTINGS
-- ข้อมูลเว็บไซต์ / ติดต่อ / ค่าสมัคร
-- =========================================================

CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    setting_key VARCHAR(100) NOT NULL UNIQUE,

    setting_value TEXT DEFAULT NULL,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS privacy_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    policy_version VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_privacy_consents_user (user_id),
    CONSTRAINT fk_privacy_consents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 11. ADMIN AUDIT LOGS
-- ประวัติการเปลี่ยนแปลงโดยผู้ดูแลระบบ
-- =========================================================

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    old_values LONGTEXT DEFAULT NULL,
    new_values LONGTEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_admin (admin_user_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at),

    CONSTRAINT fk_admin_audit_user
        FOREIGN KEY (admin_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- ค่าเริ่มต้นเว็บไซต์
-- =========================================================

INSERT INTO site_settings (setting_key, setting_value)
VALUES
(
    'club_name',
    'Bizcomp Club'
),
(
    'club_name_th',
    'ชมรมบิตคอม'
),
(
    'faculty',
    'คณะการบัญชีและการจัดการ'
),
(
    'university',
    'มหาวิทยาลัยมหาสารคาม'
),
(
    'facebook',
    'https://www.facebook.com/profile.php?id=100094575348518'
),
(
    'tiktok',
    'https://www.tiktok.com/@bizcompclub'
),
(
    'phone',
    ''
),
(
    'email',
    ''
),
(
    'address',
    ''
),
(
    'map_embed',
    ''
),
(
    'membership_fee',
    '20.00'
)
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value);
