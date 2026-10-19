<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';


/* =========================================
   ถ้า Login อยู่แล้ว
========================================= */

if (isset($_SESSION['user_id'])) {

    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: admin/index.php');
        exit;
    }

    header('Location: index.php');
    exit;
}


/* =========================================
   ข้อความแจ้งเตือน
========================================= */

$error = '';
$success = $_SESSION['login_success'] ?? '';

unset($_SESSION['login_success']);


/* =========================================
   Login
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';


    if ($email === '' || $password === '') {

        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'รูปแบบอีเมลไม่ถูกต้อง';

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();


        if (!$user || !password_verify($password, $user['password'])) {

            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';

        } elseif ($user['account_status'] === 'blocked') {

            $error = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';

        } elseif ($user['account_status'] !== 'active') {

            $error = 'บัญชีนี้ยังไม่สามารถใช้งานได้';

        } else {

            session_regenerate_id(true);


            $_SESSION['user_id'] = (int)$user['id'];

            $_SESSION['first_name'] = $user['first_name'];

            $_SESSION['last_name'] = $user['last_name'];

            $_SESSION['email'] = $user['email'];

            $_SESSION['profile_image'] = $user['profile_image'] ?? null;

            $_SESSION['role'] = $user['role'];


            /* ถ้าเป็น Admin */

            if ($user['role'] === 'admin') {

                header('Location: admin/index.php');
                exit;

            }


            /* ผู้ใช้งานทั่วไป */

            header('Location: index.php');
            exit;

        }

    }

}


$pageTitle = 'เข้าสู่ระบบ';
$pageCss = 'login.css';

include 'includes/header.php';

?>


<section class="login-page">

    <div class="login-container">

        <div class="login-card">


            <!-- =================================
                 HEADER
            ================================== -->

            <div class="login-card-header">

                <div class="login-icon">
                    <i class="fa-regular fa-user"></i>
                </div>

                <span class="login-label">
                    BIZCOMP CLUB
                </span>

                <h1>
                    เข้าสู่ระบบ
                </h1>

                <p>
                    เข้าสู่บัญชีของคุณเพื่อใช้งานระบบ Bizcomp Club
                </p>

            </div>


            <!-- =================================
                 สมัครสำเร็จ
            ================================== -->

            <?php if ($success): ?>

                <div class="login-alert success">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        <?= htmlspecialchars($success) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================
                 ERROR
            ================================== -->

            <?php if ($error): ?>

                <div class="login-alert error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($error) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================
                 FORM
            ================================== -->

            <form
                action="login.php"
                method="POST"
                class="login-form"
            >


                <!-- Email -->

                <div class="login-form-group">

                    <label for="email">
                        อีเมล
                    </label>

                    <div class="login-input-box">

                        <i class="fa-regular fa-envelope"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="example@email.com"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>


                <!-- Password -->

                <div class="login-form-group">

                    <label for="password">
                        รหัสผ่าน
                    </label>

                    <div class="login-input-box">

                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="กรอกรหัสผ่าน"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="login-password-toggle"
                            id="passwordToggle"
                            aria-label="แสดงหรือซ่อนรหัสผ่าน"
                        >
                            <i class="fa-regular fa-eye"></i>
                        </button>

                    </div>

                    <div style="margin-top:7px;text-align:right;">
                        <a
                            href="forgot_password_otp.php"
                            style="color:#1768cf;font-size:11px;font-weight:500;text-decoration:none;"
                        >
                            ลืมรหัสผ่าน?
                        </a>
                    </div>

                </div>


                <!-- Submit -->

                <button
                    type="submit"
                    class="login-submit"
                >

                    <i class="fa-solid fa-arrow-right-to-bracket"></i>

                    เข้าสู่ระบบ

                </button>


            </form>


            <!-- =================================
                 REGISTER
            ================================== -->

            <div class="login-register">

                <span>
                    ยังไม่มีบัญชีใช่ไหม?
                </span>

                <a href="register.php">
                    สมัครบัญชี
                </a>

            </div>


        </div>

    </div>

</section>


<script>

const passwordInput = document.getElementById('password');
const passwordToggle = document.getElementById('passwordToggle');

if (passwordInput && passwordToggle) {

    passwordToggle.addEventListener('click', function () {

        const icon = this.querySelector('i');

        if (passwordInput.type === 'password') {

            passwordInput.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

        } else {

            passwordInput.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

        }

    });

}

</script>


<?php include 'includes/footer.php'; ?>
