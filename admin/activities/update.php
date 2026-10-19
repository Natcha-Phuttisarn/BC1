<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// update.php
// หน้านี้ใช้สำหรับบันทึกข้อมูลกิจกรรมหลังจากผู้ดูแลแก้ไข

require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$sessionCsrfToken = $_SESSION['admin_activity_edit_csrf'] ?? null;
$submittedCsrfToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($sessionCsrfToken) ||
    $sessionCsrfToken === '' ||
    !is_string($submittedCsrfToken) ||
    $submittedCsrfToken === '' ||
    !hash_equals($sessionCsrfToken, $submittedCsrfToken)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'คำขอไม่ถูกต้อง กรุณากลับไปยังหน้าแก้ไขกิจกรรมและลองใหม่อีกครั้ง';
    exit;
}


$activityId =
    (int)($_POST['id'] ?? 0);


if ($activityId <= 0) {

    $_SESSION['activity_error'] =
        'ไม่พบรหัสกิจกรรม';

    header('Location: index.php');
    exit;
}


// =========================================
// รับข้อมูล
// =========================================

$title =
    trim($_POST['title'] ?? '');

$shortDescription =
    trim($_POST['short_description'] ?? '');

$description =
    trim($_POST['description'] ?? '');

$location =
    trim($_POST['location'] ?? '');

$activityDate =
    trim($_POST['activity_date'] ?? '');

$startTime =
    trim($_POST['start_time'] ?? '');

$endTime =
    trim($_POST['end_time'] ?? '');

$registrationOpen =
    trim($_POST['registration_open'] ?? '');

$registrationClose =
    trim($_POST['registration_close'] ?? '');

$maxParticipants =
    trim($_POST['max_participants'] ?? '');

$publishStatus =
    trim($_POST['publish_status'] ?? 'draft');


// =========================================
// เก็บข้อมูลเดิม
// =========================================

$_SESSION['activity_edit_old'] = [

    'title' => $title,

    'short_description' =>
        $shortDescription,

    'description' =>
        $description,

    'location' =>
        $location,

    'activity_date' =>
        $activityDate,

    'start_time' =>
        $startTime,

    'end_time' =>
        $endTime,

    'registration_open' =>
        $registrationOpen,

    'registration_close' =>
        $registrationClose,

    'max_participants' =>
        $maxParticipants,

    'publish_status' =>
        $publishStatus
];


function updateActivityFail(
    string $message,
    int $activityId
): void {

    $_SESSION['activity_error'] =
        $message;

    header(
        'Location: edit.php?id=' .
        $activityId
    );

    exit;
}


// =========================================
// ตรวจข้อมูล
// =========================================

if (
    $title === '' ||
    $description === '' ||
    $location === '' ||
    $activityDate === '' ||
    $startTime === '' ||
    $endTime === '' ||
    $registrationOpen === '' ||
    $registrationClose === ''
) {

    updateActivityFail(
        'กรุณากรอกข้อมูลที่จำเป็นให้ครบ',
        $activityId
    );
}


$activityStart =
    strtotime(
        $activityDate .
        ' ' .
        $startTime
    );

$activityEnd =
    strtotime(
        $activityDate .
        ' ' .
        $endTime
    );

$openTimestamp =
    strtotime(
        str_replace(
            'T',
            ' ',
            $registrationOpen
        )
    );

$closeTimestamp =
    strtotime(
        str_replace(
            'T',
            ' ',
            $registrationClose
        )
    );


if (
    $activityStart === false ||
    $activityEnd === false
) {

    updateActivityFail(
        'วันที่หรือเวลาจัดกิจกรรมไม่ถูกต้อง',
        $activityId
    );
}


if (
    $activityEnd <=
    $activityStart
) {

    updateActivityFail(
        'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม',
        $activityId
    );
}


if (
    $openTimestamp === false ||
    $closeTimestamp === false
) {

    updateActivityFail(
        'วันหรือเวลารับสมัครไม่ถูกต้อง',
        $activityId
    );
}


if (
    $closeTimestamp <=
    $openTimestamp
) {

    updateActivityFail(
        'วันปิดรับสมัครต้องอยู่หลังวันเปิดรับสมัคร',
        $activityId
    );
}


if (
    $closeTimestamp >
    $activityStart
) {

    updateActivityFail(
        'วันปิดรับสมัครต้องไม่เกินเวลาเริ่มกิจกรรม',
        $activityId
    );
}


// =========================================
// จำนวนรับ
// =========================================

$maxValue = null;

if ($maxParticipants !== '') {

    if (
        !ctype_digit($maxParticipants) ||
        (int)$maxParticipants < 1
    ) {

        updateActivityFail(
            'จำนวนผู้เข้าร่วมต้องมากกว่า 0',
            $activityId
        );
    }

    $maxValue =
        (int)$maxParticipants;
}


// =========================================
// สถานะ
// =========================================

if (
    !in_array(
        $publishStatus,
        [
            'published',
            'draft',
            'hidden'
        ],
        true
    )
) {

    $publishStatus = 'draft';
}


// =========================================
// กิจกรรมเดิม
// =========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM activities
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$activityId]);

$activity =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$activity) {

    updateActivityFail(
        'ไม่พบกิจกรรมที่ต้องการแก้ไข',
        $activityId
    );
}


// =========================================
// จำนวนผู้สมัครปัจจุบัน
// =========================================

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM activity_registrations
    WHERE activity_id = ?
    AND registration_status = 'registered'
");

$countStmt->execute([$activityId]);

$currentRegistered =
    (int)$countStmt->fetchColumn();


if (
    $maxValue !== null &&
    $maxValue < $currentRegistered
) {

    updateActivityFail(
        'จำนวนรับใหม่ต้องไม่น้อยกว่าจำนวนผู้สมัครปัจจุบัน '
        . $currentRegistered .
        ' คน',
        $activityId
    );
}


// =========================================
// โฟลเดอร์
// =========================================

$coverDirectory =
    __DIR__ .
    '/../../uploads/activities/covers';

$attachmentDirectory =
    __DIR__ .
    '/../../uploads/activity_files';


if (!is_dir($coverDirectory)) {

    mkdir(
        $coverDirectory,
        0775,
        true
    );
}


if (!is_dir($attachmentDirectory)) {

    mkdir(
        $attachmentDirectory,
        0775,
        true
    );
}


$coverName =
    $activity['cover_image']
    ?? null;

$uploadedFiles = [];

$oldCoverFile = null;


try {

    $pdo->beginTransaction();


    // =========================================
    // เปลี่ยนรูปปก
    // =========================================

    if (
        isset($_FILES['cover_image']) &&
        $_FILES['cover_image']['error']
            !== UPLOAD_ERR_NO_FILE
    ) {

        $file =
            $_FILES['cover_image'];


        if (
            $file['error']
            !== UPLOAD_ERR_OK
        ) {

            throw new RuntimeException(
                'อัปโหลดรูปหน้าปกไม่สำเร็จ'
            );
        }


        if (
            $file['size'] >
            5 * 1024 * 1024
        ) {

            throw new RuntimeException(
                'รูปหน้าปกต้องมีขนาดไม่เกิน 5 MB'
            );
        }


        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );

        $mime =
            $finfo->file(
                $file['tmp_name']
            );


        $allowedImages = [

            'image/jpeg' => 'jpg',

            'image/png' => 'png',

            'image/webp' => 'webp'
        ];


        if (
            !isset(
                $allowedImages[$mime]
            )
        ) {

            throw new RuntimeException(
                'รูปหน้าปกรองรับเฉพาะ JPG, PNG และ WEBP'
            );
        }


        $coverName =
            'cover_' .
            date('YmdHis') .
            '_' .
            bin2hex(
                random_bytes(6)
            ) .
            '.' .
            $allowedImages[$mime];


        $coverPath =
            $coverDirectory .
            DIRECTORY_SEPARATOR .
            $coverName;


        if (
            !move_uploaded_file(
                $file['tmp_name'],
                $coverPath
            )
        ) {

            throw new RuntimeException(
                'ไม่สามารถบันทึกรูปหน้าปกใหม่ได้'
            );
        }


        $uploadedFiles[] =
            $coverPath;


        if (
            !empty(
                $activity['cover_image']
            )
        ) {

            $oldCoverFile =
                $coverDirectory .
                DIRECTORY_SEPARATOR .
                $activity['cover_image'];
        }
    }


    // =========================================
    // UPDATE กิจกรรม
    // =========================================

    $stmt = $pdo->prepare("
        UPDATE activities

        SET
            title = ?,
            short_description = ?,
            description = ?,
            cover_image = ?,
            location = ?,
            activity_date = ?,
            start_time = ?,
            end_time = ?,
            registration_open = ?,
            registration_close = ?,
            max_participants = ?,
            publish_status = ?,

            published_at =
                CASE

                    WHEN ? = 'published'
                    AND published_at IS NULL

                    THEN NOW()

                    ELSE published_at

                END

        WHERE id = ?
    ");


    $stmt->execute([

        $title,

        $shortDescription !== ''
            ? $shortDescription
            : null,

        $description,

        $coverName,

        $location,

        $activityDate,

        $startTime,

        $endTime,

        date(
            'Y-m-d H:i:s',
            $openTimestamp
        ),

        date(
            'Y-m-d H:i:s',
            $closeTimestamp
        ),

        $maxValue,

        $publishStatus,

        $publishStatus,

        $activityId
    ]);


    // =========================================
    // เพิ่มไฟล์แนบ
    // =========================================

    if (
        isset($_FILES['attachments']) &&
        isset(
            $_FILES[
                'attachments'
            ]['name']
        ) &&
        is_array(
            $_FILES[
                'attachments'
            ]['name']
        )
    ) {

        $blockedExtensions = [
            'php',
            'php3',
            'php4',
            'php5',
            'php7',
            'php8',
            'phtml',
            'phar',
            'exe',
            'bat',
            'cmd',
            'com',
            'sh',
            'cgi'
        ];


        foreach (
            $_FILES['attachments']['name']
            as $index => $originalName
        ) {

            $uploadError =
                $_FILES[
                    'attachments'
                ]['error'][$index]
                ?? UPLOAD_ERR_NO_FILE;


            if (
                $uploadError ===
                UPLOAD_ERR_NO_FILE
            ) {

                continue;
            }


            if (
                $uploadError !==
                UPLOAD_ERR_OK
            ) {

                throw new RuntimeException(
                    'มีไฟล์แนบบางไฟล์อัปโหลดไม่สำเร็จ'
                );
            }


            $tmp =
                $_FILES[
                    'attachments'
                ]['tmp_name'][$index];


            $size =
                (int)$_FILES[
                    'attachments'
                ]['size'][$index];


            if (
                $size >
                20 * 1024 * 1024
            ) {

                throw new RuntimeException(
                    'ไฟล์แนบต้องมีขนาดไม่เกิน 20 MB'
                );
            }


            $originalName =
                basename($originalName);


            $extension =
                strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                );


            if (
                in_array(
                    $extension,
                    $blockedExtensions,
                    true
                )
            ) {

                throw new RuntimeException(
                    'ไม่อนุญาตไฟล์ประเภท .'
                    . $extension
                );
            }


            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mime =
                $finfo->file($tmp)
                ?: 'application/octet-stream';


            $storedName =
                'file_' .
                date('YmdHis') .
                '_' .
                bin2hex(
                    random_bytes(6)
                );


            if ($extension !== '') {

                $storedName .=
                    '.' .
                    $extension;
            }


            $absolutePath =
                $attachmentDirectory .
                DIRECTORY_SEPARATOR .
                $storedName;


            if (
                !move_uploaded_file(
                    $tmp,
                    $absolutePath
                )
            ) {

                throw new RuntimeException(
                    'ไม่สามารถบันทึกไฟล์แนบได้'
                );
            }


            $uploadedFiles[] =
                $absolutePath;


            $filePath =
                'uploads/activity_files/' .
                $storedName;


            $fileStmt =
                $pdo->prepare("
                    INSERT INTO activity_files
                    (
                        activity_id,
                        original_name,
                        stored_name,
                        file_path,
                        mime_type,
                        file_extension,
                        file_size
                    )

                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?
                    )
                ");


            $fileStmt->execute([

                $activityId,

                $originalName,

                $storedName,

                $filePath,

                $mime,

                $extension,

                $size
            ]);
        }
    }


    // =========================================
    // สำเร็จ
    // =========================================

    $pdo->commit();

    logAdminAction($pdo, 'update', 'activity', $activityId, 'แก้ไขกิจกรรม: ' . $title, $activity, ['title'=>$title, 'publish_status'=>$publishStatus]);


    // ลบรูปเก่าเฉพาะเมื่อบันทึกสำเร็จแล้ว
    if (
        $oldCoverFile &&
        is_file($oldCoverFile)
    ) {

        @unlink($oldCoverFile);
    }


    unset(
        $_SESSION['activity_edit_old']
    );


    $_SESSION['activity_success'] =
        'แก้ไขข้อมูลกิจกรรมเรียบร้อยแล้ว';


    header(
        'Location: index.php'
    );

    exit;


} catch (Throwable $e) {


    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    foreach (
        $uploadedFiles as $file
    ) {

        if (is_file($file)) {
            @unlink($file);
        }
    }


    $_SESSION['activity_error'] =
        'เกิดข้อผิดพลาด: ' .
        $e->getMessage();


    header(
        'Location: edit.php?id=' .
        $activityId
    );

    exit;
}
