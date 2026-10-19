# ผลตรวจเตรียมขึ้นโฮสต์ — 2026-10-20

## แก้ไขแล้ว

- ยกเลิกพาธ /BC1 ที่ฝังในลิงก์และ redirect ใช้ base path กลาง รองรับโดเมนหลักและโฟลเดอร์ย่อย
- DB รองรับ hostname/port ของโฮสต์ และ config.local.php หรือ environment variables
- ตั้ง timezone PHP และ MySQL เป็น Asia/Bangkok/+07:00; ตั้ง session cookies เป็น HttpOnly, SameSite=Lax และ Secure สำหรับ HTTPS
- รวม OTP 2 ตารางลง hosting_schema.sql รวมเป็น 20 ตาราง
- SMTP เลือก STARTTLS หรือ SMTPS ได้ และใช้ config กลางพร้อม OTP secret
- ปิด directory listing และ HTTP access ไปยัง source/config/database/backups/tools และหลักฐานส่วนตัว
- ไฟล์แนบกิจกรรมดาวน์โหลดผ่าน controller เท่านั้น ตรวจสถานะเผยแพร่ สิทธิ์ admin และขอบเขต filesystem
- ระบุ PHP/extensions ใน Composer และสร้าง optimized autoloader
- เพิ่มเครื่องมือตรวจโฮสต์และสร้าง admin แรกทาง CLI

## หลักฐานการทดสอบ

- PHP lint: ผ่าน 183 ไฟล์ รวม vendor แต่ไม่รวม .backups
- `php tests/hosting.php`: ผ่าน 139 checks บน PHP 8.0.30 และ MariaDB 10.4.32
- ทดสอบฐานข้อมูลใหม่ชั่วคราว แล้วลบฐานข้อมูล/ไฟล์อัปโหลดทดสอบหลังจบ ไม่เปลี่ยนข้อมูลฐานข้อมูลเดิม
- ครอบคลุมหน้าสาธารณะ หน้าหลัก admin การ login สมัครสองขั้นพร้อมสลิป อนุมัติ/ใบเสร็จ สิทธิ์อ่านสลิป กิจกรรม/CSRF เช็กชื่อ อัปโหลดและแสดงแกลเลอรี รายรับ backup และ OTP
- OTP ทดสอบด้วย transport จำลอง: ออก/ยืนยันรหัส cooldown ตั้งรหัสผ่านใหม่ token ใช้ซ้ำไม่ได้ และ login ด้วยรหัสผ่านใหม่
- ทดสอบ HTTP ทั้งติดตั้งที่ root และ /BC1
- ตรวจไม่พบ PHP warning/fatal/deprecation/notice ใน log ของ integration server
- Apache จริงปฏิเสธ database, includes, .backups, composer.lock, uploads/payments, uploads/activity_files, tools และ .user.ini ด้วย 403; รูปโลโก้ตอบ 200
- Composer check-platform-reqs ผ่าน; audit ไม่พบประกาศช่องโหว่ของแพ็กเกจที่ล็อก ณ เวลาตรวจ
- Composer validate ผ่าน มี warning ว่าโปรเจกต์ยังไม่ระบุ license ซึ่งควรให้เจ้าของโปรเจกต์เป็นผู้กำหนด

## ยังไม่ใช่การยืนยัน production

ยังไม่ได้เชื่อมต่อหรือติดตั้งบนโฮสต์จริง ไม่มีข้อมูล DB/SMTP ของโฮสต์และยังไม่ทราบชนิดเว็บเซิร์ฟเวอร์
เว็บ local เดิมตอบ 503 เนื่องจากไม่มี BC_DB configuration; ไม่ได้สร้างค่าฐานข้อมูลจริงให้โดยคาดเดา
ต้องกำหนด config ตาม includes/config.example.php แล้วทำขั้นตอน database/HOSTING_SETUP.md

ยังไม่ได้ทดสอบส่งอีเมลจริง, HTTPS/CDN, rendering ใน browser/มือถือ, โหลดพร้อมกัน, provider-specific limits หรือทุกชุดข้อมูล/ทุกกรณีผิดพลาด
ชุดทดสอบมีทั้ง HTTP status checks และ assertions ของผลในฐานข้อมูล จึงไม่ใช่ 139 workflow แยกกัน
ไม่รับรองว่าไม่มีบั๊กเหลือทั้งหมด ต้องทำ acceptance test บน staging ของโฮสต์จริงก่อนเปิดให้ผู้ใช้

## ไฟล์ที่ใช้ต่อ

- `database/HOSTING_SETUP.md`: ขั้นตอน deploy, migration, Apache/Nginx และ acceptance checks
- `includes/config.example.php`: แม่แบบตั้งค่า ห้ามคัดลอก placeholder ไปใช้งานจริงโดยไม่แก้
- `tools/preflight.php`: ตรวจ PHP/extensions/config/ตาราง/uploads/admin โดยไม่เขียนข้อมูล
- `tools/create_admin.php`: สร้าง admin แรกทาง CLI
- `tests/hosting.php`: integration checks บนฐานข้อมูลชั่วคราว
