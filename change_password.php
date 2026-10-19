<?php
require_once __DIR__ . '/includes/bootstrap.php';
// change_password.php
// หน้านี้ใช้สำหรับเปลี่ยนรหัสผ่านของบัญชีผู้ใช้งานที่เข้าสู่ระบบแล้ว

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';


// =========================================
// ต้อง Login ก่อน
// =========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];


// =========================================
// CSRF
// =========================================

if (empty($_SESSION['change_password_csrf'])) {
    $_SESSION['change_password_csrf'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['change_password_csrf'];


// =========================================
// MESSAGE
// =========================================

$error = '';
$success = '';


// =========================================
// CHANGE PASSWORD
// =========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token =
        $_POST['csrf_token'] ?? '';

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    // =====================================
    // CSRF
    // =====================================

    if (
        !hash_equals(
            $_SESSION['change_password_csrf'] ?? '',
            $token
        )
    ) {

        $error =
            'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';

    }


    // =====================================
    // EMPTY
    // =====================================

    elseif (
        $currentPassword === ''
        ||
        $newPassword === ''
        ||
        $confirmPassword === ''
    ) {

        $error =
            'กรุณากรอกรหัสผ่านให้ครบทุกช่อง';

    }


    // =====================================
    // PASSWORD LENGTH
    // =====================================

    elseif (strlen($newPassword) < 8) {

        $error =
            'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';

    }


    // =====================================
    // CONFIRM
    // =====================================

    elseif ($newPassword !== $confirmPassword) {

        $error =
            'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';

    }


    else {

        try {

            // =================================
            // ดึง Password ปัจจุบัน
            // =================================

            $stmt = $pdo->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $userId
            ]);

            $user =
                $stmt->fetch();


            if (!$user) {

                session_destroy();

                header(
                    'Location: login.php'
                );

                exit;
            }


            // =================================
            // ตรวจ Password เดิม
            // =================================

            if (
                !password_verify(
                    $currentPassword,
                    $user['password']
                )
            ) {

                $error =
                    'รหัสผ่านปัจจุบันไม่ถูกต้อง';

            }


            // =================================
            // ห้ามใช้ Password เดิม
            // =================================

            elseif (
                password_verify(
                    $newPassword,
                    $user['password']
                )
            ) {

                $error =
                    'รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเดิม';

            }


            else {

                // =================================
                // HASH PASSWORD ใหม่
                // =================================

                $newHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );


                if ($newHash === false) {

                    throw new RuntimeException(
                        'ไม่สามารถเข้ารหัสรหัสผ่านได้'
                    );
                }


                // =================================
                // UPDATE
                // =================================

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $newHash,
                    $userId
                ]);


                // =================================
                // เปลี่ยน Session ID
                // =================================

                session_regenerate_id(true);


                // สร้าง Token ใหม่
                $_SESSION['change_password_csrf'] =
                    bin2hex(
                        random_bytes(32)
                    );

                $csrfToken =
                    $_SESSION['change_password_csrf'];


                $success =
                    'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';

            }

        } catch (Throwable $e) {

            $error =
                'ไม่สามารถเปลี่ยนรหัสผ่านได้ กรุณาลองใหม่อีกครั้ง';

        }
    }
}


$pageTitle = 'เปลี่ยนรหัสผ่าน';

include 'includes/header.php';
?>


<style>

/* =========================================
   CHANGE PASSWORD
========================================= */

.security-page{
    min-height:calc(100vh - 170px);
    padding:70px 20px;

    background:#f5f8fc;
}

.security-container{
    width:100%;
    max-width:560px;

    margin:0 auto;
}

.security-card{
    padding:32px;

    border:1px solid #e1e8ef;
    border-radius:16px;

    background:#fff;

    box-shadow:
        0 10px 35px rgba(25,60,100,.06);
}


/* =========================================
   HEADING
========================================= */

.security-heading{
    margin-bottom:25px;

    text-align:center;
}

.security-icon{
    width:58px;
    height:58px;

    margin:0 auto 14px;

    border-radius:50%;

    background:#eaf3ff;
    color:#1768cf;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:22px;
}

.security-heading span{
    color:#1768cf;

    font-size:9px;
    font-weight:600;

    letter-spacing:1.5px;
}

.security-heading h1{
    margin:5px 0;

    color:#294e72;

    font-size:24px;
    font-weight:600;
}

.security-heading p{
    margin:0;

    color:#8796a5;

    font-size:10px;
}


/* =========================================
   ALERT
========================================= */

.security-alert{
    margin-bottom:18px;
    padding:13px 15px;

    border-radius:9px;

    display:flex;
    align-items:center;
    gap:9px;

    font-size:10px;
}

.security-alert.error{
    color:#a64242;
    background:#fff1f1;
    border:1px solid #efcece;
}

.security-alert.success{
    color:#26734c;
    background:#edf9f2;
    border:1px solid #cbe9d8;
}


/* =========================================
   FORM
========================================= */

.security-group{
    margin-bottom:17px;
}

.security-group label{
    margin-bottom:7px;

    color:#47627d;

    display:block;

    font-size:10px;
    font-weight:500;
}

.password-input{
    position:relative;
}

.password-input input{
    width:100%;
    height:46px;

    padding:0 45px 0 13px;

    border:1px solid #d9e2ec;
    border-radius:9px;

    background:#fff;
    color:#405c76;

    font-family:"Prompt",sans-serif;
    font-size:10px;

    outline:none;

    box-sizing:border-box;
}

.password-input input:focus{
    border-color:#1768cf;

    box-shadow:
        0 0 0 3px rgba(23,104,207,.07);
}

.password-toggle{
    position:absolute;

    top:50%;
    right:10px;

    width:32px;
    height:32px;

    border:0;

    background:transparent;
    color:#8899aa;

    cursor:pointer;

    transform:translateY(-50%);
}

.security-help{
    margin-top:5px;

    color:#95a3b0;

    font-size:8px;
}


/* =========================================
   BUTTON
========================================= */

.security-actions{
    margin-top:25px;

    display:flex;
    gap:10px;
}

.security-back,
.security-submit{
    min-height:44px;

    border-radius:9px;

    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;

    font-family:"Prompt",sans-serif;
    font-size:10px;
    font-weight:500;

    text-decoration:none;
}

.security-back{
    padding:0 17px;

    border:1px solid #d9e1e9;

    background:#fff;
    color:#627a91;
}

.security-submit{
    flex:1;

    border:0;

    background:#1768cf;
    color:#fff;

    cursor:pointer;
}

.security-submit:hover{
    background:#105bb2;
}


/* =========================================
   NOTE
========================================= */

.security-note{
    margin-top:22px;
    padding:13px;

    border-radius:9px;

    background:#f6f9fc;

    color:#8796a5;

    font-size:8px;
    line-height:1.7;
}

.security-note i{
    margin-right:5px;

    color:#1768cf;
}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:600px){

    .security-card{
        padding:23px 18px;
    }

    .security-actions{
        flex-direction:column-reverse;
    }

    .security-back{
        width:100%;
        box-sizing:border-box;
    }

}

</style>


<section class="security-page">

    <div class="security-container">

        <div class="security-card">


            <!-- =================================
                 HEADING
            ================================== -->

            <div class="security-heading">

                <div class="security-icon">

                    <i class="fa-solid fa-lock"></i>

                </div>

                <span>
                    ACCOUNT SECURITY
                </span>

                <h1>
                    เปลี่ยนรหัสผ่าน
                </h1>

                <p>
                    ตั้งรหัสผ่านใหม่สำหรับบัญชี Bizcomp Club
                </p>

            </div>


            <!-- =================================
                 MESSAGE
            ================================== -->

            <?php if ($error): ?>

                <div class="security-alert error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <?php if ($success): ?>

                <div class="security-alert success">

                    <i class="fa-solid fa-circle-check"></i>

                    <?= htmlspecialchars($success) ?>

                </div>

            <?php endif; ?>


            <!-- =================================
                 FORM
            ================================== -->

            <form method="POST">


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >


                <!-- Password ปัจจุบัน -->

                <div class="security-group">

                    <label>
                        รหัสผ่านปัจจุบัน
                    </label>

                    <div class="password-input">

                        <input
                            type="password"
                            name="current_password"
                            id="currentPassword"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            data-target="currentPassword"
                        >

                            <i class="fa-regular fa-eye"></i>

                        </button>

                    </div>

                </div>


                <!-- Password ใหม่ -->

                <div class="security-group">

                    <label>
                        รหัสผ่านใหม่
                    </label>

                    <div class="password-input">

                        <input
                            type="password"
                            name="new_password"
                            id="newPassword"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            data-target="newPassword"
                        >

                            <i class="fa-regular fa-eye"></i>

                        </button>

                    </div>

                    <div class="security-help">
                        ใช้อย่างน้อย 8 ตัวอักษร
                    </div>

                </div>


                <!-- Confirm -->

                <div class="security-group">

                    <label>
                        ยืนยันรหัสผ่านใหม่
                    </label>

                    <div class="password-input">

                        <input
                            type="password"
                            name="confirm_password"
                            id="confirmPassword"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            data-target="confirmPassword"
                        >

                            <i class="fa-regular fa-eye"></i>

                        </button>

                    </div>

                </div>


                <!-- =================================
                     ACTION
                ================================== -->

                <div class="security-actions">

                    <a
                        href="profile.php"
                        class="security-back"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        กลับ

                    </a>


                    <button
                        type="submit"
                        class="security-submit"
                    >

                        <i class="fa-solid fa-key"></i>

                        เปลี่ยนรหัสผ่าน

                    </button>

                </div>


            </form>


            <div class="security-note">

                <i class="fa-solid fa-shield-halved"></i>

                ระบบจะไม่แสดงหรือเก็บรหัสผ่านจริง
                รหัสผ่านจะถูกเข้ารหัสก่อนบันทึกลงฐานข้อมูล

            </div>


        </div>

    </div>

</section>


<script>

// =========================================
// SHOW / HIDE PASSWORD
// =========================================

document
    .querySelectorAll('.password-toggle')
    .forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                const input =
                    document.getElementById(
                        this.dataset.target
                    );

                const icon =
                    this.querySelector('i');


                if (input.type === 'password') {

                    input.type = 'text';

                    icon.className =
                        'fa-regular fa-eye-slash';

                } else {

                    input.type = 'password';

                    icon.className =
                        'fa-regular fa-eye';
                }

            }
        );

    });

</script>


<?php
include 'includes/footer.php';
?>