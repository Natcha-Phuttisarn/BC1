# ติดตั้ง BC1 บนโฮสต์

## ความต้องการ

- PHP 8.0+ ตามข้อกำหนดโค้ด เลือกรุ่นที่ผู้ให้บริการยังดูแลความปลอดภัยสำหรับ production
- Extensions: pdo_mysql, mbstring, fileinfo, openssl, session
- MariaDB 10.4+ หรือ MySQL 8.0.16+ รองรับ InnoDB และ utf8mb4
- HTTPS, ฐานข้อมูลและบัญชี DB, SMTP ที่ส่งอีเมลได้จริง
- Apache/LiteSpeed ต้องอนุญาต `.htaccess` (mod_rewrite, mod_authz_core และ Options); mod_headers ใช้เพิ่ม HTTP security headers

## จัดไฟล์

วางเว็บที่ document root หรือโฟลเดอร์ย่อย เช่น `/BC1` ได้ ระบบตรวจ base path อัตโนมัติ หรือกำหนด `BC_BASE_PATH` เอง
ไม่อัปโหลด `.backups`, `.git`, `tests`, log หรือ `database/BC1.sql` ซึ่งอาจมีข้อมูลจริง ไม่แจก config ที่มีรหัสผ่าน
คัดลอก `.htaccess` ทุกระดับด้วย รวมถึงกฎภายใน uploads
โฟลเดอร์ uploads/profiles, payments, finance, gallery, activity_files, activities/covers ต้องเขียนได้โดย PHP ใช้สิทธิ์ตามผู้ให้บริการ ไม่ใช้ 777

หากโฮสต์ไม่มี Composer ให้นำ vendor ที่ติดตั้งแล้วขึ้นไปด้วย หากมี terminal ให้รัน:

```sh
composer install --no-dev --optimize-autoloader
composer check-platform-reqs
```

ตั้ง upload_max_filesize อย่างน้อย 10M, post_max_size 64M และ max_file_uploads 20 ผ่าน control panel
ไฟล์ `.user.ini` ใช้กับ PHP-FPM/CGI; Apache mod_php อาจต้องตั้งผ่าน control panel/php.ini
ตรวจ session.save_path และ upload_tmp_dir ว่าเขียนได้โดยผู้ใช้ที่รัน PHP

## ฐานข้อมูล

**ติดตั้งใหม่:** สร้างฐานข้อมูลว่าง เลือกฐานข้อมูลนั้น แล้ว import `database/hosting_schema.sql` เพียงครั้งเดียว ได้ 20 ตาราง รวม OTP 2 ตาราง ไม่มีบัญชีและข้อมูลส่วนบุคคล
ห้าม import migration เก่าซ้ำหลังใช้ schema นี้ และห้ามนำ schema นี้ทับฐานข้อมูลที่มีข้อมูล

**ย้ายระบบเดิมพร้อมข้อมูล:** สำรองฐานข้อมูลและ uploads แล้วนำเข้าฐานข้อมูลใหม่ ตรวจ schema และ migration ที่ยังขาดก่อนใช้ ไม่ใช้ hosting_schema.sql เป็นเครื่องมือ upgrade
ถ้ายังไม่มี password_reset_otp_challenges และ password_reset_otp_limits ให้ทำ preflight ตาม `migrations/20261018_password_reset_otp.sql` ก่อนเพิ่มเฉพาะสองตารางนี้
การทดสอบใน workspace ไม่ได้แก้ข้อมูลฐานข้อมูลเดิม

```sql
SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();
SHOW CREATE TABLE password_reset_otp_challenges;
SHOW CREATE TABLE password_reset_otp_limits;
```

## กำหนดค่าจริง

คัดลอก `includes/config.example.php` เป็น `includes/config.local.php` บนโฮสต์แล้วใส่ค่าจริง หรือใช้ environment variables ซึ่งมีลำดับความสำคัญสูงกว่าไฟล์

- BC_DB_HOST, BC_DB_PORT, BC_DB_NAME, BC_DB_USERNAME, BC_DB_PASSWORD ตามโฮสต์
- BC_BASE_PATH: ค่าว่างเมื่อใช้โดเมนหลัก, `/BC1` เมื่อใช้โฟลเดอร์ BC1 หรือลบ key เพื่อใช้ auto detection
- BC_SECURE_COOKIES: `1` สำหรับ HTTPS; `0` เฉพาะพัฒนาใน HTTP
- BC_MAIL_HOST, BC_MAIL_PORT, BC_MAIL_USERNAME, BC_MAIL_PASSWORD, BC_MAIL_FROM, BC_MAIL_FROM_NAME
- BC_MAIL_ENCRYPTION: `tls` คู่ port 587 หรือ `ssl` คู่ port 465 ตาม SMTP provider
- BC_OTP_HMAC_SECRET: สุ่มใหม่อย่างน้อย 64 ตัวอักษร เก็บให้คงที่และเป็นความลับ สร้างด้วย `php -r "echo bin2hex(random_bytes(32));"`

เปิด force HTTPS ที่โฮสต์/CDN ก่อนใช้งานจริง หากอยู่หลัง reverse proxy ให้ตั้ง BC_SECURE_COOKIES=1 และ BC_BASE_PATH ให้ตรง
เว็บไซต์ตอบ 503 เมื่อ DB config ไม่ครบหรือเชื่อมต่อไม่ได้ ตรวจ PHP error log โดยไม่เปิด display_errors ให้ผู้ใช้เห็น

## Admin แรก

Schema ไม่มีรหัสผ่านเริ่มต้น สร้าง admin แรกผ่าน hosting terminal:

```sh
php tools/create_admin.php your-admin@example.com
php tools/preflight.php
```

คำสั่งสร้าง admin รับรหัสผ่านจาก stdin ซึ่ง terminal อาจแสดงตัวอักษรที่พิมพ์ ใช้ terminal ส่วนตัว ไม่ใส่รหัสผ่านใน command arguments
เครื่องมือไม่สร้างเพิ่มหากมี admin อยู่แล้ว ถ้าไม่มี terminal ให้สร้าง hash บนเครื่องที่เชื่อถือได้และเพิ่มบัญชีผ่านช่องทางผู้ดูแล DB ของโฮสต์
หลัง login ตั้งค่าชมรม ค่าสมัคร บัญชีรับเงิน QR และข้อมูลติดต่อผ่านหน้า Settings

## ตรวจผ่านเว็บจริงก่อนเปิดใช้

1. เปิดหน้าแรก รูป CSS และเมนูสมาชิก/admin ตรวจพาธบนตำแหน่งจริง
2. เปิด database/hosting_schema.sql, includes/config.local.php, composer.lock, uploads/payments/ โดยตรง ต้องได้ 403/404
3. สมัครบัญชีทดสอบ แนบสลิป อนุมัติ พิมพ์ใบเสร็จ และตรวจสิทธิ์เปิดสลิป
4. สร้างกิจกรรม สมัคร เช็กชื่อ อัปโหลดรูปแกลเลอรี และบันทึกรายรับ/รายจ่าย
5. ขอ OTP ตรวจว่าอีเมลถึงจริง ใส่รหัส ตั้งรหัสผ่านใหม่ แล้ว login ใหม่
6. ตรวจ HTTPS/cookies, timezone Asia/Bangkok, error log, ดาวน์โหลด backup และทดลอง restore บนฐานข้อมูลสำรอง

SMTP จำลองในชุดทดสอบไม่ยืนยันการส่งอีเมลของโฮสต์ ตรวจการส่งเข้ากล่องจดหมายและ SPF/DKIM กับผู้ให้บริการด้วย

## Nginx

Nginx ไม่อ่าน .htaccess ต้องตั้งกฎเทียบเท่าก่อนเปิดเว็บ ตัวอย่างสำหรับโดเมนหลัก (เปลี่ยน prefix ทุกกฎเมื่อใช้โฟลเดอร์ย่อย):

```nginx
location ~ ^/(includes|database|vendor|tests|tools|\.backups)(/|$) { deny all; }
location ~ /\. { deny all; }
location ~* (\.(sql|log|bak|ini|md|lock|env|zip|tar|gz)$|/composer\.json$) { deny all; }
location ^~ /uploads/payments/ { deny all; }
location ^~ /uploads/finance/ { deny all; }
location ^~ /uploads/activity_files/ { deny all; }
location ~* ^/uploads/.*\.(php[0-9]*|phtml|pht|phar|cgi|pl|py|sh|shtml)(\.|$) { deny all; }
autoindex off;
client_max_body_size 64m;
add_header X-Content-Type-Options nosniff always;
add_header X-Frame-Options SAMEORIGIN always;
add_header Referrer-Policy strict-origin-when-cross-origin always;
```

ให้ผู้ดูแลโฮสต์รวมกฎก่อน regex PHP handler และตรวจ configuration ทั้งชุดตาม PHP-FPM ตัวอย่างนี้ไม่ใช่ server block สมบูรณ์

## ทดสอบซ้ำในเครื่องพัฒนา

```sh
php tests/hosting.php
```

ชุดทดสอบสร้างฐานข้อมูลชื่อสุ่ม bc1_test_* เองแล้วลบทิ้ง ไม่ใช้ BC_DB_NAME ตั้ง BC_TEST_DB_HOST, BC_TEST_DB_PORT, BC_TEST_DB_USER, BC_TEST_DB_PASSWORD หากไม่ได้ใช้ local root
บัญชีทดสอบต้องมีสิทธิ์สร้าง/ลบฐานข้อมูล ใช้เครื่องพัฒนาหรือ staging เท่านั้น ไม่รันบน production
