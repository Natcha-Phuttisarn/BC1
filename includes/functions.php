<?php
// functions.php
// ไฟล์นี้รวบรวมฟังก์ชันกลางของเว็บไซต์ เช่น วันที่ สมาชิก และการแปลงจำนวนเงินภาษาไทย

/* =========================================
   UPLOAD FILE
========================================= */

function uploadFile($file, $folder) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }

    $originalName = basename($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName = bin2hex(random_bytes(16));

    if ($extension !== '') {
        $storedName .= '.' . $extension;
    }

    $destination = rtrim($folder, '/') . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return false;
    }

    return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'file_path' => $destination,
        'file_size' => $file['size'],
        'mime_type' => mime_content_type($destination) ?: null,
        'file_extension' => $extension
    ];
}


/* =========================================
   วันที่ภาษาไทย
========================================= */

function formatThaiDate($date) {
    if (!$date) {
        return '-';
    }

    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.'
    ];

    $time = strtotime($date);
    $day = date('j', $time);
    $month = $months[(int)date('n', $time)];
    $year = (int)date('Y', $time) + 543;

    return $day . ' ' . $month . ' ' . $year;
}


/* =========================================
   ตรวจว่ากิจกรรมเพิ่งเผยแพร่ภายใน 7 วันหรือไม่
   ใช้ published_at เท่านั้น เพราะเป็นเวลาที่ผู้ใช้เห็นกิจกรรมจริง
========================================= */

function isActivityNew(
    array $activity,
    ?DateTimeImmutable $currentTime = null
): bool {

    $publishedAt = trim(
        (string)($activity['published_at'] ?? '')
    );

    if ($publishedAt === '') {
        return false;
    }

    try {
        $timezone = new DateTimeZone('Asia/Bangkok');

        $publishedTime = new DateTimeImmutable(
            $publishedAt,
            $timezone
        );

        $now = $currentTime !== null
            ? $currentTime->setTimezone($timezone)
            : new DateTimeImmutable('now', $timezone);

        $newBadgeExpiresAt =
            $publishedTime->modify('+7 days');

        return
            $publishedTime <= $now
            && $now < $newBadgeExpiresAt;

    } catch (Throwable $e) {
        return false;
    }
}


/* =========================================
   ปีการศึกษาปัจจุบัน
   เปลี่ยนทุกวันที่ 1 มิถุนายน
========================================= */

function getCurrentAcademicYear() {
    date_default_timezone_set('Asia/Bangkok');

    $year = (int)date('Y');
    $month = (int)date('n');

    if ($month >= 6) {
        return $year + 543;
    }

    return ($year - 1) + 543;
}


/* =========================================
   อ่านปีเข้าจากรหัสนิสิต

   ตัวอย่าง
   66xxxxxx = 2566
   67xxxxxx = 2567
========================================= */

function inferAdmissionYearFromStudentId($studentId) {
    $studentId = preg_replace('/\D/', '', (string)$studentId);

    if (strlen($studentId) < 2) {
        return null;
    }

    $prefix = (int)substr($studentId, 0, 2);

    if ($prefix >= 50 && $prefix <= 99) {
        return 2500 + $prefix;
    }

    return null;
}


/* =========================================
   หาปีที่เข้าเรียนของสมาชิก
========================================= */

function getMemberAdmissionYear($member) {
    if (!empty($member['admission_year'])) {
        return (int)$member['admission_year'];
    }

    return inferAdmissionYearFromStudentId(
        $member['student_id'] ?? ''
    );
}


/* =========================================
   คำนวณชั้นปีอัตโนมัติ
========================================= */

function calculateMemberYearLevel($member) {
    $admissionYear = getMemberAdmissionYear($member);

    if (!$admissionYear) {
        return null;
    }

    $currentAcademicYear = getCurrentAcademicYear();

    return ($currentAcademicYear - $admissionYear) + 1;
}


/* =========================================
   คำนวณสถานะสมาชิกอัตโนมัติ
========================================= */

function getAutomaticMemberStatus($member) {
    $manualStatus = $member['member_status'] ?? 'active';
    $yearLevel = calculateMemberYearLevel($member);

    /* ระงับโดย Admin */
    if ($manualStatus === 'suspended') {
        return [
            'code' => 'suspended',
            'text' => 'ระงับสมาชิก',
            'year_level' => $yearLevel
        ];
    }

    /* ปิดใช้งานโดย Admin */
    if ($manualStatus === 'inactive') {
        return [
            'code' => 'inactive',
            'text' => 'ไม่ใช้งาน',
            'year_level' => $yearLevel
        ];
    }

    /* ไม่ทราบปีเข้า */
    if ($yearLevel === null) {
        return [
            'code' => 'unknown',
            'text' => 'ไม่พบปีที่เข้าเรียน',
            'year_level' => null
        ];
    }

    /* ข้อมูลผิด เช่น เข้าเรียนปีในอนาคต */
    if ($yearLevel < 1) {
        return [
            'code' => 'unknown',
            'text' => 'ข้อมูลปีไม่ถูกต้อง',
            'year_level' => $yearLevel
        ];
    }

    /* เกิน 4 ปี */
    if ($yearLevel > 4) {
        return [
            'code' => 'graduated',
            'text' => 'พ้นสภาพสมาชิก',
            'year_level' => $yearLevel
        ];
    }

    /* ปี 1-4 */
    return [
        'code' => 'active',
        'text' => 'สมาชิกปัจจุบัน',
        'year_level' => $yearLevel
    ];
}


/* =========================================
   แปลงจำนวนเงินเป็นตัวอักษรภาษาไทย
========================================= */

function thaiIntegerText(string $number, bool $useEtForOne = false): string
{
    $number = preg_replace('/\D/', '', $number) ?? '';
    $number = ltrim($number, '0');

    if ($number === '') {
        return 'ศูนย์';
    }

    if (strlen($number) > 6) {
        $prefix = substr($number, 0, -6);
        $suffix = substr($number, -6);
        $text = thaiIntegerText($prefix) . 'ล้าน';

        if ((int)$suffix > 0) {
            $text .= thaiIntegerText($suffix, true);
        }

        return $text;
    }

    $digits = [
        0 => '',
        1 => 'หนึ่ง',
        2 => 'สอง',
        3 => 'สาม',
        4 => 'สี่',
        5 => 'ห้า',
        6 => 'หก',
        7 => 'เจ็ด',
        8 => 'แปด',
        9 => 'เก้า'
    ];

    $units = [
        0 => '',
        1 => 'สิบ',
        2 => 'ร้อย',
        3 => 'พัน',
        4 => 'หมื่น',
        5 => 'แสน'
    ];

    $length = strlen($number);
    $text = '';

    for ($index = 0; $index < $length; $index++) {
        $digit = (int)$number[$index];
        $position = $length - $index - 1;

        if ($digit === 0) {
            continue;
        }

        if ($position === 1) {
            if ($digit === 1) {
                $text .= 'สิบ';
            } elseif ($digit === 2) {
                $text .= 'ยี่สิบ';
            } else {
                $text .= $digits[$digit] . 'สิบ';
            }

            continue;
        }

        if (
            $position === 0 &&
            $digit === 1 &&
            ($length > 1 || $useEtForOne)
        ) {
            $text .= 'เอ็ด';
            continue;
        }

        $text .= $digits[$digit] . $units[$position];
    }

    return $text;
}

function thaiBahtText($amount): string
{
    if (!is_numeric($amount)) {
        return '-';
    }

    $amount = max(0, round((float)$amount, 2));
    [$baht, $satang] = explode('.', number_format($amount, 2, '.', ''));

    $text = thaiIntegerText($baht) . 'บาท';

    if ((int)$satang === 0) {
        return $text . 'ถ้วน';
    }

    return $text . thaiIntegerText($satang) . 'สตางค์';
}


/* =========================================
   อ่านค่าตั้งค่าเว็บไซต์อย่างปลอดภัย
========================================= */

function getSiteSetting(PDO $pdo, string $key, $default = null)
{
    try {
        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM site_settings
            WHERE setting_key = ?
            LIMIT 1
        ");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null || $value === ''
            ? $default
            : $value;
    } catch (Throwable $e) {
        return $default;
    }
}


/* =========================================
   เบอร์โทรศัพท์รูปแบบกลาง
========================================= */

function normalizePhoneNumber($phone)
{
    $phone = trim((string)$phone);

    if ($phone === '') {
        return null;
    }

    $phone = strtr($phone, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9'
    ]);

    $phone = preg_replace('/[\s\-().]/u', '', $phone);

    if (!preg_match('/^\+?[0-9]{9,15}$/', $phone)) {
        return false;
    }

    return $phone;
}

function formatPhoneNumber($phone): string
{
    $normalized = normalizePhoneNumber($phone);

    if (!is_string($normalized)) {
        return '';
    }

    if (preg_match('/^0[0-9]{9}$/', $normalized)) {
        return substr($normalized, 0, 3) . '-' .
            substr($normalized, 3, 3) . '-' .
            substr($normalized, 6, 4);
    }

    if (preg_match('/^0[0-9]{8}$/', $normalized)) {
        return substr($normalized, 0, 2) . '-' .
            substr($normalized, 2, 3) . '-' .
            substr($normalized, 5, 4);
    }

    return $normalized;
}
