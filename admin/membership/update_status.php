<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// update_status.php หน้านี้ใช้สำหรับอนุมัติหรือปฏิเสธคำขอสมัครสมาชิก

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: applications.php');
    exit;
}

$applicationId = (int)($_POST['application_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';
$rejectReason = preg_replace(
    '/^\s+|\s+$/u',
    '',
    (string)($_POST['reject_reason'] ?? '')
) ?? '';

if (empty($_SESSION['membership_admin_csrf']) || !hash_equals($_SESSION['membership_admin_csrf'], $csrfToken)) {
    $_SESSION['application_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    header('Location: applications.php');
    exit;
}

if (
    $applicationId <= 0 ||
    !in_array($action, ['approve', 'reject'], true)
) {
    $_SESSION['application_error'] = 'ข้อมูลคำขอไม่ถูกต้อง';
    header('Location: applications.php');
    exit;
}

if ($action === 'reject' && $rejectReason === '') {
    $_SESSION['application_error'] =
        'กรุณากรอกเหตุผลการปฏิเสธคำขอสมัครสมาชิก';
    header('Location: applications.php');
    exit;
}

if ($action === 'reject' && mb_strlen($rejectReason, 'UTF-8') > 2000) {
    $_SESSION['application_error'] =
        'เหตุผลการปฏิเสธต้องมีความยาวไม่เกิน 2,000 ตัวอักษร';
    header('Location: applications.php');
    exit;
}

$rejectAdminNote =
    $action === 'reject'
        ? 'เหตุผล: ' . $rejectReason
        : '';


/* =========================================
   ฟังก์ชันอ่านโครงสร้างตาราง
========================================= */

function getTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = $column;
    }

    return $columns;
}


/* =========================================
   หาค่าสถานะ Payment ที่ฐานข้อมูลรองรับ
========================================= */

function getPaymentStatusValue(
    array $paymentColumns,
    array $preferred
): ?string {
    if (!isset($paymentColumns['payment_status'])) {
        return null;
    }

    $type = (string)(
        $paymentColumns['payment_status']['Type'] ?? ''
    );

    // ถ้าไม่ใช่ ENUM ใช้ค่าตัวแรกที่ต้องการได้เลย
    if (stripos($type, 'enum(') !== 0) {
        return $preferred[0] ?? null;
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $type,
        $matches
    );

    $allowed = $matches[1] ?? [];

    foreach ($preferred as $value) {
        if (in_array($value, $allowed, true)) {
            return $value;
        }
    }

    return null;
}


/* =========================================
   เพิ่ม / อัปเดตสมาชิก
========================================= */

function saveApprovedMember(
    PDO $pdo,
    array $application
): void {
    $memberColumns = getTableColumns($pdo, 'members');

    if (
        !isset($memberColumns['user_id']) ||
        !isset($memberColumns['student_id'])
    ) {
        throw new RuntimeException(
            'โครงสร้างตาราง members ไม่ถูกต้อง'
        );
    }

    $userId = (int)$application['user_id'];
    $studentId = trim(
        (string)($application['student_id'] ?? '')
    );

    $admissionYear = null;

    if (
        function_exists(
            'inferAdmissionYearFromStudentId'
        )
    ) {
        $admissionYear =
            inferAdmissionYearFromStudentId(
                $studentId
            );
    }

    /*
     * เตรียมข้อมูลเฉพาะคอลัมน์ที่มีจริง
     * เพื่อไม่ให้พังถ้า schema ของ members ต่างกันเล็กน้อย
     */
    $data = [
        'user_id' => $userId,
        'student_id' => $studentId
    ];

    if (isset($memberColumns['admission_year'])) {
        $data['admission_year'] =
            $admissionYear ?: null;
    }

    if (isset($memberColumns['faculty'])) {
        $data['faculty'] =
            $application['faculty'] ?? null;
    }

    if (isset($memberColumns['major'])) {
        $data['major'] =
            $application['major'] ?? null;
    }

    if (isset($memberColumns['gender'])) {
        $data['gender'] =
            $application['gender'] ?? null;
    }

    if (isset($memberColumns['phone'])) {
        $data['phone'] =
            $application['phone'] ?? null;
    }

    if (isset($memberColumns['member_status'])) {
        $data['member_status'] = 'active';
    }

    if (isset($memberColumns['joined_at'])) {
        $data['joined_at'] =
            date('Y-m-d H:i:s');
    }

    // ตรวจว่ามีสมาชิกคนนี้อยู่แล้วหรือยัง
    $stmt = $pdo->prepare("
        SELECT id
        FROM members
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $existingMemberId =
        $stmt->fetchColumn();

    if ($existingMemberId) {

        $sets = [];
        $values = [];

        foreach ($data as $column => $value) {
            if ($column === 'user_id') {
                continue;
            }

            $sets[] = "`$column` = ?";
            $values[] = $value;
        }

        $values[] = (int)$existingMemberId;

        $stmt = $pdo->prepare("
            UPDATE members
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");

        $stmt->execute($values);

    } else {

        $columns =
            array_keys($data);

        $placeholders =
            array_fill(
                0,
                count($columns),
                '?'
            );

        $quotedColumns =
            array_map(
                fn($column) => "`$column`",
                $columns
            );

        $stmt = $pdo->prepare("
            INSERT INTO members (
                " . implode(', ', $quotedColumns) . "
            )
            VALUES (
                " . implode(', ', $placeholders) . "
            )
        ");

        $stmt->execute(
            array_values($data)
        );
    }


    /*
     * ถ้า users มี role/account_status
     * อัปเดตให้สอดคล้องกับการเป็นสมาชิก
     */
    $userColumns =
        getTableColumns($pdo, 'users');

    $userSets = [];
    $userValues = [];

    if (isset($userColumns['role'])) {
        /*
         * ไม่เปลี่ยน admin ให้กลายเป็น member
         */
        $stmt = $pdo->prepare("
            SELECT role
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $currentRole =
            (string)$stmt->fetchColumn();

        if ($currentRole !== 'admin') {
            $userSets[] = "`role` = ?";
            $userValues[] = 'user';
        }
    }

    if (isset($userColumns['account_status'])) {
        $userSets[] = "`account_status` = ?";
        $userValues[] = 'active';
    }

    if (!empty($userSets)) {
        $userValues[] = $userId;

        $stmt = $pdo->prepare("
            UPDATE users
            SET " . implode(', ', $userSets) . "
            WHERE id = ?
        ");

        $stmt->execute($userValues);
    }
}


/* =========================================
   ประมวลผล
========================================= */

try {

    $pdo->beginTransaction();


    // ล็อกคำขอไว้ก่อน
    $stmt = $pdo->prepare("
        SELECT *
        FROM membership_applications
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $applicationId
    ]);

    $application =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        throw new RuntimeException(
            'ไม่พบคำขอสมัครสมาชิก'
        );
    }

    $userStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([(int)$application['user_id']]);
    $applicant = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];


    $currentStatus =
        (string)(
            $application[
                'application_status'
            ] ?? ''
        );


    // ดึง Payment ล่าสุด
    $stmt = $pdo->prepare("
        SELECT *
        FROM membership_payments
        WHERE application_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $applicationId
    ]);

    $payment =
        $stmt->fetch(PDO::FETCH_ASSOC);

    $paymentColumns =
        getTableColumns(
            $pdo,
            'membership_payments'
        );


    /* =====================================
       ปฏิเสธ
    ====================================== */

    if ($action === 'reject') {

        if (
            in_array(
                $currentStatus,
                ['approved', 'rejected'],
                true
            )
        ) {
            throw new RuntimeException(
                'คำขอนี้ถูกดำเนินการแล้ว'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET
                application_status = 'rejected',
                admin_note = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $rejectAdminNote,
            $applicationId
        ]);


        // ถ้ามี Payment ให้เปลี่ยนเป็น rejected ด้วย
        if ($payment) {

            $rejectPaymentStatus =
                getPaymentStatusValue(
                    $paymentColumns,
                    [
                        'rejected',
                        'failed',
                        'declined'
                    ]
                );

            if ($rejectPaymentStatus !== null) {

                $stmt = $pdo->prepare("
                    UPDATE membership_payments
                    SET
                        payment_status = ?,
                        admin_note = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $rejectPaymentStatus,
                    $rejectAdminNote,
                    (int)$payment['id']
                ]);
            }
        }

        $pdo->commit();

        logAdminAction($pdo, 'reject', 'membership_application', $applicationId, 'ปฏิเสธคำขอสมัครสมาชิก', ['status'=>$currentStatus], ['status'=>'rejected', 'reason'=>$rejectAdminNote]);
        sendBizcompNotificationEmail(
            $applicant['email'] ?? '',
            trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? '')),
            'ผลการตรวจสอบคำขอสมาชิก',
            'คำขอสมาชิกไม่ได้รับการอนุมัติ',
            ['คำขอสมัครสมาชิกของคุณได้รับการตรวจสอบแล้ว', 'กรุณาติดต่อชมรมหากต้องการสอบถามรายละเอียดเพิ่มเติม']
        );

        $_SESSION['application_success'] =
            'ปฏิเสธคำขอสมัครสมาชิกเรียบร้อยแล้ว';

        header('Location: applications.php');
        exit;
    }


    /* =====================================
       ยืนยันครั้งที่ 1
       pending -> waiting_payment
    ====================================== */

    if ($currentStatus === 'pending') {

        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET application_status = 'waiting_payment'
            WHERE id = ?
        ");

        $stmt->execute([
            $applicationId
        ]);

        $pdo->commit();

        logAdminAction($pdo, 'approve', 'membership_application', $applicationId, 'อนุมัติคำขอและรอชำระเงิน', ['status'=>$currentStatus], ['status'=>'waiting_payment']);
        sendBizcompNotificationEmail(
            $applicant['email'] ?? '',
            trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? '')),
            'ผลการตรวจสอบคำขอสมาชิก',
            'คำขอสมาชิกผ่านการตรวจสอบแล้ว',
            ['กรุณาเข้าสู่ระบบเพื่อชำระค่าสมาชิกตามขั้นตอน', 'ระบบจะแจ้งผลอีกครั้งหลังผู้ดูแลตรวจสอบหลักฐานการชำระเงิน']
        );

        $_SESSION['application_success'] =
            'อนุมัติคำขอแล้ว กรุณารอผู้สมัครชำระค่าสมาชิก';

        header('Location: applications.php');
        exit;
    }


    /* =====================================
       ยืนยันหลังตรวจสลิป
       checking_payment -> approved
    ====================================== */

    if ($currentStatus === 'checking_payment') {

        if (!$payment) {
            throw new RuntimeException(
                'ไม่พบหลักฐานการชำระเงินของคำขอนี้'
            );
        }


        // 1. อนุมัติ Payment
        $approvedPaymentStatus =
            getPaymentStatusValue(
                $paymentColumns,
                [
                    'approved',
                    'paid',
                    'verified',
                    'success'
                ]
            );

        if ($approvedPaymentStatus === null) {
            throw new RuntimeException(
                'ค่า payment_status ในฐานข้อมูลไม่รองรับสถานะอนุมัติ'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE membership_payments
            SET payment_status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $approvedPaymentStatus,
            (int)$payment['id']
        ]);


        // 2. เปลี่ยนคำขอเป็น approved
        $stmt = $pdo->prepare("
            UPDATE membership_applications
            SET application_status = 'approved'
            WHERE id = ?
        ");

        $stmt->execute([
            $applicationId
        ]);


        // 3. เพิ่มลงตาราง members
        saveApprovedMember(
            $pdo,
            $application
        );


        // 4. สร้างใบเสร็จอัตโนมัติ
        $checkReceipt = $pdo->prepare("
            SELECT id
            FROM membership_receipts
            WHERE payment_id = ?
            LIMIT 1
        ");

        $checkReceipt->execute([
            (int)$payment['id']
        ]);

        if (!$checkReceipt->fetchColumn()) {

            $receiptNo =
                'BC-' .
                date('Ymd') .
                '-' .
                str_pad(
                    (string)$applicationId,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            $stmt = $pdo->prepare("
                INSERT INTO membership_receipts (
                    application_id,
                    payment_id,
                    user_id,
                    receipt_no,
                    amount,
                    payment_method,
                    issued_at
                )
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $applicationId,
                (int)$payment['id'],
                (int)$application['user_id'],
                $receiptNo,
                $payment['amount'] ?? 0,
                $payment['payment_method'] ?? null
            ]);
        }


        $pdo->commit();

        logAdminAction($pdo, 'approve', 'membership_application', $applicationId, 'อนุมัติสมาชิกและยืนยันการชำระเงิน', ['status'=>$currentStatus], ['status'=>'approved']);
        sendBizcompNotificationEmail(
            $applicant['email'] ?? '',
            trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? '')),
            'อนุมัติสมาชิกเรียบร้อยแล้ว',
            'ยินดีต้อนรับสู่ Bizcomp Club',
            ['หลักฐานการชำระเงินได้รับการตรวจสอบและคำขอสมาชิกได้รับการอนุมัติแล้ว', 'คุณสามารถเข้าสู่ระบบเพื่อดูข้อมูลสมาชิกและกิจกรรมได้']
        );

        $_SESSION['application_success'] =
            'ยืนยันการชำระเงิน อนุมัติสมาชิก และสร้างใบเสร็จเรียบร้อยแล้ว';

        header('Location: applications.php');
        exit;
    }


    /* =====================================
       สถานะอื่น
    ====================================== */

    if ($currentStatus === 'waiting_payment') {
        throw new RuntimeException(
            'คำขอนี้ยังรอผู้สมัครชำระเงิน'
        );
    }

    if ($currentStatus === 'approved') {
        throw new RuntimeException(
            'คำขอนี้อนุมัติแล้ว'
        );
    }

    if ($currentStatus === 'rejected') {
        throw new RuntimeException(
            'คำขอนี้ถูกปฏิเสธแล้ว'
        );
    }

    throw new RuntimeException(
        'ไม่สามารถเปลี่ยนสถานะคำขอนี้ได้'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'MEMBERSHIP UPDATE STATUS ERROR: ' .
        $e->getMessage()
    );

    $_SESSION['application_error'] =
        $e->getMessage();

    header('Location: applications.php');
    exit;
}
