<?php
require_once __DIR__ . '/includes/bootstrap.php';
// ขั้นตอนที่ 2: ชำระค่าสมัครสมาชิก

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

$step = $_SESSION['register_step1'] ?? [];
if (!is_array($step) || !validPreparedRegistration($step)) {
    unset($_SESSION['register_step1'], $_SESSION['register_payment_old']);
    $_SESSION['register_error'] =
        'ข้อมูลขั้นตอนที่ 1 ไม่มีอยู่หรือหมดอายุ กรุณากรอกข้อมูลใหม่อีกครั้ง';
    header('Location: register.php');
    exit;
}

$membershipFee = 20.00;
$payment = [
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'promptpay_number' => '',
];

try {
    $stmt = $pdo->query("
        SELECT setting_key, setting_value
        FROM site_settings
        WHERE setting_key IN (
            'bank_name',
            'bank_account_name',
            'bank_account_number',
            'promptpay_number'
        )
    ");

    foreach ($stmt->fetchAll() as $row) {
        if (array_key_exists($row['setting_key'], $payment)) {
            $payment[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Throwable $e) {
    // คงค่าว่างเมื่อยังไม่มีการตั้งค่ารับชำระเงิน
}

$qrPath = 'assets/images/payment-qr.png';
$qrExists = is_file(__DIR__ . '/' . $qrPath);
$error = (string)($_SESSION['register_payment_error'] ?? '');
$old = $_SESSION['register_payment_old'] ?? [];
$csrfToken = registrationCsrfToken();

unset($_SESSION['register_payment_error']);

$pageTitle = 'ชำระค่าสมัครสมาชิก';
$pageCss = 'register.css';
include __DIR__ . '/includes/header.php';
?>

<section class="register-page">
    <div class="register-container">
        <div class="register-page-heading">
            <span>BIZCOMP CLUB MEMBERSHIP</span>
            <h1>ชำระค่าสมัครสมาชิก</h1>
            <p>ตรวจสอบข้อมูล ชำระค่าสมัคร และแนบหลักฐานเพื่อส่งคำขอ</p>
        </div>

        <div class="register-steps">
            <a href="register.php" class="register-step">
                <div>1</div>
                <span>ข้อมูลสมาชิกและบัญชี</span>
            </a>
            <div class="step-line"></div>
            <a href="#payment-data" class="register-step">
                <div>2</div>
                <span>ชำระค่าสมัครสมาชิก</span>
            </a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="register-alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <strong>ไม่สามารถยืนยันการสมัครได้</strong>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="account-notice">
            <i class="fa-solid fa-user-check"></i>
            <div>
                <strong>
                    <?= htmlspecialchars(trim($step['first_name'] . ' ' . $step['last_name'])) ?>
                </strong>
                <p>
                    รหัสนิสิต <?= htmlspecialchars($step['student_id']) ?>
                    · <?= htmlspecialchars($step['email']) ?>
                </p>
                <a href="register.php">ย้อนกลับไปแก้ไขข้อมูล</a>
            </div>
        </div>

        <form action="register_payment_save.php" method="POST" enctype="multipart/form-data"
            class="register-form" id="registerPaymentForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="submission_token"
                value="<?= htmlspecialchars($step['submission_token']) ?>">

            <div class="register-section" id="payment-data">
                <div class="register-section-header">
                    <div class="section-number">02</div>
                    <div>
                        <span>MEMBERSHIP PAYMENT</span>
                        <h2>ชำระค่าสมัครสมาชิก</h2>
                        <p>ชำระเงินและแนบหลักฐานก่อนส่งคำขอ</p>
                    </div>
                </div>

                <div class="membership-price">
                    <span>ค่าสมัครสมาชิก</span>
                    <strong><?= number_format($membershipFee, 2) ?> <small>บาท</small></strong>
                    <p>ค่าสมัครสมาชิกตามที่ชมรมกำหนด</p>
                </div>

                <div class="payment-layout"<?= !$qrExists ? ' style="grid-template-columns: 1fr;"' : '' ?>>
                    <?php if ($qrExists): ?>
                        <div class="payment-qr-card">
                            <span class="payment-label">QR PAYMENT</span>
                            <h3>สแกนเพื่อชำระเงิน</h3>
                            <div class="payment-qr">
                                <img src="<?= htmlspecialchars($qrPath) ?>" alt="QR Code ชำระค่าสมาชิก">
                            </div>
                            <?php if ($payment['promptpay_number'] !== ''): ?>
                                <p>
                                    พร้อมเพย์:
                                    <strong><?= htmlspecialchars($payment['promptpay_number']) ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="payment-account-card">
                        <span class="payment-label">BANK ACCOUNT</span>
                        <h3>ข้อมูลบัญชีรับชำระ</h3>

                        <div class="payment-account-row">
                            <span>ธนาคาร</span>
                            <strong><?= htmlspecialchars($payment['bank_name'] ?: '-') ?></strong>
                        </div>
                        <div class="payment-account-row">
                            <span>ชื่อบัญชี</span>
                            <strong><?= htmlspecialchars($payment['bank_account_name'] ?: '-') ?></strong>
                        </div>
                        <div class="payment-account-row">
                            <span>เลขบัญชี</span>
                            <strong><?= htmlspecialchars($payment['bank_account_number'] ?: '-') ?></strong>
                        </div>

                        <div class="register-group payment-method">
                            <label>ชำระเงินผ่าน <span>*</span></label>
                            <select name="payment_method" required>
                                <option value="">เลือกช่องทางที่ใช้</option>
                                <option value="promptpay"
                                    <?= ($old['payment_method'] ?? '') === 'promptpay' ? 'selected' : '' ?>>
                                    QR / พร้อมเพย์
                                </option>
                                <option value="bank_transfer"
                                    <?= ($old['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>
                                    โอนเข้าบัญชีธนาคาร
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="slip-section">
                    <div class="slip-heading">
                        <div>
                            <span>PAYMENT EVIDENCE</span>
                            <h3>หลักฐานการชำระเงิน <b>*</b></h3>
                        </div>
                        <strong><?= number_format($membershipFee, 2) ?> บาท</strong>
                    </div>

                    <label class="slip-upload" id="slipUpload">
                        <input type="file" name="payment_slip" id="paymentSlip"
                            accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                        <div class="slip-placeholder" id="slipPlaceholder">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <strong>เลือกหลักฐานการชำระเงิน</strong>
                            <span>JPG, PNG, WEBP หรือ PDF ขนาดไม่เกิน 5 MB</span>
                        </div>
                        <div class="slip-preview" id="slipPreview"></div>
                    </label>
                </div>

                <label class="register-confirm">
                    <input type="checkbox" name="confirm_information" value="1"
                        <?= !empty($old['confirm_information']) ? 'checked' : '' ?> required>
                    <span>
                        ข้าพเจ้าตรวจสอบข้อมูล และยืนยันว่าได้ชำระค่าสมัครสมาชิก
                        จำนวน <?= number_format($membershipFee, 2) ?> บาทเรียบร้อยแล้ว
                    </span>
                </label>

                <label class="register-confirm">
                    <input type="checkbox" name="privacy_consent" value="1"
                        <?= !empty($old['privacy_consent']) ? 'checked' : '' ?> required>
                    <span>
                        ข้าพเจ้ายอมรับ
                        <a href="privacy.php" target="_blank" rel="noopener">นโยบายความเป็นส่วนตัว</a>
                        และเงื่อนไขการใช้ข้อมูลส่วนบุคคล
                    </span>
                </label>
            </div>

            <div class="register-actions">
                <a href="register.php" class="register-secondary-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    ย้อนกลับ
                </a>
                <button type="submit" class="register-primary-btn" id="registerPaymentSubmit">
                    <i class="fa-solid fa-user-check"></i>
                    ยืนยันการสมัครสมาชิก
                </button>
            </div>
        </form>
    </div>
</section>

<script>
const paymentSlip = document.getElementById('paymentSlip');
const slipPreview = document.getElementById('slipPreview');
const slipPlaceholder = document.getElementById('slipPlaceholder');

if (paymentSlip) {
    paymentSlip.addEventListener('change', function () {
        const file = this.files[0];
        slipPreview.replaceChildren();

        if (!file) {
            slipPlaceholder.style.display = 'flex';
            return;
        }

        slipPlaceholder.style.display = 'none';

        if (file.type === 'application/pdf') {
            const wrapper = document.createElement('div');
            wrapper.className = 'slip-file-preview';
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-file-pdf';
            const name = document.createElement('strong');
            name.textContent = file.name;
            const status = document.createElement('span');
            status.textContent = 'PDF พร้อมอัปโหลด';
            wrapper.append(icon, name, status);
            slipPreview.append(wrapper);
            return;
        }

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (event) {
                const image = document.createElement('img');
                image.src = event.target.result;
                image.alt = 'ตัวอย่างหลักฐานการชำระเงิน';
                const name = document.createElement('span');
                name.textContent = file.name;
                slipPreview.replaceChildren(image, name);
            };
            reader.readAsDataURL(file);
        }
    });
}

const paymentForm = document.getElementById('registerPaymentForm');
const paymentSubmit = document.getElementById('registerPaymentSubmit');

if (paymentForm && paymentSubmit) {
    paymentForm.addEventListener('submit', function () {
        paymentSubmit.disabled = true;
        paymentSubmit.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> กำลังส่งคำขอ...';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
