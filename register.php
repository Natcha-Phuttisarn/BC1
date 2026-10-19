<?php
require_once __DIR__ . '/includes/bootstrap.php';
// ขั้นตอนที่ 1: ข้อมูลสมาชิกและบัญชีผู้ใช้งาน

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/register_flow.php';

if (isset($_SESSION['user_id'])) {
    header('Location: member_status.php');
    exit;
}

$error = (string)($_SESSION['register_error'] ?? '');
$emailExists = (bool)($_SESSION['register_email_exists'] ?? false);
$preparedStep = $_SESSION['register_step1'] ?? [];
$hasPreparedStep = is_array($preparedStep) && validPreparedRegistration($preparedStep);

if (!$hasPreparedStep && !empty($preparedStep)) {
    unset($_SESSION['register_step1']);
    $preparedStep = [];
}

$old = $_SESSION['register_old'] ?? ($hasPreparedStep ? $preparedStep : []);
$canReusePassword =
    $hasPreparedStep &&
    hash_equals(
        (string)($preparedStep['email'] ?? ''),
        strtolower(trim((string)($old['email'] ?? '')))
    );
$csrfToken = registrationCsrfToken();

unset(
    $_SESSION['register_error'],
    $_SESSION['register_old'],
    $_SESSION['register_email_exists']
);

$pageTitle = 'สมัครสมาชิก';
$pageCss = 'register.css';
include __DIR__ . '/includes/header.php';
?>

<section class="register-page">
    <div class="register-container">
        <div class="register-page-heading">
            <span>BIZCOMP CLUB MEMBERSHIP</span>
            <h1>สมัครสมาชิก Bizcomp Club</h1>
            <p>กรอกข้อมูลสมาชิกและข้อมูลบัญชี ก่อนดำเนินการชำระค่าสมัคร</p>
        </div>

        <div class="register-steps">
            <a href="#member-data" class="register-step">
                <div>1</div>
                <span>ข้อมูลสมาชิกและบัญชี</span>
            </a>
            <div class="step-line"></div>
            <div class="register-step">
                <div>2</div>
                <span>ชำระค่าสมัครสมาชิก</span>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="register-alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <strong>ไม่สามารถดำเนินการต่อได้</strong>
                    <span><?= htmlspecialchars($error) ?></span>
                    <?php if ($emailExists): ?>
                        <a href="login.php">เข้าสู่ระบบด้วยบัญชีเดิม</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form action="register_step1_save.php" method="POST" class="register-form" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="register-section" id="member-data">
                <div class="register-section-header">
                    <div class="section-number">01</div>
                    <div>
                        <span>MEMBER INFORMATION</span>
                        <h2>ข้อมูลสมาชิก</h2>
                        <p>ข้อมูลสำหรับใช้เป็นสมาชิกของชมรม</p>
                    </div>
                </div>

                <div class="register-grid">
                    <div class="register-group">
                        <label>ชื่อ <span>*</span></label>
                        <input type="text" name="first_name"
                            value="<?= htmlspecialchars((string)($old['first_name'] ?? '')) ?>"
                            placeholder="กรอกชื่อ" autocomplete="given-name" maxlength="100" required>
                    </div>

                    <div class="register-group">
                        <label>นามสกุล <span>*</span></label>
                        <input type="text" name="last_name"
                            value="<?= htmlspecialchars((string)($old['last_name'] ?? '')) ?>"
                            placeholder="กรอกนามสกุล" autocomplete="family-name" maxlength="100" required>
                    </div>

                    <div class="register-group">
                        <label>รหัสนิสิต <span>*</span></label>
                        <input type="text" name="student_id"
                            value="<?= htmlspecialchars((string)($old['student_id'] ?? '')) ?>"
                            placeholder="เช่น 69xxxxxxxx" maxlength="30" required>
                        <small>ระบบจะใช้รหัสนิสิตในการคำนวณชั้นปีอัตโนมัติ</small>
                    </div>

                    <div class="register-group">
                        <label>เบอร์โทรศัพท์</label>
                        <input type="tel" name="phone"
                            value="<?= htmlspecialchars((string)($old['phone'] ?? '')) ?>"
                            placeholder="กรอกเบอร์โทรศัพท์" autocomplete="tel" maxlength="30">
                    </div>

                    <div class="register-group">
                        <label>คณะ <span>*</span></label>
                        <input type="text" name="faculty"
                            value="<?= htmlspecialchars((string)($old['faculty'] ?? 'คณะการบัญชีและการจัดการ')) ?>"
                            maxlength="200" required>
                    </div>

                    <div class="register-group">
                        <label>สาขา <span>*</span></label>
                        <input type="text" name="major"
                            value="<?= htmlspecialchars((string)($old['major'] ?? 'คอมพิวเตอร์ธุรกิจ')) ?>"
                            maxlength="200" required>
                    </div>

                    <div class="register-group full">
                        <label>เพศ</label>
                        <select name="gender">
                            <option value="">ไม่ระบุ</option>
                            <?php foreach (['ชาย', 'หญิง', 'อื่นๆ'] as $gender): ?>
                                <option value="<?= htmlspecialchars($gender) ?>"
                                    <?= ($old['gender'] ?? '') === $gender ? 'selected' : '' ?>>
                                    <?= $gender === 'อื่นๆ' ? 'อื่น ๆ' : htmlspecialchars($gender) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="register-section" id="account-data">
                <div class="register-section-header">
                    <div class="section-number">02</div>
                    <div>
                        <span>ACCOUNT INFORMATION</span>
                        <h2>ข้อมูลเข้าสู่ระบบ</h2>
                        <p>ใช้สำหรับเข้าสู่ระบบและตรวจสอบสถานะสมาชิก</p>
                    </div>
                </div>

                <div class="account-notice">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>เคยสมัครแล้ว?</strong>
                        <p>ระบบจะตรวจสอบอีเมลและรหัสนิสิตซ้ำก่อนเข้าสู่หน้าชำระเงิน</p>
                        <a href="login.php">เข้าสู่ระบบ</a>
                    </div>
                </div>

                <div class="register-grid">
                    <div class="register-group full">
                        <label>อีเมล <span>*</span></label>
                        <input type="email" name="email"
                            value="<?= htmlspecialchars((string)($old['email'] ?? '')) ?>"
                            placeholder="example@email.com" autocomplete="email" maxlength="150" required>
                    </div>

                    <div class="register-group">
                        <label>รหัสผ่าน <span>*</span></label>
                        <div class="password-box">
                            <input type="password" id="password" name="password"
                                placeholder="<?= $canReusePassword ? 'เว้นว่างเพื่อใช้รหัสผ่านเดิม' : 'อย่างน้อย 8 ตัวอักษร' ?>"
                                minlength="8" autocomplete="new-password" <?= $canReusePassword ? '' : 'required' ?>>
                            <button type="button" class="password-toggle" data-target="password"
                                aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <?php if ($canReusePassword): ?>
                            <small>ข้อมูลถูกบันทึกไว้แล้ว กรอกเฉพาะเมื่อต้องการเปลี่ยนรหัสผ่าน</small>
                        <?php endif; ?>
                    </div>

                    <div class="register-group">
                        <label>ยืนยันรหัสผ่าน <span>*</span></label>
                        <div class="password-box">
                            <input type="password" id="confirmPassword" name="confirm_password"
                                placeholder="<?= $canReusePassword ? 'เว้นว่างเพื่อใช้รหัสผ่านเดิม' : 'กรอกรหัสผ่านอีกครั้ง' ?>"
                                minlength="8" autocomplete="new-password" <?= $canReusePassword ? '' : 'required' ?>>
                            <button type="button" class="password-toggle" data-target="confirmPassword"
                                aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="register-actions">
                <a href="index.php" class="register-secondary-btn">ยกเลิก</a>
                <button type="submit" class="register-primary-btn" id="registerSubmit">
                    ดำเนินการชำระเงิน
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

            <div class="register-login-bottom">
                มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
            </div>
        </form>
    </div>
</section>

<script>
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');
        if (!input) return;

        input.type = input.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
});

const registerForm = document.getElementById('registerForm');
const registerSubmit = document.getElementById('registerSubmit');

if (registerForm && registerSubmit) {
    registerForm.addEventListener('submit', function () {
        registerSubmit.disabled = true;
        registerSubmit.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> กำลังตรวจสอบข้อมูล...';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
