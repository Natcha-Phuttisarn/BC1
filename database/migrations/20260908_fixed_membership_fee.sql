-- ค่าสมัครสมาชิกของ Bizcomp Club กำหนดตายตัว 20 บาท
INSERT INTO site_settings (setting_key, setting_value)
VALUES ('membership_fee', '20.00')
ON DUPLICATE KEY UPDATE setting_value = '20.00';

-- รายการที่ยังไม่ตรวจสอบเป็นคำขอเดิมในระบบทดสอบ ให้ใช้ยอดปัจจุบันเดียวกัน
UPDATE membership_payments
SET amount = 20.00
WHERE payment_status = 'pending' AND amount <> 20.00;
