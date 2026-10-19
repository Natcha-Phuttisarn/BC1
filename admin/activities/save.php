<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// save.php
// หน้านี้ใช้สำหรับตรวจสอบและบันทึกกิจกรรมใหม่
// รวมถึงอัปโหลดรูปหน้าปกและไฟล์แนบกิจกรรม

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/db.php';

date_default_timezone_set('Asia/Bangkok');


// =========================================
// ต้องเป็น POST
// =========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: create.php');
    exit;
}

$sessionCsrfToken = $_SESSION['admin_activity_create_csrf'] ?? null;
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
    echo 'คำขอไม่ถูกต้อง กรุณากลับไปยังหน้าเพิ่มกิจกรรมและลองใหม่อีกครั้ง';
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

$postedStatus =
    trim($_POST['publish_status'] ?? 'draft');

$action =
    trim($_POST['action'] ?? 'draft');


// =========================================
// เก็บข้อมูลเดิม
// ถ้าผิดจะไม่ต้องกรอกใหม่ทั้งหมด
// =========================================

$_SESSION['activity_old'] = [

    'title' =>
        $title,

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
        $postedStatus
];


// =========================================
// ฟังก์ชันกลับหน้าฟอร์มเมื่อ Error
// =========================================

function activityFail(
    string $message
): void {

    $_SESSION['activity_error'] =
        $message;

    header(
        'Location: create.php'
    );

    exit;
}


// =========================================
// ตรวจข้อมูลจำเป็น
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

    activityFail(
        'กรุณากรอกข้อมูลที่จำเป็นให้ครบ'
    );
}


// =========================================
// ตรวจรูปแบบวันที่
// =========================================

$activityStartTimestamp =
    strtotime(
        $activityDate .
        ' ' .
        $startTime
    );


$activityEndTimestamp =
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
    $activityStartTimestamp === false ||
    $activityEndTimestamp === false
) {

    activityFail(
        'วันหรือเวลาจัดกิจกรรมไม่ถูกต้อง'
    );
}


if (
    $openTimestamp === false ||
    $closeTimestamp === false
) {

    activityFail(
        'วันหรือเวลารับสมัครไม่ถูกต้อง'
    );
}


// =========================================
// ตรวจเวลาเริ่มและสิ้นสุด
// =========================================

if (
    $activityEndTimestamp <=
    $activityStartTimestamp
) {

    activityFail(
        'เวลาสิ้นสุดกิจกรรมต้องมากกว่าเวลาเริ่ม'
    );
}


// =========================================
// ตรวจวันรับสมัคร
// =========================================

if (
    $closeTimestamp <=
    $openTimestamp
) {

    activityFail(
        'วันปิดรับสมัครต้องอยู่หลังวันเปิดรับสมัคร'
    );
}


// =========================================
// วันปิดสมัครไม่ควรเลยเวลาเริ่มกิจกรรม
// =========================================

if (
    $closeTimestamp >
    $activityStartTimestamp
) {

    activityFail(
        'วันปิดรับสมัครต้องไม่เกินเวลาเริ่มกิจกรรม'
    );
}


// =========================================
// จำนวนผู้เข้าร่วม
// =========================================

if ($maxParticipants !== '') {

    if (
        !ctype_digit(
            $maxParticipants
        ) ||
        (int)$maxParticipants < 1
    ) {

        activityFail(
            'จำนวนผู้เข้าร่วมต้องเป็นตัวเลขและมากกว่า 0'
        );
    }
}


// =========================================
// สถานะ
// =========================================

// ปุ่มเผยแพร่
if ($action === 'publish') {

    $publishStatus =
        'published';

}
// ปุ่มฉบับร่าง
elseif ($action === 'draft') {

    $publishStatus =
        'draft';

}
// กรณีอื่นใช้ Select
else {

    $allowedStatuses = [
        'draft',
        'published',
        'hidden'
    ];

    $publishStatus =
        in_array(
            $postedStatus,
            $allowedStatuses,
            true
        )
        ? $postedStatus
        : 'draft';
}


$publishedAt =
    $publishStatus === 'published'
    ? date('Y-m-d H:i:s')
    : null;


// =========================================
// เตรียมโฟลเดอร์ Upload
// =========================================

$coverDirectory =
    __DIR__ .
    '/../../uploads/activities/covers';


$attachmentDirectory =
    __DIR__ .
    '/../../uploads/activity_files';


if (!is_dir($coverDirectory)) {

    if (
        !mkdir(
            $coverDirectory,
            0775,
            true
        ) &&
        !is_dir($coverDirectory)
    ) {

        activityFail(
            'ไม่สามารถสร้างโฟลเดอร์เก็บรูปกิจกรรมได้'
        );
    }
}


if (!is_dir($attachmentDirectory)) {

    if (
        !mkdir(
            $attachmentDirectory,
            0775,
            true
        ) &&
        !is_dir($attachmentDirectory)
    ) {

        activityFail(
            'ไม่สามารถสร้างโฟลเดอร์เก็บไฟล์แนบได้'
        );
    }
}


// =========================================
// ตัวแปรสำหรับลบไฟล์
// หากบันทึกฐานข้อมูลไม่สำเร็จ
// =========================================

$uploadedAbsoluteFiles = [];

$coverImageName = null;


// =========================================
// เริ่มบันทึก
// =========================================

try {

    $pdo->beginTransaction();


    // =====================================================
    // รูปหน้าปก
    // =====================================================

    if (
        isset($_FILES['cover_image']) &&
        $_FILES['cover_image']['error']
            !== UPLOAD_ERR_NO_FILE
    ) {


        $coverFile =
            $_FILES['cover_image'];


        // Error Upload
        if (
            $coverFile['error']
            !== UPLOAD_ERR_OK
        ) {

            throw new Exception(
                'อัปโหลดรูปหน้าปกไม่สำเร็จ'
            );
        }


        // ขนาด 5MB
        if (
            $coverFile['size'] >
            5 * 1024 * 1024
        ) {

            throw new Exception(
                'รูปหน้าปกต้องมีขนาดไม่เกิน 5 MB'
            );
        }


        // ต้องเป็นไฟล์ที่ Upload จริง
        if (
            !is_uploaded_file(
                $coverFile['tmp_name']
            )
        ) {

            throw new Exception(
                'ไฟล์รูปหน้าปกไม่ถูกต้อง'
            );
        }


        // ตรวจ MIME จริง
        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );


        $coverMime =
            $finfo->file(
                $coverFile['tmp_name']
            );


        $allowedCoverTypes = [

            'image/jpeg' =>
                'jpg',

            'image/png' =>
                'png',

            'image/webp' =>
                'webp'
        ];


        if (
            !isset(
                $allowedCoverTypes[
                    $coverMime
                ]
            )
        ) {

            throw new Exception(
                'รูปหน้าปกรองรับเฉพาะ JPG, PNG และ WEBP'
            );
        }


        // ตรวจว่าเปิดเป็นรูปได้จริง
        $imageInfo =
            @getimagesize(
                $coverFile['tmp_name']
            );


        if ($imageInfo === false) {

            throw new Exception(
                'ไฟล์หน้าปกไม่ใช่รูปภาพที่ถูกต้อง'
            );
        }


        $coverExtension =
            $allowedCoverTypes[
                $coverMime
            ];


        // ตั้งชื่อไฟล์ใหม่
        $coverImageName =
            'cover_' .
            date('YmdHis') .
            '_' .
            bin2hex(
                random_bytes(6)
            ) .
            '.' .
            $coverExtension;


        $coverAbsolutePath =
            $coverDirectory .
            DIRECTORY_SEPARATOR .
            $coverImageName;


        // ย้ายไฟล์
        if (
            !move_uploaded_file(
                $coverFile['tmp_name'],
                $coverAbsolutePath
            )
        ) {

            throw new Exception(
                'ไม่สามารถบันทึกรูปหน้าปกได้'
            );
        }


        $uploadedAbsoluteFiles[] =
            $coverAbsolutePath;
    }


    // =====================================================
    // บันทึกกิจกรรม
    // =====================================================

    $stmt = $pdo->prepare("
        INSERT INTO activities
        (
            title,
            short_description,
            description,
            cover_image,
            location,
            activity_date,
            start_time,
            end_time,
            registration_open,
            registration_close,
            max_participants,
            publish_status,
            created_by,
            published_at
        )

        VALUES
        (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ");


    $stmt->execute([

        $title,

        $shortDescription !== ''
            ? $shortDescription
            : null,

        $description,

        $coverImageName,

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

        $maxParticipants !== ''
            ? (int)$maxParticipants
            : null,

        $publishStatus,

        (int)$_SESSION['user_id'],

        $publishedAt
    ]);


    $activityId =
        (int)$pdo->lastInsertId();


    // =====================================================
    // ไฟล์แนบหลายไฟล์
    // =====================================================

    if (
        isset($_FILES['attachments']) &&
        isset($_FILES['attachments']['name']) &&
        is_array(
            $_FILES['attachments']['name']
        )
    ) {


        $attachmentCount =
            count(
                $_FILES['attachments']['name']
            );


        // Extension ที่ห้าม Upload
        // เพราะโฟลเดอร์อยู่ในเว็บไซต์
        $blockedExtensions = [

            'php',
            'php3',
            'php4',
            'php5',
            'php7',
            'php8',
            'phtml',
            'phar',

            'cgi',
            'pl',
            'py',
            'sh',

            'htaccess',
            'htpasswd',

            'exe',
            'com',
            'bat',
            'cmd'
        ];


        for (
            $i = 0;
            $i < $attachmentCount;
            $i++
        ) {


            $uploadError =
                $_FILES['attachments']['error'][$i];


            // ไม่มีไฟล์
            if (
                $uploadError ===
                UPLOAD_ERR_NO_FILE
            ) {

                continue;
            }


            // Upload Error
            if (
                $uploadError !==
                UPLOAD_ERR_OK
            ) {

                throw new Exception(
                    'มีไฟล์แนบบางไฟล์อัปโหลดไม่สำเร็จ'
                );
            }


            $originalName =
                basename(
                    $_FILES['attachments']['name'][$i]
                );


            $temporaryPath =
                $_FILES['attachments']['tmp_name'][$i];


            $fileSize =
                (int)$_FILES['attachments']['size'][$i];


            // ขนาด 20MB
            if (
                $fileSize >
                20 * 1024 * 1024
            ) {

                throw new Exception(
                    'ไฟล์ "' .
                    $originalName .
                    '" มีขนาดเกิน 20 MB'
                );
            }


            if (
                !is_uploaded_file(
                    $temporaryPath
                )
            ) {

                throw new Exception(
                    'ไฟล์ "' .
                    $originalName .
                    '" ไม่ถูกต้อง'
                );
            }


            // Extension
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

                throw new Exception(
                    'ไม่อนุญาตให้อัปโหลดไฟล์ประเภท .' .
                    $extension
                );
            }


            // MIME
            $fileFinfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mimeType =
                $fileFinfo->file(
                    $temporaryPath
                );


            if (
                !$mimeType
            ) {

                $mimeType =
                    'application/octet-stream';
            }


            // ตั้งชื่อใหม่
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


            // ย้ายไฟล์
            if (
                !move_uploaded_file(
                    $temporaryPath,
                    $absolutePath
                )
            ) {

                throw new Exception(
                    'ไม่สามารถบันทึกไฟล์ "' .
                    $originalName .
                    '" ได้'
                );
            }


            $uploadedAbsoluteFiles[] =
                $absolutePath;


            $filePath =
                'uploads/activity_files/' .
                $storedName;


            // =================================
            // บันทึกข้อมูลไฟล์
            // =================================

            $fileStmt = $pdo->prepare("
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

                $mimeType,

                $extension,

                $fileSize
            ]);
        }
    }


    // =====================================================
    // สำเร็จ
    // =====================================================

    $pdo->commit();

    logAdminAction($pdo, 'create', 'activity', $activityId, 'เพิ่มกิจกรรม: ' . $title, null, ['title'=>$title, 'publish_status'=>$publishStatus]);


    // ล้างข้อมูลเก่า
    unset(
        $_SESSION['activity_old']
    );


    if (
        $publishStatus ===
        'published'
    ) {

        $_SESSION['activity_success'] =
            'เผยแพร่กิจกรรมเรียบร้อยแล้ว';

    } elseif (
        $publishStatus ===
        'hidden'
    ) {

        $_SESSION['activity_success'] =
            'บันทึกกิจกรรมแบบซ่อนเรียบร้อยแล้ว';

    } else {

        $_SESSION['activity_success'] =
            'บันทึกกิจกรรมเป็นฉบับร่างเรียบร้อยแล้ว';
    }


    header(
        'Location: index.php'
    );

    exit;


// =========================================================
// ERROR
// =========================================================

} catch (Throwable $e) {


    if (
        isset($pdo) &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    // ลบไฟล์ที่อัปโหลดไปแล้ว
    foreach (
        $uploadedAbsoluteFiles
        as $uploadedFile
    ) {

        if (
            is_file(
                $uploadedFile
            )
        ) {

            @unlink(
                $uploadedFile
            );
        }
    }


    error_log(
        'ACTIVITY SAVE ERROR: ' .
        $e->getMessage()
    );


    $_SESSION['activity_error'] =
        'เกิดข้อผิดพลาด: ' .
        $e->getMessage();


    header(
        'Location: create.php'
    );

    exit;
}
