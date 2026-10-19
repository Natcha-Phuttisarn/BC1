-- 20261004_activity_gallery_images.sql
-- ปรับชื่อคอลัมน์รูปปกและเพิ่มตารางรูปภาพเพิ่มเติมสำหรับ Gallery

SET @gallery_has_image = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_gallery'
      AND COLUMN_NAME = 'image'
);

SET @gallery_has_cover = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_gallery'
      AND COLUMN_NAME = 'cover_image'
);

SET @gallery_cover_sql = IF(
    @gallery_has_image = 1 AND @gallery_has_cover = 0,
    'ALTER TABLE activity_gallery CHANGE COLUMN image cover_image VARCHAR(255) NOT NULL',
    IF(
        @gallery_has_cover = 0,
        'ALTER TABLE activity_gallery ADD COLUMN cover_image VARCHAR(255) NOT NULL AFTER description',
        'SELECT 1'
    )
);

PREPARE gallery_cover_statement FROM @gallery_cover_sql;
EXECUTE gallery_cover_statement;
DEALLOCATE PREPARE gallery_cover_statement;

SET @gallery_has_display_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_gallery'
      AND INDEX_NAME = 'idx_gallery_display'
);

SET @gallery_drop_index_sql = IF(
    @gallery_has_display_index > 0,
    'ALTER TABLE activity_gallery DROP INDEX idx_gallery_display',
    'SELECT 1'
);

PREPARE gallery_drop_index_statement FROM @gallery_drop_index_sql;
EXECUTE gallery_drop_index_statement;
DEALLOCATE PREPARE gallery_drop_index_statement;

ALTER TABLE activity_gallery
    ADD INDEX idx_gallery_display (status, created_at, id);

CREATE TABLE IF NOT EXISTS activity_gallery_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    image VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_gallery_images_gallery (gallery_id, id),

    CONSTRAINT fk_gallery_images_gallery
        FOREIGN KEY (gallery_id)
        REFERENCES activity_gallery(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
