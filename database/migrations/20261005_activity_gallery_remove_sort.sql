-- 20261005_activity_gallery_remove_sort.sql
-- ยกเลิกคอลัมน์ลำดับการแสดงผลของ Gallery โดยไม่แก้ตารางระบบอื่น

SET @gallery_has_sort_order = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_gallery'
      AND COLUMN_NAME = 'sort_order'
);

SET @gallery_remove_sort_sql = IF(
    @gallery_has_sort_order > 0,
    'ALTER TABLE activity_gallery DROP COLUMN sort_order',
    'SELECT 1'
);

PREPARE gallery_remove_sort_statement FROM @gallery_remove_sort_sql;
EXECUTE gallery_remove_sort_statement;
DEALLOCATE PREPARE gallery_remove_sort_statement;
