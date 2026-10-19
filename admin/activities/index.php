<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// index.php
// หน้านี้ใช้สำหรับแสดงและจัดการกิจกรรมทั้งหมดของผู้ดูแลระบบ

require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'จัดการกิจกรรม';


// =========================================
// CSRF สำหรับลบกิจกรรม
// =========================================

if (empty($_SESSION['activity_delete_csrf'])) {

    $_SESSION['activity_delete_csrf'] =
        bin2hex(random_bytes(32));
}


// =========================================
// ข้อความหลังบันทึก/แก้ไข/ลบกิจกรรม
// =========================================

$success =
    $_SESSION['activity_success']
    ?? '';

$error =
    $_SESSION['activity_error']
    ?? '';

unset(
    $_SESSION['activity_success'],
    $_SESSION['activity_error']
);


// =========================================
// ดึงกิจกรรมทั้งหมด
// =========================================

$stmt = $pdo->query("
    SELECT
        a.*,

        (
            SELECT COUNT(*)

            FROM activity_registrations ar

            WHERE ar.activity_id = a.id

            AND ar.registration_status =
                'registered'

        ) AS registered_count,

        (
            SELECT COUNT(*)

            FROM activity_registrations ar

            WHERE ar.activity_id = a.id

            AND ar.registration_status =
                'cancelled'

        ) AS cancelled_count

    FROM activities a

    ORDER BY a.created_at DESC
");


$activities =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>


<section class="panel full-panel">


    <!-- =====================================
         HEADER
    ====================================== -->

    <div class="panel-head">

        <div>

            <small>
                ACTIVITIES
            </small>

            <h3>
                กิจกรรมทั้งหมด
            </h3>

        </div>


        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="create.php" class="primary-btn"><i class="fa-solid fa-plus"></i> เพิ่มกิจกรรม</a>
        </div>

    </div>


    <!-- =====================================
         SUCCESS
    ====================================== -->

    <?php if ($success): ?>

        <div class="form-alert success">

            <i class="fa-solid fa-circle-check"></i>

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================
         ERROR
    ====================================== -->

    <?php if ($error): ?>

        <div class="form-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================
         ตารางกิจกรรม
    ====================================== -->

    <?php if (!empty($activities)): ?>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            กิจกรรม
                        </th>

                        <th>
                            วันที่จัด
                        </th>

                        <th>
                            เวลา
                        </th>

                        <th>
                            สถานที่
                        </th>

                        <th>
                            ผู้สมัคร
                        </th>

                        <th>
                            สถานะ
                        </th>

                        <th>
                            จัดการ
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach ($activities as $activity): ?>


                        <?php

                        $registeredCount =
                            (int)(
                                $activity[
                                    'registered_count'
                                ]
                                ?? 0
                            );


                        $cancelledCount =
                            (int)(
                                $activity[
                                    'cancelled_count'
                                ]
                                ?? 0
                            );


                        $maxParticipants =
                            !empty(
                                $activity[
                                    'max_participants'
                                ]
                            )
                            ? (int)$activity[
                                'max_participants'
                            ]
                            : null;


                        // =================================
                        // วันที่
                        // =================================

                        $activityDate =
                            !empty(
                                $activity[
                                    'activity_date'
                                ]
                            )
                            ? date(
                                'd/m/Y',
                                strtotime(
                                    $activity[
                                        'activity_date'
                                    ]
                                )
                            )
                            : '-';


                        // =================================
                        // เวลาเริ่ม
                        // =================================

                        $startTime =
                            !empty(
                                $activity[
                                    'start_time'
                                ]
                            )
                            ? date(
                                'H:i',
                                strtotime(
                                    $activity[
                                        'start_time'
                                    ]
                                )
                            )
                            : '-';


                        // =================================
                        // เวลาสิ้นสุด
                        // =================================

                        $endTime =
                            !empty(
                                $activity[
                                    'end_time'
                                ]
                            )
                            ? date(
                                'H:i',
                                strtotime(
                                    $activity[
                                        'end_time'
                                    ]
                                )
                            )
                            : '-';


                        // =================================
                        // รูปปก
                        // =================================

                        if (
                            !empty(
                                $activity[
                                    'cover_image'
                                ]
                            )
                        ) {

                            $coverImage =
                                ('' . BC_BASE_PATH . '/uploads/activities/covers/') .
                                $activity[
                                    'cover_image'
                                ];

                        } else {

                            $coverImage =
                                ('' . BC_BASE_PATH . '/assets/images/logo.png');
                        }

                        ?>


                        <tr>


                            <!-- =================================
                                 กิจกรรม
                            ================================== -->

                            <td>

                                <div class="admin-activity-info">


                                    <div class="admin-activity-image">

                                        <img
                                            src="<?= htmlspecialchars(
                                                $coverImage,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            alt="<?= htmlspecialchars(
                                                $activity[
                                                    'title'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                    </div>


                                    <div class="admin-activity-text">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $activity[
                                                    'title'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                        <?php if (
                                            !empty(
                                                $activity[
                                                    'short_description'
                                                ]
                                            )
                                        ): ?>

                                            <span>

                                                <?= htmlspecialchars(
                                                    $activity[
                                                        'short_description'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>

                                        <?php endif; ?>


                                    </div>


                                </div>

                            </td>


                            <!-- =================================
                                 วันที่
                            ================================== -->

                            <td>

                                <?= $activityDate ?>

                            </td>


                            <!-- =================================
                                 เวลา
                            ================================== -->

                            <td>

                                <?= $startTime ?>


                                <?php if (
                                    $endTime !== '-'
                                ): ?>

                                    -
                                    <?= $endTime ?>

                                <?php endif; ?>


                            </td>


                            <!-- =================================
                                 สถานที่
                            ================================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $activity[
                                        'location'
                                    ]
                                    ?: '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- =================================
                                 ผู้สมัคร
                            ================================== -->

                            <td>


                                <div class="activity-registration-actions">


                                    <!-- =========================
                                         ดูรายชื่อผู้สมัคร
                                    ========================== -->

                                    <a
                                        href="<?= BC_BASE_PATH ?>/admin/registrations/index.php?activity_id=<?= (int)$activity['id'] ?>"
                                        class="activity-applicant-link"
                                        title="ดูรายชื่อผู้สมัครและผู้ที่ยกเลิก"
                                    >

                                        <strong>

                                            <?= $registeredCount ?>


                                            <?php if (
                                                $maxParticipants
                                                !== null
                                            ): ?>

                                                /
                                                <?= $maxParticipants ?>

                                            <?php endif; ?>


                                            คน

                                        </strong>


                                        <?php if (
                                            $cancelledCount > 0
                                        ): ?>

                                            <small>

                                                ยกเลิก
                                                <?= $cancelledCount ?>
                                                คน

                                            </small>

                                        <?php else: ?>

                                            <small>
                                                ดูรายชื่อผู้สมัคร
                                            </small>

                                        <?php endif; ?>


                                    </a>


                                    <!-- =========================
                                         เช็กชื่อ
                                    ========================== -->

                                    <?php if (
                                        $registeredCount > 0
                                    ): ?>


                                        <a
                                            href="<?= BC_BASE_PATH ?>/admin/registrations/attendance.php?activity_id=<?= (int)$activity['id'] ?>"
                                            class="attendance-link"
                                            title="เช็กชื่อผู้เข้าร่วมกิจกรรม"
                                        >

                                            <i class="fa-solid fa-clipboard-check"></i>

                                            เช็กชื่อ

                                        </a>


                                    <?php else: ?>


                                        <span
                                            class="attendance-link disabled"
                                            title="ยังไม่มีผู้สมัครกิจกรรม"
                                        >

                                            <i class="fa-solid fa-clipboard-check"></i>

                                            เช็กชื่อ

                                        </span>


                                    <?php endif; ?>


                                </div>


                            </td>


                            <!-- =================================
                                 สถานะ
                            ================================== -->

                            <td>


                                <?php if (
                                    $activity[
                                        'publish_status'
                                    ]
                                    === 'published'
                                ): ?>


                                    <span class="badge published">

                                        เผยแพร่

                                    </span>


                                <?php elseif (
                                    $activity[
                                        'publish_status'
                                    ]
                                    === 'draft'
                                ): ?>


                                    <span class="badge draft">

                                        ฉบับร่าง

                                    </span>


                                <?php else: ?>


                                    <span class="badge hidden">

                                        ซ่อน

                                    </span>


                                <?php endif; ?>


                            </td>


                            <!-- =================================
                                 จัดการ
                            ================================== -->

                            <td>


                                <div class="action-buttons">


                                    <!-- =========================
                                         ดู
                                    ========================== -->

                                    <?php if (
                                        $activity[
                                            'publish_status'
                                        ]
                                        === 'published'
                                    ): ?>


                                        <a
                                            href="<?= BC_BASE_PATH ?>/activities.php?id=<?= (int)$activity['id'] ?>"
                                            target="_blank"
                                            class="action-btn view"
                                            title="ดู"
                                        >

                                            <i class="fa-regular fa-eye"></i>

                                        </a>


                                    <?php else: ?>


                                        <span
                                            class="action-btn disabled"
                                            title="กิจกรรมยังไม่ได้เผยแพร่"
                                        >

                                            <i class="fa-regular fa-eye"></i>

                                        </span>


                                    <?php endif; ?>


                                    <!-- =========================
                                         แก้ไข
                                    ========================== -->

                                    <a
                                        href="edit.php?id=<?= (int)$activity['id'] ?>"
                                        class="action-btn edit"
                                        title="แก้ไข"
                                    >

                                        <i class="fa-regular fa-pen-to-square"></i>

                                    </a>


                                    <!-- =========================
                                         ลบกิจกรรม
                                    ========================== -->

                                    <?php
                                    $activityDeleteMessage =
                                        "คุณต้องการลบกิจกรรมนี้ใช่หรือไม่?\n\n" .
                                        'ชื่อกิจกรรม: "' .
                                        $activity['title'] .
                                        '"' .
                                        "\n\nการลบกิจกรรมอาจไม่สามารถกู้คืนได้ กรุณาตรวจสอบก่อนดำเนินการ";
                                    ?>

                                    <form
                                        action="delete.php"
                                        method="POST"
                                        class="delete-activity-form"
                                        data-bc-confirm
                                        data-bc-confirm-type="delete"
                                        data-bc-confirm-title="ยืนยันการลบกิจกรรม"
                                        data-bc-confirm-message="<?= htmlspecialchars(
                                            $activityDeleteMessage,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-bc-confirm-label="ลบกิจกรรม"
                                    >


                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$activity['id'] ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $_SESSION[
                                                    'activity_delete_csrf'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="action-btn delete"
                                            title="ลบกิจกรรม"
                                        >

                                            <i class="fa-solid fa-trash-can"></i>

                                        </button>


                                    </form>


                                </div>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    <?php else: ?>


        <!-- =====================================
             ไม่มีข้อมูล
        ====================================== -->

        <div class="empty">

            <i class="fa-regular fa-calendar"></i>


            <h3>

                ยังไม่มีกิจกรรม

            </h3>


            <p>

                กดปุ่ม “เพิ่มกิจกรรม”
                เพื่อสร้างกิจกรรมแรกของชมรม

            </p>


            <a
                href="create.php"
                class="primary-btn"
                style="margin-top:15px;"
            >

                <i class="fa-solid fa-plus"></i>

                เพิ่มกิจกรรม

            </a>


        </div>


    <?php endif; ?>


</section>


<style>

/* =========================================
   ผู้สมัคร
========================================= */

.activity-registration-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 7px;
}


.activity-applicant-link {
    display: inline-flex;
    flex-direction: column;
    gap: 3px;
    color: #124ea2;
    text-decoration: none;
}


.activity-applicant-link strong {
    font-size: 13px;
    font-weight: 700;
}


.activity-applicant-link small {
    color: #7b8ba2;
    font-size: 10px;
    font-weight: 500;
}


.activity-applicant-link:hover strong {
    text-decoration: underline;
}


/* =========================================
   ปุ่มเช็กชื่อ
========================================= */

.attendance-link {
    min-height: 29px;
    padding: 5px 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;

    border-radius: 7px;

    color: #1762c6;
    background: #eaf2ff;

    font-size: 10px;
    font-weight: 600;

    text-decoration: none;

    transition: .2s ease;
}


.attendance-link:hover {
    color: #0f52aa;
    background: #dbeaff;
}


.attendance-link.disabled {
    color: #a0a8b4;
    background: #f1f3f6;

    cursor: not-allowed;
}


/* =========================================
   Form ปุ่มลบ
========================================= */

.delete-activity-form {
    margin: 0;
    padding: 0;
    display: inline-flex;
}


/* =========================================
   ปุ่มลบ
========================================= */

.delete-activity-form .action-btn.delete {
    border: 0;
    font-family: inherit;
    cursor: pointer;
}

</style>


<?php
include __DIR__ . '/../includes/confirmation_modal.php';
include __DIR__ . '/../includes/admin_footer.php';
?>
