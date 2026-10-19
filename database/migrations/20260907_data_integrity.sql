-- แก้ข้อมูลเดิมและเพิ่มข้อกำหนดป้องกันข้อมูลผิดรูปแบบ

UPDATE users
SET role = 'user'
WHERE role IS NULL OR role NOT IN ('user', 'admin');

ALTER TABLE users
    ADD CONSTRAINT chk_users_role
        CHECK (role IN ('user', 'admin'));

ALTER TABLE members
    ADD CONSTRAINT chk_members_phone
        CHECK (phone IS NULL OR phone REGEXP '^\\+?[0-9]{9,15}$');

ALTER TABLE membership_applications
    ADD CONSTRAINT chk_membership_applications_phone
        CHECK (phone IS NULL OR phone REGEXP '^\\+?[0-9]{9,15}$');

ALTER TABLE personnel
    ADD CONSTRAINT chk_personnel_phone
        CHECK (phone IS NULL OR phone REGEXP '^\\+?[0-9]{9,15}$');
