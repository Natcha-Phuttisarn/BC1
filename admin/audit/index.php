<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

date_default_timezone_set('Asia/Bangkok');

$adminPageTitle = 'ประวัติการแก้ไข';
$search = trim($_GET['search'] ?? '');
$action = trim($_GET['action'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$where = [];
$params = [];

function auditOptionalDateInteger(
    $value,
    int $minimum,
    int $maximum,
    bool &$invalid
): ?int
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value)) {
        $invalid = true;
        return null;
    }

    $number = (int)$value;

    if ($number < $minimum || $number > $maximum) {
        $invalid = true;
        return null;
    }

    return $number;
}

$dateFilterInvalid = false;
$filterDay = auditOptionalDateInteger(
    $_GET['filter_day'] ?? '',
    1,
    31,
    $dateFilterInvalid
);
$filterMonth = auditOptionalDateInteger(
    $_GET['filter_month'] ?? '',
    1,
    12,
    $dateFilterInvalid
);
$filterYear = auditOptionalDateInteger(
    $_GET['filter_year'] ?? '',
    2443,
    3043,
    $dateFilterInvalid
);
$filterGregorianYear = $filterYear !== null
    ? $filterYear - 543
    : null;

if (
    !$dateFilterInvalid
    && $filterDay !== null
    && $filterMonth !== null
) {
    $validationYear = $filterGregorianYear ?? 2000;

    if (!checkdate($filterMonth, $filterDay, $validationYear)) {
        $dateFilterInvalid = true;
    }
}

$filterMaximumDay = 31;

if ($filterMonth !== null) {
    $dayOptionYear = $filterGregorianYear ?? 2000;
    $filterMaximumDay = (int)(new DateTimeImmutable(
        sprintf('%04d-%02d-01', $dayOptionYear, $filterMonth)
    ))->format('t');
}

$dateFilterActive =
    $filterDay !== null
    || $filterMonth !== null
    || $filterYear !== null
    || $dateFilterInvalid;

$filterMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
    4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
    7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
    10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

$auditYears = array_map(
    'intval',
    $pdo->query("
        SELECT DISTINCT YEAR(created_at)
        FROM admin_audit_logs
        WHERE created_at IS NOT NULL
        ORDER BY YEAR(created_at) DESC
    ")->fetchAll(PDO::FETCH_COLUMN)
);

if ($search !== '') {
    $where[] = "(l.description LIKE ? OR l.entity_type LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}
if ($action !== '') {
    $where[] = 'l.action = ?';
    $params[] = $action;
}
if ($dateFilterInvalid) {
    $where[] = '1 = 0';
} else {
    if ($filterDay !== null) {
        $where[] = 'DAY(l.created_at) = ?';
        $params[] = $filterDay;
    }
    if ($filterMonth !== null) {
        $where[] = 'MONTH(l.created_at) = ?';
        $params[] = $filterMonth;
    }
    if ($filterGregorianYear !== null) {
        $where[] = 'YEAR(l.created_at) = ?';
        $params[] = $filterGregorianYear;
    }
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs l LEFT JOIN users u ON u.id = l.admin_user_id {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT l.*, u.first_name, u.last_name, u.email
    FROM admin_audit_logs l LEFT JOIN users u ON u.id = l.admin_user_id
    {$whereSql} ORDER BY l.created_at DESC, l.id DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$actions = $pdo->query("SELECT DISTINCT action FROM admin_audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>
<section class="panel full-panel">
    <div class="panel-head"><div><small>AUDIT LOG</small><h3>ประวัติการแก้ไขของผู้ดูแล</h3></div></div>
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;">
        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหารายละเอียด ผู้ดูแล หรือประเภทข้อมูล" style="min-width:280px;">
        <select name="action"><option value="">ทุกการกระทำ</option><?php foreach ($actions as $item): ?><option value="<?= htmlspecialchars($item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select>
        <div class="audit-date-fields" id="auditDateFields">
            <label><span>วัน</span><select name="filter_day" id="auditFilterDay"><option value="">ทุกวัน</option><?php for ($day = 1; $day <= 31; $day++): ?><option value="<?= $day ?>" data-day="<?= $day ?>" <?= $day > $filterMaximumDay ? 'hidden disabled' : '' ?> <?= $filterDay === $day ? 'selected' : '' ?>><?= $day ?></option><?php endfor; ?></select></label>
            <label><span>เดือน</span><select name="filter_month" id="auditFilterMonth"><option value="">ทุกเดือน</option><?php foreach ($filterMonths as $monthNumber => $monthName): ?><option value="<?= $monthNumber ?>" <?= $filterMonth === $monthNumber ? 'selected' : '' ?>><?= htmlspecialchars($monthName) ?></option><?php endforeach; ?></select></label>
            <label><span>ปี พ.ศ.</span><select name="filter_year" id="auditFilterYear"><option value="">ทุกปี</option><?php foreach ($auditYears as $gregorianYear): ?><?php $buddhistYear = $gregorianYear + 543; ?><option value="<?= $buddhistYear ?>" <?= $filterYear === $buddhistYear ? 'selected' : '' ?>><?= $buddhistYear ?></option><?php endforeach; ?></select></label>
        </div>
        <button class="primary-btn" type="submit"><i class="fa-solid fa-filter"></i> กรอง</button>
        <a class="secondary-btn" href="index.php">ล้างตัวกรอง</a>
    </form>
    <div class="table-wrap"><table><thead><tr><th>วันเวลา</th><th>ผู้ดูแล</th><th>การกระทำ</th><th>ข้อมูล</th><th>รายละเอียด</th><th>IP</th></tr></thead><tbody>
    <?php if (!$logs): ?><tr><td colspan="6" style="text-align:center;"><?= $dateFilterActive ? 'ไม่พบประวัติการแก้ไขในช่วงวันที่ที่เลือก' : 'ยังไม่มีประวัติการแก้ไข' ?></td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?><tr>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
        <td><?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: ($log['email'] ?? 'ระบบ')) ?></td>
        <td><span class="status-badge"><?= htmlspecialchars($log['action']) ?></span></td>
        <td><?= htmlspecialchars($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int)$log['entity_id'] : '' ?></td>
        <td><?= htmlspecialchars($log['description']) ?></td><td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php if ($totalPages > 1): ?><div style="display:flex;gap:8px;justify-content:center;margin-top:20px;">
        <?php for ($i=1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'primary-btn' : 'secondary-btn' ?>" href="?<?= http_build_query(['search'=>$search,'action'=>$action,'filter_day'=>$filterDay,'filter_month'=>$filterMonth,'filter_year'=>$filterYear,'page'=>$i]) ?>"><?= $i ?></a><?php endfor; ?>
    </div><?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const daySelect = document.getElementById('auditFilterDay');
    const monthSelect = document.getElementById('auditFilterMonth');
    const yearSelect = document.getElementById('auditFilterYear');

    if (!daySelect || !monthSelect || !yearSelect) {
        return;
    }

    function updateAvailableDays() {
        const month = Number.parseInt(monthSelect.value, 10);
        const buddhistYear = Number.parseInt(yearSelect.value, 10);
        const gregorianYear = Number.isInteger(buddhistYear)
            ? buddhistYear - 543
            : 2000;
        const maximumDay = Number.isInteger(month)
            ? new Date(gregorianYear, month, 0).getDate()
            : 31;

        daySelect.querySelectorAll('option[data-day]').forEach(function (option) {
            const unavailable = Number.parseInt(option.dataset.day, 10) > maximumDay;
            option.hidden = unavailable;
            option.disabled = unavailable;
        });

        if (Number.parseInt(daySelect.value, 10) > maximumDay) {
            daySelect.value = '';
        }
    }

    monthSelect.addEventListener('change', updateAvailableDays);
    yearSelect.addEventListener('change', updateAvailableDays);
    updateAvailableDays();
});
</script>
<style>
.audit-date-fields {
    display: flex;
    align-items: flex-end;
    gap: 7px;
    flex-wrap: wrap;
}
.audit-date-fields label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 10px;
    color: #66758b;
}
.audit-date-fields input,
.audit-date-fields select {
    min-width: 92px;
}
</style>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
