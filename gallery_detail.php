<?php
require_once __DIR__ . '/includes/bootstrap.php';
// gallery_detail.php
// หน้านี้แสดงรายละเอียด Gallery รูปปก และรูปภาพบรรยากาศเพิ่มเติม

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$galleryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$gallery = null;
$additionalImages = [];

if ($galleryId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT g.*
            FROM activity_gallery g
            WHERE g.id = ?
              AND g.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$galleryId]);
        $gallery = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($gallery) {
            $imageStmt = $pdo->prepare("
                SELECT id, image, created_at
                FROM activity_gallery_images
                WHERE gallery_id = ?
                ORDER BY id ASC
            ");
            $imageStmt->execute([$galleryId]);
            $additionalImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $gallery = null;
        $additionalImages = [];
        error_log('GALLERY DETAIL ERROR: ' . $e->getMessage());
    }
}

if (!$gallery) {
    http_response_code(404);
}

$pageTitle = $gallery ? $gallery['title'] : 'ไม่พบภาพกิจกรรม';
$pageCss = 'gallery.css';

include __DIR__ . '/includes/header.php';
?>

<main class="gallery-detail-page">
    <section class="gallery-detail-layout">
        <div class="gallery-container">
            <nav class="gallery-detail-breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>หน้าหลัก</span>
                </a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <a href="gallery.php">ภาพกิจกรรม</a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <span class="gallery-detail-breadcrumb-current">
                    <?= $gallery ? htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') : 'ไม่พบข้อมูล' ?>
                </span>
            </nav>

            <?php if ($gallery): ?>
                <?php
                $coverImageUrl = 'uploads/gallery/' . rawurlencode(
                    basename((string)$gallery['cover_image'])
                );
                ?>

                <article class="gallery-detail-card">
                    <div class="gallery-cover-wrap">
                        <img
                            class="gallery-cover-image"
                            src="<?= htmlspecialchars($coverImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="gallery-detail-info">
                        <?php if (!empty($gallery['activity_date'])): ?>
                            <div class="gallery-detail-date">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <span><?= htmlspecialchars(
                                    formatThaiDate($gallery['activity_date']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></span>
                            </div>
                        <?php endif; ?>

                        <h1 class="gallery-detail-title">
                            <?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>
                        </h1>

                        <?php if (!empty($gallery['description'])): ?>
                            <div class="gallery-detail-description">
                                <?= nl2br(htmlspecialchars($gallery['description'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <section class="gallery-photos-section" aria-labelledby="galleryPhotosTitle">
                    <div class="gallery-photos-header">
                        <div class="gallery-photos-heading">
                            <i class="fa-regular fa-images" aria-hidden="true"></i>
                            <h2 id="galleryPhotosTitle">ภาพบรรยากาศกิจกรรม</h2>
                        </div>
                        <div class="gallery-photo-count">
                            ทั้งหมด <?= count($additionalImages) ?> รูปภาพ
                        </div>
                    </div>

                    <?php if (!empty($additionalImages)): ?>
                        <div class="gallery-photo-grid">
                            <?php foreach ($additionalImages as $imageIndex => $image): ?>
                                <?php
                                $imageUrl = 'uploads/gallery/' . rawurlencode(
                                    basename((string)$image['image'])
                                );
                                ?>

                                <button
                                    type="button"
                                    class="gallery-photo-item gallery-photo-open"
                                    data-gallery-index="<?= (int)$imageIndex ?>"
                                    aria-label="ดูภาพที่ <?= (int)$imageIndex + 1 ?> จาก <?= count($additionalImages) ?> ภาพ"
                                >
                                    <img
                                        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="ภาพบรรยากาศ <?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?> ลำดับที่ <?= (int)$imageIndex + 1 ?>"
                                        loading="lazy"
                                    >
                                    <span aria-hidden="true">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="gallery-photo-empty">
                            <i class="fa-regular fa-images" aria-hidden="true"></i>
                            <p>ยังไม่มีภาพบรรยากาศเพิ่มเติม</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <div class="gallery-empty gallery-detail-not-found">
                    <div class="gallery-empty-icon"><i class="fa-regular fa-image"></i></div>
                    <h1>ไม่พบภาพกิจกรรม</h1>
                    <p>รายการนี้อาจถูกลบหรือซ่อนจากเว็บไซต์แล้ว</p>
                    <a href="gallery.php">
                        <i class="fa-solid fa-arrow-left"></i>
                        กลับไปหน้าภาพกิจกรรม
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php if ($gallery && !empty($additionalImages)): ?>
    <div
        class="gallery-lightbox"
        id="galleryLightbox"
        role="dialog"
        aria-modal="true"
        aria-label="ภาพบรรยากาศขนาดใหญ่"
        aria-hidden="true"
        hidden
    >
        <button type="button" class="gallery-lightbox-backdrop" data-gallery-close aria-label="ปิดรูปภาพ"></button>

        <div class="gallery-lightbox-dialog gallery-photo-dialog">
            <button type="button" class="gallery-lightbox-close" data-gallery-close aria-label="ปิด">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="gallery-lightbox-image">
                <img src="" alt="" id="galleryLightboxImage">
            </div>

            <button type="button" class="gallery-lightbox-nav previous" id="galleryLightboxPrevious" aria-label="รูปก่อนหน้า">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <button type="button" class="gallery-lightbox-nav next" id="galleryLightboxNext" aria-label="รูปถัดไป">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <div class="gallery-lightbox-count" id="galleryLightboxCount" aria-live="polite"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const lightbox = document.getElementById('galleryLightbox');
        const lightboxImage = document.getElementById('galleryLightboxImage');
        const closeButton = lightbox.querySelector('.gallery-lightbox-close');
        const previousButton = document.getElementById('galleryLightboxPrevious');
        const nextButton = document.getElementById('galleryLightboxNext');
        const counter = document.getElementById('galleryLightboxCount');
        const triggers = Array.from(document.querySelectorAll('.gallery-photo-open'));
        let lastTrigger = null;
        let currentIndex = 0;

        function showImage(index) {
            currentIndex = (index + triggers.length) % triggers.length;
            const trigger = triggers[currentIndex];
            const image = trigger.querySelector('img');

            lightboxImage.src = image.src;
            lightboxImage.alt = image.alt;
            counter.textContent = (currentIndex + 1) + ' / ' + triggers.length;
        }

        function closeLightbox() {
            lightbox.hidden = true;
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('gallery-modal-open');

            if (lastTrigger) {
                lastTrigger.focus();
            }
        }

        triggers.forEach(function (trigger, index) {
            trigger.addEventListener('click', function () {
                lastTrigger = trigger;
                showImage(index);
                lightbox.hidden = false;
                lightbox.setAttribute('aria-hidden', 'false');
                document.body.classList.add('gallery-modal-open');
                closeButton.focus();
            });
        });

        previousButton.addEventListener('click', function () {
            showImage(currentIndex - 1);
        });

        nextButton.addEventListener('click', function () {
            showImage(currentIndex + 1);
        });

        lightbox.querySelectorAll('[data-gallery-close]').forEach(function (button) {
            button.addEventListener('click', closeLightbox);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            } else if (event.key === 'ArrowLeft' && !lightbox.hidden) {
                showImage(currentIndex - 1);
            } else if (event.key === 'ArrowRight' && !lightbox.hidden) {
                showImage(currentIndex + 1);
            }
        });
    });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
