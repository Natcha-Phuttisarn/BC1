<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// Endpoint เก่านี้เลิกใช้งานแล้ว ระบบปัจจุบันดำเนินการผ่าน review.php
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'Endpoint นี้ยกเลิกการใช้งานแล้ว';
exit;

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$paymentId = (int)($_POST['payment_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($paymentId <= 0) {

    $_SESSION['payment_error'] =
        'ไม่พบข้อมูลการชำระเงิน';

    header('Location: index.php');
    exit;
}


/* =========================================
   ดึงข้อมูล Payment + Application
========================================= */

$stmt = $pdo->prepare("
    SELECT
        mp.*,
        ma.student_id,
        ma.faculty,
        ma.major,
        ma.year_level,
        ma.gender,
        ma.phone,
        ma.application_status
    FROM membership_payments mp
    INNER JOIN membership_applications ma
        ON ma.id = mp.application_id
    WHERE mp.id = ?
    LIMIT 1
");

$stmt->execute([$paymentId]);

$payment = $stmt->fetch();

if (!$payment) {

    $_SESSION['payment_error'] =
        'ไม่พบรายการชำระเงิน';

    header('Location: index.php');
    exit;
}


if ($payment['payment_status'] !== 'pending') {

    $_SESSION['payment_error'] =
        'รายการนี้ถูกดำเนินการแล้ว';

    header('Location: index.php');
    exit;
}


/* =========================================
   ไม่อนุมัติ
========================================= */

if ($action === 'reject') {

    try {

        $pdo->beginTransaction();


        /* การชำระเงินไม่ผ่าน */

        $stmt = $pdo->prepare("
            UPDATE membership_payments
            SET
                payment_status = 'rejected',
                admin_note = ?
            WHERE id = ?
        ");

        $stmt->execute([
            'หลักฐานการชำระเงินไม่ผ่านการตรวจสอบ',
            $paymentId
        ]);


        /* ให้กลับไปรอชำระใหม่ */

        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET application_status = 'waiting_payment'
            WHERE id = ?
        ");

        $stmt->execute([
            $payment['application_id']
        ]);


        $pdo->commit();


        $_SESSION['payment_success'] =
            'ปฏิเสธหลักฐานการชำระเงินเรียบร้อยแล้ว';


        header('Location: index.php');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['payment_error'] =
            'ไม่สามารถดำเนินการได้: ' . $e->getMessage();

        header('Location: index.php');
        exit;
    }
}


/* =========================================
   อนุมัติ
========================================= */

if ($action === 'approve') {

    try {

        $pdo->beginTransaction();


        /* =====================================
           ตรวจว่ามีสมาชิกอยู่แล้วหรือไม่
        ====================================== */

        $checkMember = $pdo->prepare("
            SELECT id
            FROM members
            WHERE user_id = ?
            LIMIT 1
        ");

        $checkMember->execute([
            $payment['user_id']
        ]);

        $existingMember = $checkMember->fetch();


        /* =====================================
           ถ้ายังไม่มีสมาชิก → สร้างสมาชิก
        ====================================== */

        if (!$existingMember) {

            /*
             * ปีที่เข้าเรียน
             *
             * พยายามหาอัตโนมัติจากรหัสนิสิต
             * เช่น 66xxxxxx → 2566
             */

            $admissionYear =
                inferAdmissionYearFromStudentId(
                    $payment['student_id']
                );


            $insertMember = $pdo->prepare("
                INSERT INTO members (
                    user_id,
                    student_id,
                    admission_year,
                    faculty,
                    major,
                    year_level,
                    gender,
                    phone,
                    member_status
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'active'
                )
            ");

            $insertMember->execute([
                $payment['user_id'],
                $payment['student_id'],
                $admissionYear,
                $payment['faculty'],
                $payment['major'],
                $payment['year_level'],
                $payment['gender'],
                $payment['phone']
            ]);
        }


        /* =====================================
           Payment = Approved
        ====================================== */

        $updatePayment = $pdo->prepare("
            UPDATE membership_payments
            SET
                payment_status = 'approved',
                admin_note = ?
            WHERE id = ?
        ");

        $updatePayment->execute([
            'ตรวจสอบหลักฐานการชำระเงินแล้ว',
            $paymentId
        ]);


        /* =====================================
           Application = Approved
        ====================================== */

        $updateApplication = $pdo->prepare("
            UPDATE membership_applications
            SET application_status = 'approved'
            WHERE id = ?
        ");

        $updateApplication->execute([
            $payment['application_id']
        ]);


        $pdo->commit();


        $_SESSION['payment_success'] =
            'อนุมัติการชำระเงินและเพิ่มเป็นสมาชิกชมรมเรียบร้อยแล้ว';


        header('Location: index.php');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['payment_error'] =
            'ไม่สามารถอนุมัติการชำระเงินได้: ' .
            $e->getMessage();

        header('Location: index.php');
        exit;
    }
}


$_SESSION['payment_error'] =
    'คำสั่งไม่ถูกต้อง';

header('Location: index.php');
exit;
