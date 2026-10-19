<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// attendance_save.php
// หน้านี้ใช้สำหรับบันทึกผลการเช็กชื่อผู้เข้าร่วมกิจกรรม

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');


// =========================================
// ต้องเป็น POST
// =========================================

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header(
        'Location: index.php'
    );

    exit;
}


// =========================================
// รับข้อมูล
// =========================================

$activityId =
    (int)(
        $_POST['activity_id']
        ?? 0
    );

$csrfToken =
    $_POST['csrf_token']
    ?? '';

$userIds =
    $_POST['user_id']
    ?? [];

$statuses =
    $_POST['status']
    ?? [];

$notes =
    $_POST['note']
    ?? [];

$checkouts =
    $_POST['checkout']
    ?? [];


// =========================================
// ตรวจ CSRF
// =========================================

if (
    empty(
        $_SESSION['attendance_csrf']
    )
    ||
    !hash_equals(
        $_SESSION['attendance_csrf'],
        $csrfToken
    )
) {

    $_SESSION['attendance_error'] =
        'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';

    header(
        'Location: attendance.php?activity_id='
        . $activityId
    );

    exit;
}


// =========================================
// ตรวจ Activity
// =========================================

if ($activityId <= 0) {

    $_SESSION['attendance_error'] =
        'ไม่พบกิจกรรม';

    header(
        'Location: index.php'
    );

    exit;
}


$allowedStatuses = [
    'present',
    'late',
    'absent'
];


try {


    $pdo->beginTransaction();


    foreach ($userIds as $userIdValue) {


        $userId =
            (int)$userIdValue;


        if ($userId <= 0) {
            continue;
        }


        // =====================================
        // ตรวจว่าผู้ใช้นี้สมัครกิจกรรมจริง
        // =====================================

        $checkStmt =
            $pdo->prepare("
                SELECT id

                FROM activity_registrations

                WHERE activity_id = ?

                AND user_id = ?

                AND registration_status =
                    'registered'

                LIMIT 1
            ");


        $checkStmt->execute([
            $activityId,
            $userId
        ]);


        if (!$checkStmt->fetch()) {
            continue;
        }


        // =====================================
        // สถานะ
        // =====================================

        $status =
            $statuses[$userId]
            ?? '';


        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            continue;
        }


        $note =
            trim(
                $notes[$userId]
                ?? ''
            );


        if (
            mb_strlen($note) > 255
        ) {

            $note =
                mb_substr(
                    $note,
                    0,
                    255
                );
        }


        // =====================================
        // หา Attendance เดิม
        // =====================================

        $existingStmt =
            $pdo->prepare("
                SELECT
                    id,
                    check_in_time,
                    check_out_time

                FROM activity_attendance

                WHERE activity_id = ?

                AND user_id = ?

                LIMIT 1
            ");


        $existingStmt->execute([
            $activityId,
            $userId
        ]);


        $existing =
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            );


        $now =
            date(
                'Y-m-d H:i:s'
            );


        // =====================================
        // เวลาเช็กเข้า
        // =====================================

        $checkInTime = null;


        if (
            $status === 'present'
            ||
            $status === 'late'
        ) {

            if (
                $existing
                &&
                !empty(
                    $existing[
                        'check_in_time'
                    ]
                )
            ) {

                $checkInTime =
                    $existing[
                        'check_in_time'
                    ];

            } else {

                $checkInTime =
                    $now;
            }
        }


        // =====================================
        // เวลาเช็กออก
        // =====================================

        $checkOutTime = null;


        if (
            isset(
                $checkouts[$userId]
            )
        ) {

            if (
                $existing
                &&
                !empty(
                    $existing[
                        'check_out_time'
                    ]
                )
            ) {

                $checkOutTime =
                    $existing[
                        'check_out_time'
                    ];

            } else {

                $checkOutTime =
                    $now;
            }
        }


        // ถ้าไม่มา ไม่ต้องมีเวลา
        if ($status === 'absent') {

            $checkInTime = null;
            $checkOutTime = null;
        }


        // =====================================
        // UPDATE
        // =====================================

        if ($existing) {


            $stmt =
                $pdo->prepare("
                    UPDATE activity_attendance

                    SET
                        attendance_status = ?,
                        check_in_time = ?,
                        check_out_time = ?,
                        note = ?,
                        recorded_by = ?

                    WHERE id = ?
                ");


            $stmt->execute([

                $status,

                $checkInTime,

                $checkOutTime,

                $note !== ''
                    ? $note
                    : null,

                (int)$_SESSION[
                    'user_id'
                ],

                (int)$existing['id']
            ]);


        } else {


            // =================================
            // INSERT
            // =================================

            $stmt =
                $pdo->prepare("
                    INSERT INTO
                        activity_attendance
                    (
                        activity_id,
                        user_id,
                        attendance_status,
                        check_in_time,
                        check_out_time,
                        note,
                        recorded_by
                    )

                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?
                    )
                ");


            $stmt->execute([

                $activityId,

                $userId,

                $status,

                $checkInTime,

                $checkOutTime,

                $note !== ''
                    ? $note
                    : null,

                (int)$_SESSION[
                    'user_id'
                ]
            ]);
        }
    }


    $pdo->commit();


    $_SESSION['attendance_success'] =
        'บันทึกการเช็กชื่อเรียบร้อยแล้ว';


    header(
        'Location: attendance.php?activity_id='
        . $activityId
    );

    exit;


} catch (Throwable $e) {


    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    error_log(
        'ATTENDANCE ERROR: '
        . $e->getMessage()
    );


    $_SESSION['attendance_error'] =
        'ไม่สามารถบันทึกการเช็กชื่อได้ กรุณาลองใหม่อีกครั้ง';


    header(
        'Location: attendance.php?activity_id='
        . $activityId
    );

    exit;
}