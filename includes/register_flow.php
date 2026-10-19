<?php

/**
 * ฟังก์ชันกลางสำหรับการสมัครสมาชิกแบบ 2 ขั้นตอน
 * ข้อมูลรหัสผ่านที่ส่งต่อระหว่างหน้าเก็บเป็น password hash เท่านั้น
 */

function validateRegistrationDetails(array $source): array
{
    $data = [
        'first_name' => trim((string)($source['first_name'] ?? '')),
        'last_name' => trim((string)($source['last_name'] ?? '')),
        'student_id' => trim((string)($source['student_id'] ?? '')),
        'faculty' => trim((string)($source['faculty'] ?? '')),
        'major' => trim((string)($source['major'] ?? '')),
        'gender' => trim((string)($source['gender'] ?? '')),
        'phone' => trim((string)($source['phone'] ?? '')),
        'email' => strtolower(trim((string)($source['email'] ?? ''))),
    ];

    foreach (['first_name', 'last_name', 'student_id', 'faculty', 'major', 'email'] as $field) {
        if ($data[$field] === '') {
            return [$data, null, 'กรุณากรอกข้อมูลที่จำเป็นให้ครบทุกช่อง'];
        }
    }

    if (
        mb_strlen($data['first_name']) > 100 ||
        mb_strlen($data['last_name']) > 100 ||
        mb_strlen($data['student_id']) > 30 ||
        mb_strlen($data['faculty']) > 200 ||
        mb_strlen($data['major']) > 200 ||
        mb_strlen($data['email']) > 150
    ) {
        return [$data, null, 'ข้อมูลบางช่องยาวเกินกว่าที่ระบบกำหนด'];
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [$data, null, 'รูปแบบอีเมลไม่ถูกต้อง'];
    }

    $normalizedPhone = normalizePhoneNumber($data['phone']);
    if ($normalizedPhone === false) {
        return [$data, null, 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง กรุณากรอกตัวเลข 9–15 หลัก'];
    }
    $data['phone'] = $normalizedPhone ?? '';

    if (!in_array($data['gender'], ['', 'ชาย', 'หญิง', 'อื่นๆ'], true)) {
        return [$data, null, 'ข้อมูลเพศไม่ถูกต้อง'];
    }

    $studentNumber = preg_replace('/\D/', '', $data['student_id']);
    if ($studentNumber === null || strlen($studentNumber) < 2) {
        return [$data, null, 'รหัสนิสิตไม่ถูกต้อง'];
    }

    $admissionYear = inferAdmissionYearFromStudentId($data['student_id']);
    if (!$admissionYear) {
        return [$data, null, 'ไม่สามารถตรวจสอบปีที่เข้าเรียนจากรหัสนิสิตได้'];
    }

    $yearLevel = (getCurrentAcademicYear() - $admissionYear) + 1;
    if ($yearLevel < 1 || $yearLevel > 4) {
        return [$data, null, 'รหัสนิสิตนี้ไม่อยู่ในช่วงชั้นปี 1 - 4'];
    }

    return [$data, $yearLevel, null];
}

function findRegistrationConflict(PDO $pdo, string $email, string $studentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            EXISTS (
                SELECT 1
                FROM membership_applications ma
                WHERE ma.user_id = u.id
                  AND ma.application_status IN ('pending', 'waiting_payment', 'checking_payment')
            ) AS has_pending_application
        FROM users u
        WHERE LOWER(u.email) = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ((int)$user['has_pending_application'] === 1) {
            return [
                'type' => 'pending',
                'message' => 'อีเมลนี้มีคำขอสมัครสมาชิกอยู่ระหว่างตรวจสอบ กรุณาเข้าสู่ระบบเพื่อตรวจสอบสถานะ',
            ];
        }

        return [
            'type' => 'email',
            'message' => 'อีเมลนี้ถูกใช้สมัครสมาชิกแล้ว กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ',
        ];
    }

    $stmt = $pdo->prepare('SELECT id FROM members WHERE student_id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    if ($stmt->fetchColumn()) {
        return [
            'type' => 'student',
            'message' => 'รหัสนิสิตนี้เป็นสมาชิกชมรมอยู่แล้ว กรุณาเข้าสู่ระบบด้วยบัญชีเดิม',
        ];
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM membership_applications
        WHERE student_id = ?
          AND application_status IN ('pending', 'waiting_payment', 'checking_payment', 'approved')
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    if ($stmt->fetchColumn()) {
        return [
            'type' => 'student',
            'message' => 'รหัสนิสิตนี้มีคำขอสมัครสมาชิกอยู่ในระบบแล้ว กรุณาใช้บัญชีเดิมเพื่อตรวจสอบสถานะ',
        ];
    }

    return null;
}

function registrationCsrfToken(): string
{
    if (empty($_SESSION['register_csrf'])) {
        $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['register_csrf'];
}

function validRegistrationCsrf(string $token): bool
{
    $sessionToken = (string)($_SESSION['register_csrf'] ?? '');

    return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
}

function validPreparedRegistration(array $step, int $maxAge = 3600): bool
{
    $required = [
        'first_name',
        'last_name',
        'student_id',
        'faculty',
        'major',
        'email',
        'password_hash',
        'submission_token',
        'prepared_at',
        'year_level',
    ];

    foreach ($required as $field) {
        if (!array_key_exists($field, $step) || $step[$field] === '') {
            return false;
        }
    }

    $preparedAt = filter_var($step['prepared_at'], FILTER_VALIDATE_INT);
    $yearLevel = filter_var($step['year_level'], FILTER_VALIDATE_INT);

    if (
        $preparedAt === false ||
        $preparedAt > time() ||
        (time() - $preparedAt) > $maxAge ||
        $yearLevel === false ||
        $yearLevel < 1 ||
        $yearLevel > 4
    ) {
        return false;
    }

    if (!filter_var($step['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $passwordHash = (string)$step['password_hash'];
    $hashInfo = password_get_info($passwordHash);

    return
        $passwordHash !== '' &&
        strlen($passwordHash) <= 255 &&
        ($hashInfo['algoName'] ?? 'unknown') !== 'unknown' &&
        preg_match('/^[a-f0-9]{64}$/', (string)$step['submission_token']) === 1;
}

function registrationPaymentError(string $message): void
{
    $_SESSION['register_payment_error'] = $message;
    header('Location: register_payment.php');
    exit;
}
