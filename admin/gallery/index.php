<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// index.php
// หน้านี้ใช้สำหรับแสดงและจัดการรายการภาพกิจกรรมในระบบผู้ดูแล

require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'ภาพกิจกรรม';

if (empty($_SESSION['gallery_csrf'])) {
    $_SESSION['gallery_csrf'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['gallery_success'] ?? '';
$error = $_SESSION['gallery_error'] ?? '';

unset(
    $_SESSION['gallery_success'],
    $_SESSION['gallery_error']
);

$galleryItems = [];

try {
    $stmt = $pdo->query("
        SELECT
            g.*,
            a.title AS linked_activity_title
        FROM activity_gallery g
        LEFT JOIN activities a
            ON a.id = g.activity_id
        ORDER BY
            g.created_at DESC,
            g.id DESC
    ");

    $galleryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'ไม่สามารถโหลดรายการภาพกิจกรรมได้ กรุณาตรวจสอบว่าได้ติดตั้งตาราง activity_gallery แล้ว';
    error_log('GALLERY INDEX ERROR: ' . $e->getMessage());
}

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel">

    <div class="panel-head">

        <div>
            <small>GALLERY</small>
            <h3>ภาพกิจกรรมทั้งหมด</h3>
        </div>

        <a href="form.php" class="primary-btn">
            <i class="fa-solid fa-plus"></i>
            เพิ่มภาพกิจกรรม
        </a>

    </div>

    <?php if ($success !== ''): ?>
        <div class="form-alert success">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="form-alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($galleryItems)): ?>

        <div class="table-wrap">

            <table class="gallery-admin-table">

                <thead>
                    <tr>
                        <th>รูปภาพ</th>
                        <th>ชื่อกิจกรรม</th>
                        <th>วันที่กิจกรรม</th>
                        <th>กิจกรรมที่เชื่อมโยง</th>
                        <th>สถานะ</th>
                        <th>วันที่เพิ่ม</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($galleryItems as $gallery): ?>

                        <?php
                        $imageName = basename((string)$gallery['cover_image']);
                        $imageUrl = ('' . BC_BASE_PATH . '/uploads/gallery/') . rawurlencode($imageName);
                        ?>

                        <tr>

                            <td>
                                <div class="gallery-admin-thumb">
                                    <img
                                        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy"
                                    >
                                </div>
                            </td>

                            <td>
                                <div class="gallery-admin-title">
                                    <strong>
                                        <?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                    <?php if (!empty($gallery['description'])): ?>
                                        <span>
                                            <?= htmlspecialchars($gallery['description'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <?= !empty($gallery['activity_date'])
                                    ? htmlspecialchars(formatThaiDate($gallery['activity_date']), ENT_QUOTES, 'UTF-8')
                                    : '-'
                                ?>
                            </td>

                            <td>
                                <?php if (!empty($gallery['linked_activity_title'])): ?>
                                    <a
                                        href="<?= BC_BASE_PATH ?>/admin/activities/edit.php?id=<?= (int)$gallery['activity_id'] ?>"
                                        class="gallery-activity-link"
                                    >
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= htmlspecialchars($gallery['linked_activity_title'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php elseif (!empty($gallery['activity_id'])): ?>
                                    <span class="gallery-unlinked">กิจกรรมถูกลบแล้ว</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="team-status <?= $gallery['status'] === 'active' ? 'active' : 'hidden' ?>">
                                    <i class="fa-solid <?= $gallery['status'] === 'active' ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                    <?= $gallery['status'] === 'active' ? 'แสดง' : 'ซ่อน' ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    date('d/m/Y H:i', strtotime($gallery['created_at'])),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <a
                                        href="form.php?id=<?= (int)$gallery['id'] ?>"
                                        class="action-btn edit"
                                        title="แก้ไข"
                                        aria-label="แก้ไข <?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <form
                                        action="delete.php"
                                        method="POST"
                                        class="gallery-delete-form"
                                        data-bc-confirm
                                        data-bc-confirm-type="delete"
                                        data-bc-confirm-title="ลบภาพกิจกรรม"
                                        data-bc-confirm-message="ยืนยันการลบภาพกิจกรรมนี้? การลบไม่สามารถย้อนกลับได้"
                                        data-bc-confirm-label="ลบภาพกิจกรรม"
                                    >
                                        <input type="hidden" name="id" value="<?= (int)$gallery['id'] ?>">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars($_SESSION['gallery_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                        <button
                                            type="submit"
                                            class="action-btn delete"
                                            title="ลบ"
                                            aria-label="ลบ <?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php elseif ($error === ''): ?>

        <div class="empty">
            <i class="fa-regular fa-images"></i>
            <h3>ยังไม่มีภาพกิจกรรม</h3>
            <p>เริ่มเพิ่มภาพบรรยากาศจากกิจกรรมของชมรมได้เลย</p>
        </div>

    <?php endif; ?>

</section>

<?php include __DIR__ . '/../includes/confirmation_modal.php'; ?>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
