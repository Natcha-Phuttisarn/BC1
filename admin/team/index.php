<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// index.php
// หน้านี้ใช้สำหรับดูและจัดการข้อมูลบุคลากรทั้งหมดในระบบ Admin

require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'จัดการบุคลากร';

$success = $_SESSION['team_success'] ?? '';
$error = $_SESSION['team_error'] ?? '';

unset($_SESSION['team_success']);
unset($_SESSION['team_error']);

if (empty($_SESSION['team_csrf'])) {
    $_SESSION['team_csrf'] = bin2hex(random_bytes(32));
}


// =========================================
// ดึงข้อมูลบุคลากร
// =========================================

$stmt = $pdo->query("
    SELECT *
    FROM personnel
    ORDER BY
        CASE status
            WHEN 'active' THEN 1
            WHEN 'hidden' THEN 2
            ELSE 3
        END,
        CASE
            WHEN type = 'advisor' THEN 1
            ELSE 2
        END,
        display_order ASC,
        id ASC
");

$personnel = $stmt->fetchAll();


// =========================================
// สรุปจำนวน
// =========================================

$total = count($personnel);

$advisorCount = 0;
$committeeCount = 0;
$activeCount = 0;

foreach ($personnel as $person) {

    if ($person['type'] === 'advisor') {
        $advisorCount++;
    }

    if ($person['type'] === 'committee') {
        $committeeCount++;
    }

    if ($person['status'] === 'active') {
        $activeCount++;
    }
}


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';

?>


<div class="team-stat-grid">

    <div class="team-stat-card main">

        <span>
            บุคลากรทั้งหมด
        </span>

        <strong>
            <?= $total ?>
        </strong>

        <small>
            คน
        </small>

        <i class="fa-solid fa-users"></i>

    </div>


    <div class="team-stat-card">

        <span>
            อาจารย์ที่ปรึกษา
        </span>

        <strong>
            <?= $advisorCount ?>
        </strong>

        <small>
            คน
        </small>

        <i class="fa-solid fa-user-tie"></i>

    </div>


    <div class="team-stat-card">

        <span>
            คณะกรรมการ
        </span>

        <strong>
            <?= $committeeCount ?>
        </strong>

        <small>
            คน
        </small>

        <i class="fa-solid fa-people-group"></i>

    </div>


    <div class="team-stat-card">

        <span>
            แสดงบนเว็บไซต์
        </span>

        <strong>
            <?= $activeCount ?>
        </strong>

        <small>
            คน
        </small>

        <i class="fa-solid fa-eye"></i>

    </div>

</div>


<section class="panel full-panel">

    <div class="panel-head">

        <div>

            <small>
                PERSONNEL
            </small>

            <h3>
                รายการบุคลากรชมรม
            </h3>

        </div>


        <a
            href="form.php"
            class="primary-btn"
        >

            <i class="fa-solid fa-plus"></i>

            เพิ่มบุคลากร

        </a>

    </div>


    <?php if ($success): ?>

        <div class="form-alert success">

            <i class="fa-solid fa-circle-check"></i>

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="form-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <?php if ($personnel): ?>

        <div class="table-wrap">

            <table class="team-admin-table">

                <thead>

                    <tr>
                        <th>บุคลากร</th>
                        <th>ประเภท</th>
                        <th>ตำแหน่ง</th>
                        <th>ติดต่อ</th>
                        <th>ลำดับ</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>

                </thead>


                <tbody>

                <?php foreach ($personnel as $person): ?>

                    <?php

                    $image =
                        !empty($person['image'])
                        ? ('' . BC_BASE_PATH . '/') . ltrim($person['image'], '/')
                        : ('' . BC_BASE_PATH . '/assets/images/logo.png');

                    ?>


                    <tr>

                        <td>

                            <div class="admin-person">

                                <div class="admin-person-image">

                                    <img
                                        src="<?= htmlspecialchars($image) ?>"
                                        alt="<?= htmlspecialchars($person['name']) ?>"
                                        onerror="this.src='<?= BC_BASE_PATH ?>/assets/images/logo.png';"
                                    >

                                </div>


                                <div class="admin-person-info">

                                    <strong>
                                        <?= htmlspecialchars($person['name']) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $person['department'] ?: '-'
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?php if ($person['type'] === 'advisor'): ?>

                                <span class="team-type advisor">

                                    <i class="fa-solid fa-user-tie"></i>

                                    อาจารย์ที่ปรึกษา

                                </span>

                            <?php else: ?>

                                <span class="team-type committee">

                                    <i class="fa-solid fa-users"></i>

                                    คณะกรรมการ

                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $person['position']
                            ) ?>

                            <?php if (!empty($person['started_at']) || !empty($person['ended_at'])): ?>
                                <small class="team-duty-period">
                                    <?= !empty($person['started_at'])
                                        ? date('d/m/Y', strtotime($person['started_at']))
                                        : 'ไม่ระบุวันเริ่ม' ?>
                                    –
                                    <?= !empty($person['ended_at'])
                                        ? date('d/m/Y', strtotime($person['ended_at']))
                                        : 'ปัจจุบัน' ?>
                                </small>
                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="team-contact-admin">

                                <?php if (!empty($person['phone'])): ?>

                                    <span>

                                        <i class="fa-solid fa-phone"></i>

                                        <?= htmlspecialchars(
                                            $person['phone']
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if (!empty($person['email'])): ?>

                                    <span>

                                        <i class="fa-regular fa-envelope"></i>

                                        <?= htmlspecialchars(
                                            $person['email']
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    empty($person['phone']) &&
                                    empty($person['email'])
                                ): ?>

                                    <span>-</span>

                                <?php endif; ?>

                            </div>

                        </td>


                        <td>

                            <?= (int)$person['display_order'] ?>

                        </td>


                        <td>

                            <?php if ($person['status'] === 'active'): ?>

                                <span class="team-status active">

                                    <i class="fa-solid fa-circle-check"></i>

                                    แสดงผล

                                </span>

                            <?php elseif ($person['status'] === 'former'): ?>

                                <span class="team-status former">

                                    <i class="fa-solid fa-clock-rotate-left"></i>

                                    สิ้นสุดหน้าที่

                                </span>

                            <?php else: ?>

                                <span class="team-status hidden">

                                    <i class="fa-solid fa-eye-slash"></i>

                                    ซ่อน

                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="action-buttons">

                                <a
                                    href="form.php?id=<?= (int)$person['id'] ?>"
                                    class="action-btn edit"
                                    title="แก้ไข"
                                >

                                    <i class="fa-regular fa-pen-to-square"></i>

                                </a>

                                <?php if ($person['status'] === 'active'): ?>
                                    <form
                                        action="end_duty.php"
                                        method="POST"
                                        class="team-inline-action"
                                        data-bc-confirm
                                        data-bc-confirm-type="warning"
                                        data-bc-confirm-title="สิ้นสุดหน้าที่"
                                        data-bc-confirm-message="ยืนยันการสิ้นสุดหน้าที่ของบุคลากรคนนี้หรือไม่? ข้อมูลจะยังถูกเก็บไว้"
                                        data-bc-confirm-label="สิ้นสุดหน้าที่"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['team_csrf']) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
                                        <button type="submit" class="action-btn archive" title="สิ้นสุดหน้าที่">
                                            <i class="fa-solid fa-user-clock"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form
                                    action="delete.php"
                                    method="POST"
                                    class="team-inline-action"
                                    data-bc-confirm
                                    data-bc-confirm-type="archive"
                                    data-bc-confirm-title="เก็บบุคลากรเป็นประวัติ"
                                    data-bc-confirm-message="ต้องการเก็บบุคลากรรายนี้ไว้ในประวัติหรือไม่?"
                                    data-bc-confirm-label="เก็บเป็นประวัติ"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['team_csrf']) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
                                    <button type="submit" class="action-btn archive" title="เก็บเป็นประวัติ">
                                        <i class="fa-solid fa-box-archive"></i>
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

        <div class="empty">

            <i class="fa-solid fa-user-group"></i>

            <h3>
                ยังไม่มีข้อมูลบุคลากร
            </h3>

            <p>
                กดปุ่ม “เพิ่มบุคลากร” เพื่อเพิ่มข้อมูล
            </p>

        </div>

    <?php endif; ?>

</section>


<?php
include __DIR__ . '/../includes/confirmation_modal.php';
include __DIR__ . '/../includes/admin_footer.php';
?>
