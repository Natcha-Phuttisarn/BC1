-- เพิ่มการจัดการสถานะบุคลากรรายบุคคลโดยไม่บังคับกำหนดวาระ
-- ใช้กับฐานข้อมูลเดิมที่มีตาราง personnel อยู่แล้ว

ALTER TABLE personnel
    MODIFY COLUMN status ENUM('active', 'hidden', 'former')
        NOT NULL DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS started_at DATE NULL AFTER image,
    ADD COLUMN IF NOT EXISTS ended_at DATE NULL AFTER started_at,
    ADD COLUMN IF NOT EXISTS show_phone TINYINT(1) NOT NULL DEFAULT 0 AFTER ended_at,
    ADD COLUMN IF NOT EXISTS show_email TINYINT(1) NOT NULL DEFAULT 0 AFTER show_phone;
