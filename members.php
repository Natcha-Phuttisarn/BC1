<?php
require_once __DIR__ . '/includes/bootstrap.php';

// members.php
// หน้านี้ใช้สำหรับแสดงรายชื่อสมาชิกชมรมให้ผู้ใช้งานที่เข้าสู่ระบบแล้วดู

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';


// =========================================
// ต้อง Login ก่อน
// =========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


// =========================================
// อนุญาตเฉพาะสมาชิกที่อนุมัติและใช้งานได้
// =========================================

$memberAccessStmt = $pdo->prepare("
    SELECT
        u.account_status,
        m.*,
        (
            SELECT ma.application_status
            FROM membership_applications ma
            WHERE ma.user_id = u.id
            ORDER BY ma.id DESC
            LIMIT 1
        ) AS latest_application_status
    FROM users u
    LEFT JOIN members m
        ON m.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");

$memberAccessStmt->execute([
    (int)$_SESSION['user_id']
]);

$memberAccess =
    $memberAccessStmt->fetch(PDO::FETCH_ASSOC);

$automaticMemberStatus =
    $memberAccess && !empty($memberAccess['id'])
    ? getAutomaticMemberStatus($memberAccess)
    : null;

$canViewMemberDirectory =
    $memberAccess &&
    !empty($memberAccess['id']) &&
    ($memberAccess['account_status'] ?? '') === 'active' &&
    ($memberAccess['member_status'] ?? '') === 'active' &&
    ($memberAccess['latest_application_status'] ?? '') === 'approved' &&
    ($automaticMemberStatus['code'] ?? '') === 'active';

if (!$canViewMemberDirectory) {
    $_SESSION['membership_error'] =
        'คุณยังไม่ได้รับการอนุมัติเป็นสมาชิกชมรม จึงไม่สามารถดูรายชื่อสมาชิกชมรมได้';

    header('Location: member_status.php');
    exit;
}


$search = trim($_GET['search'] ?? '');
$yearFilter = $_GET['year'] ?? '';


// =========================================
// ดึงสมาชิก
// =========================================

$stmt = $pdo->query("
    SELECT
        m.*,
        u.first_name,
        u.last_name,
        u.profile_image,
        u.account_status
    FROM members m
    INNER JOIN users u
        ON u.id = m.user_id
    WHERE u.account_status = 'active'
    ORDER BY m.student_id ASC
");

$allMembers = $stmt->fetchAll();

$members = [];

$summary = [
    'all' => 0,
    'year1' => 0,
    'year2' => 0,
    'year3' => 0,
    'year4' => 0
];


// =========================================
// คำนวณชั้นปีอัตโนมัติ
// =========================================

foreach ($allMembers as $member) {

    $status = getAutomaticMemberStatus($member);

    // ฝั่งผู้ใช้แสดงเฉพาะสมาชิกปัจจุบัน ปี 1-4
    if ($status['code'] !== 'active') {
        continue;
    }

    $yearLevel = $status['year_level'];

    if (
        $yearLevel === null ||
        $yearLevel < 1 ||
        $yearLevel > 4
    ) {
        continue;
    }


    $summary['all']++;

    if ($yearLevel === 1) $summary['year1']++;
    if ($yearLevel === 2) $summary['year2']++;
    if ($yearLevel === 3) $summary['year3']++;
    if ($yearLevel === 4) $summary['year4']++;


    // ค้นหา
    if ($search !== '') {

        $searchText =
            $member['first_name'] . ' ' .
            $member['last_name'] . ' ' .
            $member['student_id'];

        if (mb_stripos($searchText, $search) === false) {
            continue;
        }
    }


    // กรองชั้นปี
    if ($yearFilter !== '') {

        if ((string)$yearLevel !== (string)$yearFilter) {
            continue;
        }
    }


    $member['year_level_auto'] = $yearLevel;

    $members[] = $member;
}


// =========================================
// แบ่งหน้าหลังการค้นหาและกรองข้อมูล
// =========================================

$membersPerPage = 10;
$memberTotal = count($members);
$memberTotalPages = max(
    1,
    (int)ceil($memberTotal / $membersPerPage)
);

$memberPage = max(
    1,
    (int)($_GET['page'] ?? 1)
);

if ($memberPage > $memberTotalPages) {
    $memberPage = $memberTotalPages;
}

$memberOffset =
    ($memberPage - 1) * $membersPerPage;

$visibleMembers = array_slice(
    $members,
    $memberOffset,
    $membersPerPage
);

$memberPageUrl = static function (
    int $page
) use ($search, $yearFilter): string {
    return 'members.php?' . http_build_query([
        'search' => $search,
        'year' => $yearFilter,
        'page' => $page
    ]);
};


$pageTitle = 'รายชื่อสมาชิก';
$pageCss = 'members.css?v=20261017-ui-fix1';

include 'includes/header.php';

?>

<section class="members-page">

    <div class="members-container">


        <!-- =================================
             HEADING
        ================================== -->

        <div class="members-heading">

            <span>
                BIZCOMP CLUB MEMBERS
            </span>

            <h1>
                รายชื่อสมาชิกชมรม
            </h1>

            <p>
                ค้นหารายชื่อสมาชิก Bizcomp Club
                คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
            </p>

        </div>


        <!-- =================================
             SUMMARY
        ================================== -->

        <div class="members-summary">

            <div class="member-summary-card main">

                <span>สมาชิกทั้งหมด</span>

                <strong>
                    <?= number_format($summary['all']) ?>
                </strong>

                <small>คน</small>

            </div>


            <div class="member-summary-card">

                <span>ชั้นปีที่ 1</span>

                <strong>
                    <?= number_format($summary['year1']) ?>
                </strong>

                <small>คน</small>

            </div>


            <div class="member-summary-card">

                <span>ชั้นปีที่ 2</span>

                <strong>
                    <?= number_format($summary['year2']) ?>
                </strong>

                <small>คน</small>

            </div>


            <div class="member-summary-card">

                <span>ชั้นปีที่ 3</span>

                <strong>
                    <?= number_format($summary['year3']) ?>
                </strong>

                <small>คน</small>

            </div>


            <div class="member-summary-card">

                <span>ชั้นปีที่ 4</span>

                <strong>
                    <?= number_format($summary['year4']) ?>
                </strong>

                <small>คน</small>

            </div>

        </div>


        <!-- =================================
             SEARCH
        ================================== -->

        <form method="GET" class="members-filter">

            <div class="members-search">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="ค้นหาชื่อหรือรหัสนิสิต"
                >

            </div>


            <select name="year">

                <option value="">
                    ทุกชั้นปี
                </option>

                <option
                    value="1"
                    <?= $yearFilter === '1'
                        ? 'selected'
                        : '' ?>
                >
                    ชั้นปีที่ 1
                </option>

                <option
                    value="2"
                    <?= $yearFilter === '2'
                        ? 'selected'
                        : '' ?>
                >
                    ชั้นปีที่ 2
                </option>

                <option
                    value="3"
                    <?= $yearFilter === '3'
                        ? 'selected'
                        : '' ?>
                >
                    ชั้นปีที่ 3
                </option>

                <option
                    value="4"
                    <?= $yearFilter === '4'
                        ? 'selected'
                        : '' ?>
                >
                    ชั้นปีที่ 4
                </option>

            </select>


            <button type="submit">
                <i class="fa-solid fa-filter"></i>
                ค้นหา
            </button>


            <a href="members.php">
                ล้าง
            </a>

        </form>


        <!-- =================================
             MEMBER LIST
        ================================== -->

        <?php if ($members): ?>

            <div class="members-table-card">

                <div class="members-table-scroll">

                    <table class="members-table">

                        <colgroup>
                            <col class="member-col-order">
                            <col class="member-col-name">
                            <col class="member-col-student-id">
                            <col class="member-col-major">
                            <col class="member-col-year">
                        </colgroup>

                        <thead>
                            <tr>
                                <th scope="col">ลำดับ</th>
                                <th scope="col">ชื่อ - นามสกุล</th>
                                <th scope="col">รหัสนิสิต</th>
                                <th scope="col">สาขา</th>
                                <th scope="col">ชั้นปี</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($visibleMembers as $index => $member): ?>

                                <?php
                                $memberProfileFile = basename(
                                    (string)($member['profile_image'] ?? '')
                                );

                                $memberProfilePath =
                                    $memberProfileFile !== '' &&
                                    is_file(
                                        __DIR__ .
                                        '/uploads/profiles/' .
                                        $memberProfileFile
                                    )
                                    ? 'uploads/profiles/' . $memberProfileFile
                                    : null;
                                ?>

                                <tr>

                                    <td class="member-row-number member-cell-order">
                                        <?= $memberOffset + $index + 1 ?>
                                    </td>

                                    <td class="member-cell-name">
                                        <div class="member-name-cell">

                                            <div class="member-table-avatar">
                                                <?php if ($memberProfilePath): ?>
                                                    <img
                                                        src="<?= htmlspecialchars($memberProfilePath) ?>"
                                                        alt="รูปโปรไฟล์ของ <?= htmlspecialchars($member['first_name']) ?>"
                                                    >
                                                <?php else: ?>
                                                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                                                <?php endif; ?>
                                            </div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $member['first_name'] . ' ' .
                                                    $member['last_name']
                                                ) ?>
                                            </strong>

                                        </div>
                                    </td>

                                    <td class="member-cell-student-id">
                                        <?= htmlspecialchars($member['student_id']) ?>
                                    </td>

                                    <td class="member-cell-major">
                                        <?= htmlspecialchars($member['major'] ?: '-') ?>
                                    </td>

                                    <td class="member-cell-year">
                                        <span class="member-table-year">
                                            ชั้นปีที่ <?= (int)$member['year_level_auto'] ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="members-table-footer">

                    <span>
                        แสดง
                        <?= number_format($memberOffset + 1) ?> -
                        <?= number_format(min($memberOffset + $membersPerPage, $memberTotal)) ?>
                        จาก <?= number_format($memberTotal) ?> รายการ
                    </span>

                    <?php if ($memberTotalPages > 1): ?>

                        <nav class="members-pagination" aria-label="แบ่งหน้ารายชื่อสมาชิก">

                            <?php if ($memberPage > 1): ?>
                                <a
                                    href="<?= htmlspecialchars($memberPageUrl($memberPage - 1)) ?>"
                                    aria-label="หน้าก่อนหน้า"
                                >
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($pageNumber = 1; $pageNumber <= $memberTotalPages; $pageNumber++): ?>
                                <a
                                    href="<?= htmlspecialchars($memberPageUrl($pageNumber)) ?>"
                                    class="<?= $pageNumber === $memberPage ? 'current' : '' ?>"
                                    <?= $pageNumber === $memberPage ? 'aria-current="page"' : '' ?>
                                >
                                    <?= $pageNumber ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($memberPage < $memberTotalPages): ?>
                                <a
                                    href="<?= htmlspecialchars($memberPageUrl($memberPage + 1)) ?>"
                                    aria-label="หน้าถัดไป"
                                >
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>

                        </nav>

                    <?php endif; ?>

                </div>

            </div>

        <?php else: ?>

            <div class="members-empty">

                <i class="fa-solid fa-users"></i>

                <h3>
                    ไม่พบสมาชิก
                </h3>

                <p>
                    ไม่มีสมาชิกที่ตรงกับเงื่อนไขที่เลือก
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php include 'includes/footer.php'; ?>
