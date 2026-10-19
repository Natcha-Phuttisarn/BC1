<?php
require_once __DIR__ . '/includes/bootstrap.php';
// gallery.php
// หน้านี้แสดงรายการ Gallery ที่เผยแพร่ทั้งหมดจากใหม่ไปเก่า

$pageTitle = 'ภาพกิจกรรม';
$pageCss = 'gallery.css';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$galleryItems = [];

try {
    $stmt = $pdo->query("
        SELECT g.*
        FROM activity_gallery g
        WHERE g.status = 'active'
        ORDER BY
            g.created_at DESC,
            g.id DESC
    ");

    $galleryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $galleryItems = [];
    error_log('PUBLIC GALLERY ERROR: ' . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>

<main class="gallery-page">
    <section class="gallery-hero">
        <div class="gallery-hero-decoration" aria-hidden="true"></div>

        <div class="gallery-container gallery-hero-inner">
            <div class="gallery-hero-icon">
                <i class="fa-regular fa-images"></i>
            </div>

            <div class="gallery-hero-text">
                <nav class="gallery-breadcrumb" aria-label="Breadcrumb">
                    <a href="index.php">หน้าหลัก</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span>ภาพกิจกรรม</span>
                </nav>

                <h1>ภาพกิจกรรม</h1>
                <p>รวมภาพความประทับใจและบรรยากาศจากกิจกรรมต่าง ๆ ของชมรมบิตคอม</p>
            </div>
        </div>
    </section>

    <section class="gallery-content">
        <div class="gallery-container">
            <div class="gallery-heading">
                <span>GALLERY</span>
                <h2>ภาพกิจกรรมที่ผ่านมา</h2>
                <p>เลือกกิจกรรมเพื่อดูภาพบรรยากาศเพิ่มเติม</p>
            </div>

            <?php if (!empty($galleryItems)): ?>
                <div class="gallery-grid">
                    <?php foreach ($galleryItems as $gallery): ?>
                        <?php
                        $coverImageUrl = 'uploads/gallery/' . rawurlencode(
                            basename((string)$gallery['cover_image'])
                        );
                        ?>

                        <a
                            href="gallery_detail.php?id=<?= (int)$gallery['id'] ?>"
                            class="gallery-card"
                        >
                            <span class="gallery-card-image">
                                <img
                                    src="<?= htmlspecialchars($coverImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                >
                            </span>

                            <span class="gallery-card-body">
                                <?php if (!empty($gallery['activity_date'])): ?>
                                    <span class="gallery-card-date">
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= htmlspecialchars(
                                            formatThaiDate($gallery['activity_date']),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                <?php endif; ?>

                                <span class="gallery-card-title">
                                    <?= htmlspecialchars($gallery['title'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <?php if (!empty($gallery['description'])): ?>
                                    <span class="gallery-card-description">
                                        <?= htmlspecialchars($gallery['description'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="gallery-empty">
                    <div class="gallery-empty-icon">
                        <i class="fa-regular fa-images"></i>
                    </div>
                    <h2>ยังไม่มีภาพกิจกรรมในขณะนี้</h2>
                    <p>เมื่อมีการเผยแพร่ภาพกิจกรรมใหม่ รูปภาพจะแสดงที่หน้านี้โดยอัตโนมัติ</p>
                    <a href="index.php">
                        <i class="fa-solid fa-arrow-left"></i>
                        กลับหน้าหลัก
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
