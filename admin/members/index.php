<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// admin/members/index.php
// หน้านี้ใช้สำหรับจัดการสมาชิกและดูสมาชิกตามเดือนและปีที่สมัคร

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Asia/Bangkok');

$adminPageTitle = 'สมาชิกชมรม';

$search = trim($_GET['search'] ?? '');
$yearFilter = $_GET['year'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$success = $_SESSION['member_success'] ?? '';
$error = $_SESSION['member_error'] ?? '';

unset($_SESSION['member_success']);
unset($_SESSION['member_error']);

/* ดึงข้อมูลสมาชิก */
$stmt = $pdo->query("
    SELECT m.*, u.first_name, u.last_name, u.email, u.account_status
    FROM members m
    INNER JOIN users u ON u.id = m.user_id
    ORDER BY m.id DESC
");

$allMembers = $stmt->fetchAll();
$members = [];

$summary = [
    'current' => 0,
    'year1' => 0,
    'year2' => 0,
    'year3' => 0,
    'year4' => 0,
    'graduated' => 0
];

foreach ($allMembers as $member) {
    $status = getAutomaticMemberStatus($member);
    $admissionYear = getMemberAdmissionYear($member);

    $member['calculated_year'] = $status['year_level'];
    $member['calculated_status'] = $status['code'];
    $member['calculated_status_text'] = $status['text'];
    $member['calculated_admission_year'] = $admissionYear;

    if ($status['code'] === 'active') {
        $summary['current']++;

        if ($status['year_level'] === 1) $summary['year1']++;
        if ($status['year_level'] === 2) $summary['year2']++;
        if ($status['year_level'] === 3) $summary['year3']++;
        if ($status['year_level'] === 4) $summary['year4']++;
    }

    if ($status['code'] === 'graduated') {
        $summary['graduated']++;
    }

    /* ค้นหา */
    if ($search !== '') {
        $searchText = $member['first_name'] . ' ' .
                      $member['last_name'] . ' ' .
                      $member['student_id'] . ' ' .
                      $member['email'];

        if (mb_stripos($searchText, $search) === false) {
            continue;
        }
    }

    /* กรองชั้นปี */
    if ($yearFilter !== '') {
        if ((string)$status['year_level'] !== (string)$yearFilter) {
            continue;
        }
    }

    /* กรองสถานะ */
    if ($statusFilter !== '') {
        if ($status['code'] !== $statusFilter) {
            continue;
        }
    }

    $members[] = $member;
}

/* สมาชิกที่เข้าร่วมตามเดือนและปี โดยใช้ members.joined_at เดิม */
$thaiMonths = [
    1 => 'มกราคม',
    2 => 'กุมภาพันธ์',
    3 => 'มีนาคม',
    4 => 'เมษายน',
    5 => 'พฤษภาคม',
    6 => 'มิถุนายน',
    7 => 'กรกฎาคม',
    8 => 'สิงหาคม',
    9 => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม'
];

$joinYearInput = (int)($_GET['join_year'] ?? 0);
if ($joinYearInput > 2400) {
    $joinYearInput -= 543;
}

$joinMonth = (int)($_GET['join_month'] ?? date('n'));
if ($joinMonth < 1 || $joinMonth > 12) {
    $joinMonth = (int)date('n');
}

$yearStmt = $pdo->query("
    SELECT DISTINCT YEAR(joined_at) AS join_year
    FROM members
    WHERE joined_at IS NOT NULL
    ORDER BY join_year DESC
");
$databaseYears = array_map('intval', $yearStmt->fetchAll(PDO::FETCH_COLUMN));

$currentYear = (int)date('Y');
$availableYears = array_values(array_unique(array_merge(
    $databaseYears,
    range($currentYear - 2, $currentYear + 2)
)));
rsort($availableYears, SORT_NUMERIC);

$joinYear = ($joinYearInput >= 2000 && $joinYearInput <= 2100)
    ? $joinYearInput
    : ($databaseYears[0] ?? $currentYear);

$monthlyMemberStmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name
    FROM members m
    INNER JOIN users u ON u.id = m.user_id
    WHERE YEAR(m.joined_at) = ?
      AND MONTH(m.joined_at) = ?
    ORDER BY m.joined_at ASC, m.id ASC
");
$monthlyMemberStmt->execute([$joinYear, $joinMonth]);
$monthlyMembers = $monthlyMemberStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($monthlyMembers as &$monthlyMember) {
    $monthlyStatus = getAutomaticMemberStatus($monthlyMember);
    $monthlyMember['display_year_level'] = $monthlyStatus['year_level'];
}
unset($monthlyMember);

$monthlyCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM members
    WHERE YEAR(joined_at) = ?
      AND MONTH(joined_at) = ?
");
$monthlyCountStmt->execute([$joinYear, $joinMonth]);
$monthlyMemberCount = (int)$monthlyCountStmt->fetchColumn();

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<!-- สรุปสมาชิก -->
<div class="member-stat-grid">

    <div class="member-stat-card main">
        <div>
            <span>สมาชิกปัจจุบัน</span>
            <strong><?= number_format($summary['current']) ?></strong>
            <small>คน</small>
        </div>
        <i class="fa-solid fa-users"></i>
    </div>

    <div class="member-stat-card">
        <div>
            <span>ชั้นปีที่ 1</span>
            <strong><?= number_format($summary['year1']) ?></strong>
            <small>คน</small>
        </div>
        <i class="fa-solid fa-1"></i>
    </div>

    <div class="member-stat-card">
        <div>
            <span>ชั้นปีที่ 2</span>
            <strong><?= number_format($summary['year2']) ?></strong>
            <small>คน</small>
        </div>
        <i class="fa-solid fa-2"></i>
    </div>

    <div class="member-stat-card">
        <div>
            <span>ชั้นปีที่ 3</span>
            <strong><?= number_format($summary['year3']) ?></strong>
            <small>คน</small>
        </div>
        <i class="fa-solid fa-3"></i>
    </div>

    <div class="member-stat-card">
        <div>
            <span>ชั้นปีที่ 4</span>
            <strong><?= number_format($summary['year4']) ?></strong>
            <small>คน</small>
        </div>
        <i class="fa-solid fa-4"></i>
    </div>

    <div class="member-stat-card graduated">
        <div>
            <span>พ้นสภาพสมาชิก</span>
            <strong><?= number_format($summary['graduated']) ?></strong>
            <small>คน</small>
        </div>
        <i class="fa-solid fa-user-graduate"></i>
    </div>

</div>

<section class="panel full-panel member-month-panel">

    <div class="panel-head">
        <div>
            <small>MEMBERS BY JOIN DATE</small>
            <h3>สมาชิกที่เข้าร่วมตามเดือน</h3>
        </div>
    </div>

    <form method="GET" class="member-month-filter">
        <label>
            <span>ปี</span>
            <select name="join_year">
                <?php foreach ($availableYears as $yearOption): ?>
                    <option value="<?= $yearOption ?>" <?= $joinYear === $yearOption ? 'selected' : '' ?>>
                        <?= $yearOption + 543 ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>เดือน</span>
            <select name="join_month">
                <?php foreach ($thaiMonths as $monthNumber => $monthName): ?>
                    <option value="<?= $monthNumber ?>" <?= $joinMonth === $monthNumber ? 'selected' : '' ?>>
                        <?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="primary-btn">
            <i class="fa-solid fa-filter"></i>
            ดูรายชื่อ
        </button>
    </form>

    <div class="member-month-title">
        <div>
            <h4>
                สมาชิกที่เข้าร่วมเดือน
                <?= htmlspecialchars($thaiMonths[$joinMonth], ENT_QUOTES, 'UTF-8') ?>
                <?= $joinYear + 543 ?>
            </h4>
            <p>รายชื่ออ้างอิงจากวันที่สมัครสมาชิกในระบบ</p>
        </div>
        <strong>จำนวนทั้งหมด <?= number_format($monthlyMemberCount) ?> คน</strong>
    </div>

    <?php if ($monthlyMembers): ?>
        <div class="table-wrap">
            <table class="member-month-table">
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>รหัสนิสิต</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>คณะ</th>
                        <th>สาขา</th>
                        <th>ชั้นปี</th>
                        <th>วันที่สมัคร</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyMembers as $index => $monthlyMember): ?>
                        <?php $joinedTimestamp = strtotime($monthlyMember['joined_at']); ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($monthlyMember['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= htmlspecialchars(
                                    trim($monthlyMember['first_name'] . ' ' . $monthlyMember['last_name']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td><?= htmlspecialchars($monthlyMember['faculty'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($monthlyMember['major'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= $monthlyMember['display_year_level']
                                    ? (int)$monthlyMember['display_year_level']
                                    : '-'
                                ?>
                            </td>
                            <td>
                                <?= $joinedTimestamp
                                    ? date('d/m/', $joinedTimestamp) . ((int)date('Y', $joinedTimestamp) + 543)
                                    : '-'
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="member-month-empty">
            ไม่พบสมาชิกที่สมัครในเดือนและปีที่เลือก
        </div>
    <?php endif; ?>

</section>

<section class="panel full-panel">

    <div class="panel-head">
        <div>
            <small>MEMBERS</small>
            <h3>รายชื่อสมาชิกชมรม</h3>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="../reports/export_members.php" class="secondary-btn"><i class="fa-solid fa-file-csv"></i> ส่งออก CSV</a>
            <a href="../reports/members.php" class="primary-btn"><i class="fa-solid fa-chart-pie"></i> สรุปสมาชิก / PDF</a>
        </div>
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


    <!-- ค้นหา / กรอง -->
    <form method="GET" class="member-filter">

        <div class="member-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="ค้นหาชื่อ รหัสนิสิต หรืออีเมล"
            >
        </div>

        <select name="year">
            <option value="">ทุกชั้นปี</option>
            <option value="1" <?= $yearFilter === '1' ? 'selected' : '' ?>>ปี 1</option>
            <option value="2" <?= $yearFilter === '2' ? 'selected' : '' ?>>ปี 2</option>
            <option value="3" <?= $yearFilter === '3' ? 'selected' : '' ?>>ปี 3</option>
            <option value="4" <?= $yearFilter === '4' ? 'selected' : '' ?>>ปี 4</option>
        </select>

        <select name="status">
            <option value="">ทุกสถานะ</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>สมาชิกปัจจุบัน</option>
            <option value="graduated" <?= $statusFilter === 'graduated' ? 'selected' : '' ?>>พ้นสภาพสมาชิก</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>ไม่ใช้งาน</option>
            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>ระงับสมาชิก</option>
        </select>

        <button type="submit" class="primary-btn">
            <i class="fa-solid fa-filter"></i>
            กรอง
        </button>

        <a href="index.php" class="secondary-btn">
            ล้าง
        </a>

    </form>


    <!-- ตารางสมาชิก -->
    <?php if ($members): ?>

        <div class="table-wrap">
            <table class="members-table">

                <thead>
                    <tr>
                        <th>สมาชิก</th>
                        <th>รหัสนิสิต</th>
                        <th>ปีที่เข้า</th>
                        <th>ชั้นปี</th>
                        <th>คณะ / สาขา</th>
                        <th>เบอร์โทร</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($members as $member): ?>

                    <tr>

                        <td>
                            <div class="member-name">
                                <div class="member-avatar">
                                    <?= htmlspecialchars(
                                        mb_substr($member['first_name'], 0, 1)
                                    ) ?>
                                </div>

                                <div class="member-name-text">
                                    <strong>
                                        <?= htmlspecialchars(
                                            $member['first_name'] . ' ' . $member['last_name']
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($member['email']) ?>
                                    </span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="student-id">
                                <?= htmlspecialchars($member['student_id']) ?>
                            </span>
                        </td>

                        <td>
                            <?= $member['calculated_admission_year']
                                ? (int)$member['calculated_admission_year']
                                : '-'
                            ?>
                        </td>

                        <td>
                            <?php if ($member['calculated_status'] === 'graduated'): ?>

                                <span class="year-badge graduated">
                                    พ้นสภาพ
                                </span>

                            <?php elseif (
                                $member['calculated_year'] !== null &&
                                $member['calculated_year'] >= 1 &&
                                $member['calculated_year'] <= 4
                            ): ?>

                                <span class="year-badge">
                                    ปี <?= (int)$member['calculated_year'] ?>
                                </span>

                            <?php else: ?>

                                <span class="year-badge unknown">
                                    -
                                </span>

                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="faculty-info">
                                <strong>
                                    <?= htmlspecialchars($member['faculty'] ?: '-') ?>
                                </strong>
                                <span>
                                    <?= htmlspecialchars($member['major'] ?: '-') ?>
                                </span>
                            </div>
                        </td>

                        <td>
                            <?= htmlspecialchars($member['phone'] ?: '-') ?>
                        </td>

                        <td>
                            <span class="member-status <?= htmlspecialchars($member['calculated_status']) ?>">
                                <?= htmlspecialchars($member['calculated_status_text']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="action-buttons">

                                <a
                                    href="edit.php?id=<?= (int)$member['id'] ?>"
                                    class="action-btn edit"
                                    title="แก้ไขข้อมูล"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$member['id'] ?>"
                                    class="action-btn delete"
                                    title="ลบสมาชิกและปิดบัญชี"
                                >
                                    <i class="fa-solid fa-box-archive"></i>
                                </a>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>
        </div>

    <?php else: ?>

        <div class="empty">
            <i class="fa-solid fa-users"></i>
            <h3>ไม่พบข้อมูลสมาชิก</h3>
            <p>ยังไม่มีสมาชิก หรือไม่มีสมาชิกที่ตรงกับเงื่อนไขการค้นหา</p>
        </div>

    <?php endif; ?>

</section>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
