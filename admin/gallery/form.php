<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// form.php
// หน้านี้ใช้สำหรับเพิ่มหรือแก้ไขข้อมูลและรูปภาพของ Gallery ในระบบผู้ดูแล

require_once __DIR__ . '/../includes/admin_auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$adminPageTitle = $isEdit ? 'แก้ไขภาพกิจกรรม' : 'เพิ่มภาพกิจกรรม';

$gallery = [
    'id' => 0,
    'activity_id' => '',
    'title' => '',
    'description' => '',
    'activity_date' => '',
    'cover_image' => '',
    'status' => 'active'
];

$additionalImages = [];

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM activity_gallery
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $_SESSION['gallery_error'] = 'ไม่พบภาพกิจกรรมที่ต้องการแก้ไข';
        header('Location: index.php');
        exit;
    }

    $gallery = $result;

    $imageStmt = $pdo->prepare("
        SELECT id, image, created_at
        FROM activity_gallery_images
        WHERE gallery_id = ?
        ORDER BY id ASC
    ");
    $imageStmt->execute([$id]);
    $additionalImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
}

$old = $_SESSION['gallery_old'] ?? [];
unset($_SESSION['gallery_old']);

if (!empty($old)) {
    $gallery = array_merge($gallery, $old);
}

$error = $_SESSION['gallery_error'] ?? '';
$success = $_SESSION['gallery_success'] ?? '';
unset($_SESSION['gallery_error'], $_SESSION['gallery_success']);

if (empty($_SESSION['gallery_csrf'])) {
    $_SESSION['gallery_csrf'] = bin2hex(random_bytes(32));
}

$activities = [];

try {
    $activityStmt = $pdo->query("
        SELECT id, title, activity_date
        FROM activities
        ORDER BY activity_date DESC, id DESC
    ");
    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('GALLERY FORM ACTIVITIES ERROR: ' . $e->getMessage());
}

$currentCoverUrl = !empty($gallery['cover_image'])
    ? ('' . BC_BASE_PATH . '/uploads/gallery/') . rawurlencode(basename((string)$gallery['cover_image']))
    : '';

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel">
    <div class="panel-head">
        <div>
            <small>GALLERY FORM</small>
            <h3><?= $isEdit ? 'แก้ไขภาพกิจกรรม' : 'เพิ่มภาพกิจกรรมใหม่' ?></h3>
        </div>

        <a href="index.php" class="secondary-btn">
            <i class="fa-solid fa-arrow-left"></i>
            กลับ
        </a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="form-alert success">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form
        action="save.php"
        method="POST"
        enctype="multipart/form-data"
        class="activity-form gallery-form"
    >
        <input type="hidden" name="id" value="<?= (int)$gallery['id'] ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['gallery_csrf'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="form-section">
            <div class="form-section-title">
                <div class="form-section-icon"><i class="fa-regular fa-image"></i></div>
                <div>
                    <h4>ข้อมูลภาพกิจกรรม</h4>
                    <p>ข้อมูลสำหรับแสดงบนหน้าแรก หน้ารวม และหน้ารายละเอียด</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group full">
                    <label for="activity_id">เลือกกิจกรรม</label>
                    <select name="activity_id" id="activity_id">
                        <option value="">ไม่เชื่อมกับกิจกรรม</option>

                        <?php foreach ($activities as $activity): ?>
                            <option
                                value="<?= (int)$activity['id'] ?>"
                                data-title="<?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?>"
                                data-date="<?= htmlspecialchars($activity['activity_date'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= (string)$gallery['activity_id'] === (string)$activity['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?>
                                (<?= htmlspecialchars(formatThaiDate($activity['activity_date']), ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">
                        เมื่อเลือกกิจกรรม ระบบจะช่วยเติมชื่อและวันที่หากช่องนั้นยังว่าง โดยยังแก้ไขได้เอง
                    </small>
                </div>

                <div class="form-group full">
                    <label for="title">ชื่อภาพ / ชื่อกิจกรรม <span>*</span></label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        value="<?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label for="description">รายละเอียด</label>
                    <textarea
                        name="description"
                        id="description"
                        rows="5"
                    ><?= htmlspecialchars((string)$gallery['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="activity_date">วันที่กิจกรรม</label>
                    <input
                        type="date"
                        name="activity_date"
                        id="activity_date"
                        value="<?= htmlspecialchars((string)$gallery['activity_date'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="status">สถานะ</label>
                    <select name="status" id="status">
                        <option value="active" <?= $gallery['status'] === 'active' ? 'selected' : '' ?>>แสดง</option>
                        <option value="inactive" <?= $gallery['status'] === 'inactive' ? 'selected' : '' ?>>ซ่อน</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">
                <div class="form-section-icon"><i class="fa-solid fa-panorama"></i></div>
                <div>
                    <h4>รูปภาพ Gallery</h4>
                    <p>กำหนดรูปปกและเพิ่มภาพบรรยากาศได้หลายรูป</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group full">
                    <label for="cover_image">
                        รูปปกกิจกรรม
                        <?php if (!$isEdit): ?><span>*</span><?php endif; ?>
                    </label>

                    <?php if ($currentCoverUrl !== ''): ?>
                        <div class="gallery-form-preview" id="coverPreviewWrap">
                            <img
                                src="<?= htmlspecialchars($currentCoverUrl, ENT_QUOTES, 'UTF-8') ?>"
                                alt="รูปปกปัจจุบัน"
                                id="coverPreview"
                            >
                            <div>
                                <strong>รูปปกปัจจุบัน</strong>
                                <span>หากไม่เลือกรูปใหม่ ระบบจะใช้รูปนี้ต่อไป</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="gallery-form-preview" id="coverPreviewWrap" hidden>
                            <img src="" alt="ตัวอย่างรูปปก" id="coverPreview">
                            <div>
                                <strong>ตัวอย่างรูปปก</strong>
                                <span>รูปที่เลือกจะแสดงตัวอย่างที่นี่</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input
                        type="file"
                        name="cover_image"
                        id="cover_image"
                        accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                        <?= !$isEdit ? 'required' : '' ?>
                    >
                    <small class="form-help">
                        รูปนี้จะแสดงบนหน้าแรกและหน้ารวมภาพกิจกรรม<br>
                        รองรับไฟล์ JPG, JPEG, PNG, WEBP และ GIF ขนาดไม่เกิน 5 MB ต่อรูป
                    </small>
                </div>

                <div class="form-group full">
                    <label>รูปภาพเพิ่มเติม</label>

                    <div class="gallery-upload-inputs" id="additionalInputList">
                        <div class="gallery-upload-input-row">
                            <input
                                type="file"
                                name="additional_images[]"
                                class="gallery-additional-input"
                                accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                                multiple
                            >
                        </div>
                    </div>

                    <div class="gallery-selected-grid" id="additionalPreview" hidden></div>

                    <button type="button" class="gallery-add-input-btn" id="addAdditionalInput">
                        <i class="fa-solid fa-plus"></i>
                        เพิ่มรูป
                    </button>

                    <small class="form-help" id="additionalImagesHelp">
                        สามารถเลือกหลายรูปพร้อมกันได้ และกด “เพิ่มรูป” เพื่อเลือกเพิ่มได้เรื่อย ๆ<br>
                        รองรับไฟล์ JPG, JPEG, PNG, WEBP และ GIF ขนาดไม่เกิน 5 MB ต่อรูป
                    </small>
                </div>

                <?php if (!empty($additionalImages)): ?>
                    <div class="form-group full">
                        <label>รูปภาพเพิ่มเติมปัจจุบัน</label>

                        <div class="gallery-existing-grid">
                            <?php foreach ($additionalImages as $image): ?>
                                <?php
                                $additionalImageUrl = ('' . BC_BASE_PATH . '/uploads/gallery/')
                                    . rawurlencode(basename((string)$image['image']));
                                ?>

                                <div class="gallery-existing-item">
                                    <img
                                        src="<?= htmlspecialchars($additionalImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="รูปภาพเพิ่มเติม"
                                        loading="lazy"
                                    >
                                    <button
                                        type="submit"
                                        name="image_id"
                                        value="<?= (int)$image['id'] ?>"
                                        formaction="image_delete.php"
                                        formmethod="POST"
                                        formnovalidate
                                        data-bc-confirm
                                        data-bc-confirm-type="delete"
                                        data-bc-confirm-title="ลบรูปภาพ"
                                        data-bc-confirm-message="ยืนยันการลบรูปนี้?"
                                        data-bc-confirm-label="ลบรูป"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                        ลบรูป
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="index.php" class="secondary-btn">ยกเลิก</a>
            <button type="submit" class="primary-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                บันทึก
            </button>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const activitySelect = document.getElementById('activity_id');
    const titleInput = document.getElementById('title');
    const dateInput = document.getElementById('activity_date');
    const coverInput = document.getElementById('cover_image');
    const coverPreviewWrap = document.getElementById('coverPreviewWrap');
    const coverPreview = document.getElementById('coverPreview');
    const additionalInputList = document.getElementById('additionalInputList');
    const additionalPreview = document.getElementById('additionalPreview');
    const addAdditionalInput = document.getElementById('addAdditionalInput');
    const additionalHelp = document.getElementById('additionalImagesHelp');
    let previewUrls = [];

    if (activitySelect) {
        activitySelect.addEventListener('change', function () {
            const option = this.options[this.selectedIndex];

            if (!option || !option.value) {
                return;
            }

            if (titleInput && titleInput.value.trim() === '') {
                titleInput.value = option.dataset.title || '';
            }

            if (dateInput && dateInput.value === '') {
                dateInput.value = option.dataset.date || '';
            }
        });
    }

    if (coverInput && coverPreviewWrap && coverPreview) {
        coverInput.addEventListener('change', function () {
            const file = this.files && this.files[0];

            if (!file) {
                return;
            }

            coverPreview.src = URL.createObjectURL(file);
            coverPreviewWrap.hidden = false;
        });
    }

    function clearPreviewUrls() {
        previewUrls.forEach(function (url) {
            URL.revokeObjectURL(url);
        });
        previewUrls = [];
    }

    function renderAdditionalPreview() {
        if (!additionalInputList || !additionalPreview) {
            return;
        }

        clearPreviewUrls();
        additionalPreview.innerHTML = '';
        let selectedCount = 0;

        additionalInputList.querySelectorAll('.gallery-additional-input').forEach(function (input) {
            Array.from(input.files || []).forEach(function (file, fileIndex) {
                selectedCount++;

                const previewUrl = URL.createObjectURL(file);
                previewUrls.push(previewUrl);

                const item = document.createElement('div');
                item.className = 'gallery-selected-item';

                const image = document.createElement('img');
                image.src = previewUrl;
                image.alt = file.name;

                const fileName = document.createElement('span');
                fileName.textContent = file.name;

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'gallery-selected-remove';
                removeButton.setAttribute('aria-label', 'นำ ' + file.name + ' ออกจากรายการ');
                removeButton.textContent = '×';
                removeButton.addEventListener('click', function () {
                    if (typeof DataTransfer === 'undefined') {
                        input.value = '';
                    } else {
                        const transfer = new DataTransfer();

                        Array.from(input.files || []).forEach(function (currentFile, currentIndex) {
                            if (currentIndex !== fileIndex) {
                                transfer.items.add(currentFile);
                            }
                        });

                        input.files = transfer.files;
                    }

                    renderAdditionalPreview();
                });

                item.appendChild(image);
                item.appendChild(fileName);
                item.appendChild(removeButton);
                additionalPreview.appendChild(item);
            });
        });

        additionalPreview.hidden = selectedCount === 0;

        if (additionalHelp) {
            additionalHelp.innerHTML = selectedCount > 0
                ? 'เลือกรูปภาพเพิ่มเติมแล้ว ' + selectedCount + ' รูป<br>รองรับไฟล์ JPG, JPEG, PNG, WEBP และ GIF ขนาดไม่เกิน 5 MB ต่อรูป'
                : 'สามารถเลือกหลายรูปพร้อมกันได้ และกด “เพิ่มรูป” เพื่อเลือกเพิ่มได้เรื่อย ๆ<br>รองรับไฟล์ JPG, JPEG, PNG, WEBP และ GIF ขนาดไม่เกิน 5 MB ต่อรูป';
        }
    }

    function bindAdditionalInput(input) {
        input.addEventListener('change', renderAdditionalPreview);
    }

    if (additionalInputList) {
        additionalInputList.querySelectorAll('.gallery-additional-input').forEach(bindAdditionalInput);
    }

    if (addAdditionalInput && additionalInputList) {
        addAdditionalInput.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'gallery-upload-input-row';

            const input = document.createElement('input');
            input.type = 'file';
            input.name = 'additional_images[]';
            input.className = 'gallery-additional-input';
            input.accept = '.jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif';
            input.multiple = true;

            bindAdditionalInput(input);
            row.appendChild(input);
            additionalInputList.appendChild(row);
            input.click();
        });
    }

    window.addEventListener('beforeunload', clearPreviewUrls);
});
</script>

<?php include __DIR__ . '/../includes/confirmation_modal.php'; ?>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
