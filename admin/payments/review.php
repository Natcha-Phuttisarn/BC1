<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// review.php
// หน้านี้ใช้สำหรับอนุมัติหรือปฏิเสธหลักฐานการชำระค่าสมัครสมาชิก

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../includes/admin_auth.php';
require_once '../../includes/mail.php';


// =========================================
// ต้อง POST เท่านั้น
// =========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;
}


// =========================================
// FUNCTION
// =========================================

function paymentAdminError(string $message): void
{
    $_SESSION['payment_admin_error'] = $message;

    header('Location: index.php');
    exit;
}


function paymentAdminSuccess(string $message): void
{
    $_SESSION['payment_admin_success'] = $message;

    header('Location: index.php');
    exit;
}


// =========================================
// CSRF
// =========================================

$csrfToken =
    $_POST['csrf_token']
    ?? '';

$sessionToken =
    $_SESSION['admin_payment_csrf']
    ?? '';

if (
    $sessionToken === ''
    ||
    !hash_equals(
        $sessionToken,
        $csrfToken
    )
) {

    paymentAdminError(
        'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'
    );
}


// =========================================
// รับข้อมูล
// =========================================

$paymentId =
    (int)($_POST['payment_id'] ?? 0);

$action =
    trim($_POST['action'] ?? '');

if ($paymentId <= 0) {

    paymentAdminError(
        'ไม่พบรายการชำระเงิน'
    );
}

if (
    !in_array(
        $action,
        [
            'approve',
            'reject'
        ],
        true
    )
) {

    paymentAdminError(
        'คำสั่งไม่ถูกต้อง'
    );
}


// =========================================
// เหตุผลการปฏิเสธ
// =========================================

$reasonKey =
    trim(
        $_POST['reject_reason']
        ?? ''
    );

$rejectDetail =
    trim(
        $_POST['reject_detail']
        ?? ''
    );

$reasonList = [

    'slip_unclear' =>
        'สลิปไม่ชัดเจน',

    'wrong_amount' =>
        'ยอดเงินไม่ถูกต้อง',

    'wrong_slip' =>
        'สลิปไม่ตรงกับข้อมูลที่แจ้ง',

    'student_info' =>
        'ข้อมูลนิสิตไม่ถูกต้อง',

    'incomplete' =>
        'ข้อมูลไม่ครบถ้วน',

    'other' =>
        'เหตุผลอื่น ๆ'
];


// =========================================
// TRANSACTION
// =========================================

try {

    $pdo->beginTransaction();


    // =====================================
    // ดึง Payment + Application
    // =====================================

    $stmt = $pdo->prepare("
        SELECT
            p.id AS payment_id,
            p.application_id,
            p.user_id,
            p.amount,
            p.payment_status,

            a.student_id,
            a.faculty,
            a.major,
            a.year_level,
            a.gender,
            a.phone,
            a.application_status,
            u.first_name,
            u.last_name,
            u.email

        FROM membership_payments p

        INNER JOIN membership_applications a
            ON a.id = p.application_id

        INNER JOIN users u ON u.id = p.user_id

        WHERE p.id = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $paymentId
    ]);

    $data = $stmt->fetch();


    if (!$data) {

        throw new RuntimeException(
            'ไม่พบรายการชำระเงิน'
        );
    }


    // =====================================
    // ตรวจสถานะก่อน
    // ป้องกัน Approved -> Rejected
    // =====================================

    if (
        $data['payment_status'] !== 'pending'
        ||
        $data['application_status'] !== 'checking_payment'
    ) {

        throw new RuntimeException(
            'รายการนี้ได้รับการตรวจสอบไปแล้ว ไม่สามารถเปลี่ยนผลจากหน้านี้ได้'
        );
    }


    // =====================================
    // ตรวจยอดเงิน
    // =====================================

    // ใช้ยอดที่บันทึกไว้ตอนผู้ใช้สมัคร เพื่อให้รายการเดิมยังตรวจสอบได้
    // แม้ผู้ดูแลจะเปลี่ยนค่าสมัครสำหรับรายการใหม่ในภายหลัง
    if ((float)$data['amount'] < 0) {
        throw new RuntimeException('ยอดเงินในรายการไม่ถูกต้อง');
    }


    // =====================================
    // APPROVE
    // =====================================

    if ($action === 'approve') {


        // =================================
        // ตรวจว่าเป็นสมาชิกแล้วหรือยัง
        // =================================

        $check = $pdo->prepare("
            SELECT id
            FROM members
            WHERE user_id = ?
            LIMIT 1
        ");

        $check->execute([
            $data['user_id']
        ]);

        $existingMember =
            $check->fetch();


        if (!$existingMember) {


            // =================================
            // ตรวจรหัสนิสิตซ้ำ
            // =================================

            $checkStudent =
                $pdo->prepare("
                    SELECT id
                    FROM members
                    WHERE student_id = ?
                    LIMIT 1
                ");

            $checkStudent->execute([
                $data['student_id']
            ]);


            if ($checkStudent->fetch()) {

                throw new RuntimeException(
                    'รหัสนิสิตนี้มีสมาชิกอยู่ในระบบแล้ว'
                );
            }


            // =================================
            // หา Admission Year
            // =================================

            $admissionYear =
                inferAdmissionYearFromStudentId(
                    $data['student_id']
                );


            if (!$admissionYear) {

                throw new RuntimeException(
                    'ไม่สามารถคำนวณปีที่เข้าเรียนจากรหัสนิสิตได้'
                );
            }


            // =================================
            // เพิ่มเป็นสมาชิกจริง
            // =================================

            $insertMember =
                $pdo->prepare("
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

                $data['user_id'],

                $data['student_id'],

                $admissionYear,

                $data['faculty'],

                $data['major'],

                $data['year_level'],

                $data['gender']
                    ?: null,

                $data['phone']
                    ?: null

            ]);

        }


        // =================================
        // อนุมัติ Payment
        // =================================

        $stmt = $pdo->prepare("
            UPDATE membership_payments
            SET
                payment_status = 'approved',
                admin_note = ?,
                paid_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            'ตรวจสอบหลักฐานการชำระเงินเรียบร้อยแล้ว',
            $paymentId
        ]);


        // =================================
        // อนุมัติ Application
        // =================================

        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET
                application_status = 'approved',
                admin_note = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $data['application_id']
        ]);


        $pdo->commit();

        logAdminAction($pdo, 'approve', 'membership_payment', $paymentId, 'อนุมัติหลักฐานการชำระเงินและสมาชิก', ['payment_status'=>$data['payment_status']], ['payment_status'=>'approved']);
        sendBizcompNotificationEmail($data['email'] ?? '', trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')), 'ผลการตรวจสอบการชำระเงิน', 'ชำระเงินได้รับการอนุมัติแล้ว', ['หลักฐานการชำระเงินของคุณได้รับการตรวจสอบแล้ว และสมาชิกได้รับการอนุมัติเรียบร้อยแล้ว']);


        paymentAdminSuccess(
            'อนุมัติการชำระเงินและเพิ่มเป็นสมาชิกเรียบร้อยแล้ว'
        );

    }


    // =====================================
    // REJECT
    // =====================================

    if ($action === 'reject') {


        // =================================
        // ต้องเลือกเหตุผล
        // =================================

        if (
            !array_key_exists(
                $reasonKey,
                $reasonList
            )
        ) {

            throw new RuntimeException(
                'กรุณาเลือกเหตุผลที่ไม่อนุมัติ'
            );
        }


        // อื่น ๆ ต้องกรอกรายละเอียด
        if (
            $reasonKey === 'other'
            &&
            $rejectDetail === ''
        ) {

            throw new RuntimeException(
                'กรุณาระบุรายละเอียดเหตุผลที่ไม่อนุมัติ'
            );
        }


        // =================================
        // สร้างข้อความเหตุผล
        // =================================

        $adminNote =
            'เหตุผล: ' .
            $reasonList[$reasonKey];


        if ($rejectDetail !== '') {

            $adminNote .=
                "\nรายละเอียด: " .
                $rejectDetail;
        }


        // =================================
        // ปฏิเสธ Payment
        // =================================

        $stmt = $pdo->prepare("
            UPDATE membership_payments
            SET
                payment_status = 'rejected',
                admin_note = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $adminNote,
            $paymentId
        ]);


        // =================================
        // ปฏิเสธ Application
        // =================================

        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET
                application_status = 'rejected',
                admin_note = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $adminNote,
            $data['application_id']
        ]);


        $pdo->commit();

        logAdminAction($pdo, 'reject', 'membership_payment', $paymentId, 'ไม่อนุมัติหลักฐานการชำระเงิน', ['payment_status'=>$data['payment_status']], ['payment_status'=>'rejected', 'reason'=>$adminNote]);
        sendBizcompNotificationEmail($data['email'] ?? '', trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')), 'ผลการตรวจสอบการชำระเงิน', 'หลักฐานการชำระเงินต้องแก้ไข', ['หลักฐานการชำระเงินยังไม่ผ่านการตรวจสอบ', $adminNote, 'กรุณาเข้าสู่ระบบเพื่อส่งหลักฐานใหม่']);


        paymentAdminSuccess(
            'บันทึกการไม่อนุมัติและเหตุผลเรียบร้อยแล้ว'
        );

    }


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    paymentAdminError(
        $e->getMessage()
    );
}
