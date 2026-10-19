-- 20260916_club_display_name.sql
-- อัปเดตเฉพาะชื่อภาษาไทยที่แสดงต่อผู้ใช้ โดยไม่เปลี่ยนชื่อภายในระบบ

UPDATE site_settings
SET setting_value = 'ชมรมบิตคอม'
WHERE setting_key = 'club_name_th';
