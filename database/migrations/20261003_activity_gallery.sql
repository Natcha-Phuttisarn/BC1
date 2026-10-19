-- 20261003_activity_gallery.sql
-- เพิ่มตารางเก็บภาพกิจกรรมที่ผ่านมา โดยไม่แก้ไขหรือลบข้อมูลเดิม

CREATE TABLE IF NOT EXISTS activity_gallery (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) NOT NULL,
    activity_date DATE DEFAULT NULL,
    activity_id INT UNSIGNED DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_gallery_display (status, created_at, id),
    INDEX idx_gallery_activity (activity_id),

    CONSTRAINT fk_activity_gallery_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
