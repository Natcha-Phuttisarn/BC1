<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'บัญชีรายรับ–รายจ่าย';
if (empty($_SESSION['finance_csrf'])) {
    $_SESSION['finance_csrf'] = bin2hex(random_bytes(32));
}

function financeE($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
    4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
    7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
    10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

$today = new DateTimeImmutable('today');
$selectedMonth = (int)$today->format('n');
$selectedYear = (int)$today->format('Y');
$selectedDay = null;

// รองรับ URL เดิมจากหน้าบันทึกข้อมูลที่ส่ง month ในรูปแบบ YYYY-MM
$monthInput = trim((string)($_GET['month'] ?? ''));
if (preg_match('/^(\d{4})-(\d{2})$/', $monthInput, $legacyMonth)) {
    $selectedYear = (int)$legacyMonth[1];
    $selectedMonth = (int)$legacyMonth[2];
} elseif (ctype_digit($monthInput)) {
    $selectedMonth = (int)$monthInput;
}

$yearInput = trim((string)($_GET['year'] ?? ''));
if ($yearInput !== '' && ctype_digit($yearInput)) {
    $selectedYear = (int)$yearInput;
}

$dayInput = trim((string)($_GET['day'] ?? ''));
if ($dayInput !== '' && ctype_digit($dayInput)) {
    $selectedDay = (int)$dayInput;
}

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)$today->format('n');
}
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)$today->format('Y');
}

$monthStart = DateTimeImmutable::createFromFormat(
    '!Y-n-j',
    $selectedYear . '-' . $selectedMonth . '-1'
);
if (!$monthStart) {
    $monthStart = $today->modify('first day of this month');
    $selectedMonth = (int)$monthStart->format('n');
    $selectedYear = (int)$monthStart->format('Y');
}

$maximumDay = (int)$monthStart->format('t');
if (
    $selectedDay !== null
    && ($selectedDay < 1 || $selectedDay > $maximumDay
        || !checkdate($selectedMonth, $selectedDay, $selectedYear))
) {
    $selectedDay = null;
}

if ($selectedDay !== null) {
    $periodStart = $monthStart->setDate($selectedYear, $selectedMonth, $selectedDay);
    $periodEnd = $periodStart->modify('+1 day');
    $periodLabel = $selectedDay . ' ' . $thaiMonths[$selectedMonth] . ' ' . $selectedYear;
} else {
    $periodStart = $monthStart;
    $periodEnd = $monthStart->modify('+1 month');
    $periodLabel = $thaiMonths[$selectedMonth] . ' ' . $selectedYear;
}

$start = $periodStart->format('Y-m-d');
$end = $periodEnd->format('Y-m-d');
$monthKey = sprintf('%04d-%02d', $selectedYear, $selectedMonth);
$view = ($_GET['view'] ?? '') === 'archived' ? 'archived' : 'active';

// สูตรรายรับเดิม: ค่าสมาชิกที่อนุมัติแล้ว + รายรับอื่น
$incomeStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    FROM membership_payments
    WHERE payment_status = 'approved'
      AND COALESCE(paid_at, verified_at, created_at) >= ?
      AND COALESCE(paid_at, verified_at, created_at) < ?
");
$incomeStmt->execute([$start, $end]);
[$membershipIncome, $membershipIncomeCount] = $incomeStmt->fetch(PDO::FETCH_NUM);

$donationStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    FROM finance_incomes
    WHERE status = 'active' AND income_date >= ? AND income_date < ?
");
$donationStmt->execute([$start, $end]);
[$donationIncome, $donationCount] = $donationStmt->fetch(PDO::FETCH_NUM);

$income = (float)$membershipIncome + (float)$donationIncome;
$incomeCount = (int)$membershipIncomeCount + (int)$donationCount;

// สูตรรายจ่ายเดิมนับเฉพาะรายการ active
$expenseTotalStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    FROM finance_expenses
    WHERE status = 'active' AND expense_date >= ? AND expense_date < ?
");
$expenseTotalStmt->execute([$start, $end]);
[$expenseTotal, $expenseCount] = $expenseTotalStmt->fetch(PDO::FETCH_NUM);
$expenseTotal = (float)$expenseTotal;
$expenseCount = (int)$expenseCount;
$balance = $income - $expenseTotal;

// ยอดสะสมไม่ขึ้นกับตัวกรอง เพื่อคงความหมายเดิม
[$allMembershipIncome, $allMembershipCount] = $pdo->query("
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    FROM membership_payments WHERE payment_status = 'approved'
")->fetch(PDO::FETCH_NUM);
[$allDonation, $allDonationCount] = $pdo->query("
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    FROM finance_incomes WHERE status IN ('active', 'archived')
")->fetch(PDO::FETCH_NUM);
[$allExpense, $allExpenseCount] = $pdo->query("
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    FROM finance_expenses WHERE status IN ('active', 'archived')
")->fetch(PDO::FETCH_NUM);

$allIncome = (float)$allMembershipIncome + (float)$allDonation;
$allIncomeCount = (int)$allMembershipCount + (int)$allDonationCount;
$allExpense = (float)$allExpense;
$allExpenseCount = (int)$allExpenseCount;
$allBalance = $allIncome - $allExpense;

// ดึงข้อมูลเดิมทั้งสามแหล่งมารวมเฉพาะชั้นแสดงผล
$paymentsStmt = $pdo->prepare("
    SELECT p.id, p.amount,
           COALESCE(p.paid_at, p.verified_at, p.created_at) AS item_date,
           u.first_name, u.last_name, u.email
    FROM membership_payments p
    INNER JOIN users u ON u.id = p.user_id
    WHERE p.payment_status = 'approved'
      AND COALESCE(p.paid_at, p.verified_at, p.created_at) >= ?
      AND COALESCE(p.paid_at, p.verified_at, p.created_at) < ?
");
$paymentsStmt->execute([$start, $end]);
$paymentRows = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

$manualIncomeStmt = $pdo->prepare("
    SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) AS created_by_name
    FROM finance_incomes i
    LEFT JOIN users u ON u.id = i.created_by
    WHERE i.status = 'active' AND i.income_date >= ? AND i.income_date < ?
");
$manualIncomeStmt->execute([$start, $end]);
$manualIncomeRows = $manualIncomeStmt->fetchAll(PDO::FETCH_ASSOC);

$expenseStmt = $pdo->prepare("
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS created_by_name
    FROM finance_expenses e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.status = ? AND e.expense_date >= ? AND e.expense_date < ?
");
$expenseStmt->execute([$view, $start, $end]);
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

$historyRows = [];
foreach ($paymentRows as $payment) {
    $memberName = trim($payment['first_name'] . ' ' . $payment['last_name']);
    $historyRows[] = [
        'id' => (int)$payment['id'], 'source_type' => 'membership',
        'type' => 'income', 'date' => (string)$payment['item_date'],
        'sort_date' => (string)$payment['item_date'],
        'detail' => 'ค่าสมัครสมาชิก' . ($memberName !== '' ? ' - ' . $memberName : ''),
        'subdetail' => (string)$payment['email'], 'category' => 'ค่าสมัครสมาชิก',
        'amount' => (float)$payment['amount'], 'evidence_url' => '',
        'created_by_name' => 'ระบบ'
    ];
}
foreach ($manualIncomeRows as $manualIncome) {
    $historyRows[] = [
        'id' => (int)$manualIncome['id'], 'source_type' => 'income',
        'type' => 'income', 'date' => (string)$manualIncome['income_date'],
        'sort_date' => $manualIncome['income_date'] . ' 12:00:00',
        'detail' => (string)$manualIncome['description'], 'subdetail' => '',
        'category' => (string)$manualIncome['source'],
        'amount' => (float)$manualIncome['amount'],
        'evidence_url' => !empty($manualIncome['evidence_file'])
            ? 'evidence.php?type=income&id=' . (int)$manualIncome['id'] : '',
        'created_by_name' => trim((string)$manualIncome['created_by_name'])
    ];
}
foreach ($expenses as $expense) {
    $historyRows[] = [
        'id' => (int)$expense['id'], 'source_type' => 'expense',
        'type' => 'expense', 'date' => (string)$expense['expense_date'],
        'sort_date' => $expense['expense_date'] . ' 12:00:00',
        'detail' => (string)$expense['description'], 'subdetail' => '',
        'category' => (string)$expense['category'],
        'amount' => (float)$expense['amount'],
        'evidence_url' => !empty($expense['evidence_file'])
            ? ('' . BC_BASE_PATH . '/admin/finance/evidence.php?id=') . (int)$expense['id'] : '',
        'created_by_name' => trim((string)$expense['created_by_name'])
    ];
}
usort($historyRows, static function (array $left, array $right): int {
    $dateOrder = strcmp($right['sort_date'], $left['sort_date']);
    return $dateOrder !== 0 ? $dateOrder : $right['id'] <=> $left['id'];
});

$yearRows = $pdo->query("
    SELECT DISTINCT finance_year
    FROM (
        SELECT YEAR(COALESCE(paid_at, verified_at, created_at)) AS finance_year
        FROM membership_payments WHERE payment_status = 'approved'
        UNION SELECT YEAR(income_date) FROM finance_incomes
        UNION SELECT YEAR(expense_date) FROM finance_expenses
    ) AS finance_years
    WHERE finance_year IS NOT NULL ORDER BY finance_year DESC
")->fetchAll(PDO::FETCH_COLUMN);
$availableYears = array_map('intval', $yearRows);
$availableYears[] = (int)$today->format('Y');
$availableYears[] = $selectedYear;
$availableYears = array_values(array_unique($availableYears));
rsort($availableYears, SORT_NUMERIC);

$success = $_SESSION['finance_success'] ?? '';
$error = $_SESSION['finance_error'] ?? '';
unset($_SESSION['finance_success'], $_SESSION['finance_error']);

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel finance-page">
    <div class="finance-page-head">
        <div class="finance-title-block">
            <div class="finance-title-icon"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <small>FINANCE MANAGEMENT</small>
                <h3>จัดการการเงิน</h3>
                <p>ระบบจัดการรายรับ–รายจ่ายของชมรมบิสคอม คลับ (BC1) สำหรับผู้ดูแลระบบ</p>
            </div>
        </div>

        <form method="GET" class="finance-filter" id="financeFilter">
            <input type="hidden" name="view" value="<?= financeE($view) ?>">
            <label><span>วัน</span><select name="day" id="financeFilterDay">
                <option value="">ทุกวัน</option>
                <?php for ($day = 1; $day <= 31; $day++): ?>
                    <option value="<?= $day ?>" <?= $selectedDay === $day ? 'selected' : '' ?> <?= $day > $maximumDay ? 'disabled hidden' : '' ?>><?= $day ?></option>
                <?php endfor; ?>
            </select></label>
            <label><span>เดือน</span><select name="month" id="financeFilterMonth">
                <?php foreach ($thaiMonths as $number => $name): ?>
                    <option value="<?= $number ?>" <?= $selectedMonth === $number ? 'selected' : '' ?>><?= financeE($name) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label><span>ปี</span><select name="year" id="financeFilterYear">
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="primary-btn" type="submit"><i class="fa-solid fa-filter"></i> กรองข้อมูล</button>
        </form>
    </div>

    <?php if ($success): ?><div class="form-alert success"><i class="fa-solid fa-circle-check"></i><?= financeE($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= financeE($error) ?></div><?php endif; ?>

    <div class="finance-summary">
        <article class="finance-card income"><div class="finance-card-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><div><span>รายรับช่วงที่เลือก</span><strong><?= number_format($income, 2) ?> บาท</strong><small><?= $incomeCount ?> รายการ · <?= financeE($periodLabel) ?></small></div></article>
        <article class="finance-card expense"><div class="finance-card-icon"><i class="fa-solid fa-arrow-down"></i></div><div><span>รายจ่ายช่วงที่เลือก</span><strong><?= number_format($expenseTotal, 2) ?> บาท</strong><small><?= $expenseCount ?> รายการ · <?= financeE($periodLabel) ?></small></div></article>
        <article class="finance-card balance"><div class="finance-card-icon"><i class="fa-solid fa-wallet"></i></div><div><span>ยอดคงเหลือช่วงที่เลือก</span><strong><?= number_format($balance, 2) ?> บาท</strong><small>รายรับ − รายจ่าย</small></div></article>
        <article class="finance-card total"><div class="finance-card-icon"><i class="fa-solid fa-chart-simple"></i></div><div><span>ยอดรวมทั้งหมดในระบบ</span><strong><?= number_format($allBalance, 2) ?> บาท</strong><small>รายรับ <?= number_format($allIncome, 2) ?> บาท · รายจ่าย <?= number_format($allExpense, 2) ?> บาท</small></div></article>
    </div>

    <div class="finance-form-grid">
        <article class="finance-entry-card income-entry">
            <div class="finance-entry-head"><div class="finance-entry-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><div><h3>บันทึกรายรับ</h3><p>เพิ่มรายรับอื่นหรือเงินบริจาคเข้าสู่ระบบ</p></div></div>
            <form method="POST" action="income_save.php" enctype="multipart/form-data" class="finance-entry-form">
                <input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>">
                <div class="finance-field-grid">
                    <label><span>วันที่ได้รับ <b>*</b></span><input type="date" name="income_date" value="<?= date('Y-m-d') ?>" required></label>
                    <label><span>ผู้บริจาค/แหล่งที่มา <b>*</b></span><input type="text" name="source" maxlength="150" placeholder="เช่น ศิษย์เก่า/ผู้สนับสนุน" required></label>
                    <label><span>จำนวนเงิน (บาท) <b>*</b></span><input type="number" name="amount" min="0.01" step="0.01" placeholder="ระบุจำนวนเงิน" required></label>
                    <label><span>รายละเอียด <b>*</b></span><input type="text" name="description" maxlength="500" placeholder="รายละเอียดรายรับ" required></label>
                </div>
                <label class="finance-file-field"><span>แนบหลักฐาน/สลิป</span><input type="file" name="evidence" accept="image/jpeg,image/png,image/webp,application/pdf"><small>รองรับ JPG, PNG, WEBP หรือ PDF ขนาดไม่เกิน 8 MB</small></label>
                <button class="primary-btn finance-submit income-submit" type="submit"><i class="fa-regular fa-floppy-disk"></i> บันทึกรายรับ</button>
            </form>
        </article>

        <article class="finance-entry-card expense-entry">
            <div class="finance-entry-head"><div class="finance-entry-icon"><i class="fa-solid fa-arrow-down"></i></div><div><h3>บันทึกรายจ่าย</h3><p>เพิ่มรายการรายจ่ายเข้าสู่ระบบ</p></div></div>
            <form method="POST" action="save.php" enctype="multipart/form-data" class="finance-entry-form">
                <input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>">
                <div class="finance-field-grid">
                    <label><span>วันที่จ่าย <b>*</b></span><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></label>
                    <label><span>หมวดหมู่/จ่ายค่าอะไร <b>*</b></span><input type="text" name="category" maxlength="120" placeholder="เช่น อุปกรณ์กิจกรรม" required></label>
                    <label><span>จำนวนเงิน (บาท) <b>*</b></span><input type="number" name="amount" min="0.01" max="9999999.99" step="0.01" placeholder="ระบุจำนวนเงิน" required></label>
                    <label><span>รายละเอียด <b>*</b></span><input type="text" name="description" maxlength="500" placeholder="รายละเอียดเพิ่มเติม" required></label>
                </div>
                <label class="finance-file-field"><span>แนบหลักฐาน/สลิป</span><input type="file" name="evidence" accept="image/jpeg,image/png,image/webp,application/pdf"><small>รองรับ JPG, PNG, WEBP หรือ PDF ขนาดไม่เกิน 8 MB</small></label>
                <button class="primary-btn finance-submit expense-submit" type="submit"><i class="fa-solid fa-arrow-down"></i> บันทึกรายจ่าย</button>
            </form>
        </article>
    </div>

    <section class="finance-history">
        <div class="finance-history-head">
            <div class="finance-history-title"><div class="finance-history-icon"><i class="fa-solid fa-list"></i></div><div><h3>ประวัติรายการล่าสุด</h3><p>รายรับและรายจ่าย <?= financeE($periodLabel) ?> <?= $view === 'archived' ? '· รายจ่ายที่เก็บถาวร' : '' ?></p></div></div>
            <div class="finance-history-actions">
                <?php if ($view === 'active'): ?>
                    <form method="POST" action="archive.php" data-bc-confirm data-bc-confirm-type="archive" data-bc-confirm-title="เก็บรายจ่ายทั้งเดือน" data-bc-confirm-message="เก็บรายจ่ายทั้งเดือนนี้ไว้ในข้อมูลถาวรหรือไม่?" data-bc-confirm-label="เก็บทั้งเดือน">
                        <input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>"><input type="hidden" name="month" value="<?= financeE($monthKey) ?>">
                        <button class="secondary-btn" type="submit"><i class="fa-solid fa-box-archive"></i> เก็บรายจ่ายทั้งเดือน</button>
                    </form>
                    <a class="secondary-btn" href="?day=<?= financeE($selectedDay ?? '') ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&view=archived">ดูข้อมูลที่เก็บถาวร</a>
                <?php else: ?>
                    <a class="secondary-btn" href="?day=<?= financeE($selectedDay ?? '') ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>">กลับรายการปัจจุบัน</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="finance-table-wrap"><table class="finance-history-table">
            <thead><tr><th>ลำดับ</th><th>วันที่</th><th>ประเภท</th><th>รายละเอียด</th><th>จำนวนเงิน (บาท)</th><th>หมวดหมู่/แหล่งที่มา</th><th>หลักฐาน</th><th>ผู้บันทึก</th><th>จัดการ</th></tr></thead>
            <tbody>
            <?php foreach ($historyRows as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td class="finance-date-cell"><?= financeE(date('d/m/Y', strtotime($row['date']))) ?></td>
                    <td><span class="finance-type <?= financeE($row['type']) ?>"><?= $row['type'] === 'income' ? 'รายรับ' : 'รายจ่าย' ?></span></td>
                    <td><strong><?= financeE($row['detail']) ?></strong><?php if ($row['subdetail'] !== ''): ?><small><?= financeE($row['subdetail']) ?></small><?php endif; ?></td>
                    <td class="finance-amount <?= financeE($row['type']) ?>"><?= $row['type'] === 'income' ? '+' : '-' ?><?= number_format($row['amount'], 2) ?></td>
                    <td><?= financeE($row['category'] !== '' ? $row['category'] : '-') ?></td>
                    <td><?php if ($row['evidence_url'] !== ''): ?><a class="finance-evidence-link" href="<?= financeE($row['evidence_url']) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-file-lines"></i> เปิดหลักฐาน</a><?php else: ?><span class="finance-muted">-</span><?php endif; ?></td>
                    <td><?= financeE($row['created_by_name'] !== '' ? $row['created_by_name'] : '-') ?></td>
                    <td>
                    <?php if ($row['source_type'] === 'expense'): ?>
                        <div class="finance-actions">
                        <?php if ($view === 'active'): ?>
                            <a class="action-btn edit" href="expense_edit.php?id=<?= (int)$row['id'] ?>" title="แก้ไขรายจ่าย"><i class="fa-regular fa-pen-to-square"></i></a>
                            <form method="POST" action="archive.php" data-bc-confirm data-bc-confirm-type="archive" data-bc-confirm-title="เก็บรายจ่าย" data-bc-confirm-message="เก็บรายการนี้ไว้ในข้อมูลถาวรหรือไม่?" data-bc-confirm-label="เก็บรายการ"><input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="month" value="<?= financeE($monthKey) ?>"><button class="action-btn archive" type="submit" title="เก็บถาวร"><i class="fa-solid fa-box-archive"></i></button></form>
                            <form method="POST" action="delete.php" data-bc-confirm data-bc-confirm-type="delete" data-bc-confirm-title="ลบรายจ่าย" data-bc-confirm-message="ลบรายจ่ายที่กรอกผิดถาวรหรือไม่?" data-bc-confirm-label="ลบรายการ"><input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="month" value="<?= financeE($monthKey) ?>"><button class="action-btn delete" type="submit" title="ลบรายจ่าย"><i class="fa-regular fa-trash-can"></i></button></form>
                        <?php else: ?>
                            <form method="POST" action="restore.php"><input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="month" value="<?= financeE($monthKey) ?>"><button class="action-btn restore" type="submit" title="ยกเลิกการเก็บถาวร"><i class="fa-solid fa-box-open"></i></button></form>
                            <form method="POST" action="delete.php" data-bc-confirm data-bc-confirm-type="delete" data-bc-confirm-title="ลบรายจ่ายถาวร" data-bc-confirm-message="ลบข้อมูลนี้ถาวรหรือไม่?" data-bc-confirm-label="ลบรายการ"><input type="hidden" name="csrf_token" value="<?= financeE($_SESSION['finance_csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="month" value="<?= financeE($monthKey) ?>"><input type="hidden" name="view" value="archived"><button class="action-btn delete" type="submit" title="ลบรายจ่าย"><i class="fa-regular fa-trash-can"></i></button></form>
                        <?php endif; ?>
                        </div>
                    <?php else: ?><span class="finance-muted">-</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$historyRows): ?><tr><td colspan="9" class="finance-empty">ยังไม่มีรายการในช่วงเวลาที่เลือก</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const day = document.getElementById('financeFilterDay');
    const month = document.getElementById('financeFilterMonth');
    const year = document.getElementById('financeFilterYear');
    if (!day || !month || !year) return;
    function updateDays() {
        const maximum = new Date(Number(year.value), Number(month.value), 0).getDate();
        Array.from(day.options).forEach(function (option) {
            if (option.value === '') return;
            const unavailable = Number(option.value) > maximum;
            option.disabled = unavailable;
            option.hidden = unavailable;
            if (unavailable && option.selected) day.value = '';
        });
    }
    month.addEventListener('change', updateDays);
    year.addEventListener('change', updateDays);
    updateDays();
});
</script>

<style>
.finance-page{--f-blue:#0b5ec4;--f-navy:#16345f;--f-green:#0f9c68;--f-red:#df3344;--f-border:#e2e9f2;--f-muted:#75869d}
.finance-page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px}.finance-title-block{min-width:0;display:flex;align-items:flex-start;gap:13px}.finance-title-icon{width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;color:#fff;background:linear-gradient(145deg,#0f70dc,#164eb1);border-radius:12px;box-shadow:0 8px 18px rgba(25,91,184,.18)}
.finance-title-block small{color:#3977c9;font-size:9px;font-weight:600;letter-spacing:.08em}.finance-title-block h3{margin:1px 0 3px;color:var(--f-navy);font-size:22px}.finance-title-block p{margin:0;color:var(--f-muted);font-size:11px}
.finance-filter{display:grid;grid-template-columns:78px 130px 96px max-content;align-items:end;gap:9px}.finance-filter label{min-width:0;display:grid;grid-template-rows:16px 40px;gap:4px;color:#667992;font-size:10px}.finance-filter select{width:100%;min-width:0;height:40px;padding:0 10px;border:1px solid #d8e1ed;border-radius:8px;color:#314966;background:#fff;font:inherit;outline:none}.finance-filter .primary-btn{height:40px;white-space:nowrap}
.finance-summary{margin:22px 0 18px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.finance-card{min-width:0;min-height:105px;padding:17px;display:flex;align-items:center;gap:13px;border:1px solid var(--f-border);border-radius:13px;background:#fff;box-shadow:0 5px 16px rgba(26,56,96,.035)}.finance-card-icon{width:43px;height:43px;flex:0 0 43px;display:grid;place-items:center;border-radius:50%;font-size:17px}.finance-card span,.finance-card small{display:block;color:var(--f-muted)}.finance-card span{font-size:10px}.finance-card strong{margin:4px 0 2px;display:block;font-size:20px;line-height:1.25;white-space:nowrap}.finance-card small{font-size:8.5px;line-height:1.45}
.finance-card.income{border-left:3px solid var(--f-green)}.finance-card.income .finance-card-icon{color:var(--f-green);background:#e8f8f1}.finance-card.income strong{color:var(--f-green)}.finance-card.expense{border-left:3px solid var(--f-red)}.finance-card.expense .finance-card-icon{color:var(--f-red);background:#ffedf0}.finance-card.expense strong{color:var(--f-red)}.finance-card.balance{border-left:3px solid #2c82dd}.finance-card.balance .finance-card-icon{color:#2c82dd;background:#eaf4ff}.finance-card.balance strong{color:#1768cf}.finance-card.total{border-left:3px solid #775acb}.finance-card.total .finance-card-icon{color:#775acb;background:#f0edff}.finance-card.total strong{color:#6749bf}
.finance-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.finance-entry-card{min-width:0;overflow:hidden;border:1px solid var(--f-border);border-radius:14px;background:#fff}.finance-entry-head{min-height:76px;padding:15px 17px;display:flex;align-items:center;gap:11px}.income-entry .finance-entry-head{background:linear-gradient(135deg,#f0fbf7,#e9f8f3)}.expense-entry .finance-entry-head{background:linear-gradient(135deg,#fff4f5,#fff0f2)}.finance-entry-icon{width:39px;height:39px;flex:0 0 39px;display:grid;place-items:center;color:#fff;border-radius:50%}.income-entry .finance-entry-icon{background:var(--f-green)}.expense-entry .finance-entry-icon{background:var(--f-red)}.finance-entry-head h3{margin:0;font-size:17px}.income-entry .finance-entry-head h3{color:#16845e}.expense-entry .finance-entry-head h3{color:#c83543}.finance-entry-head p{margin:2px 0 0;color:var(--f-muted);font-size:9px}
.finance-entry-form{height:calc(100% - 76px);padding:16px;display:flex;flex-direction:column;gap:13px}.finance-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.finance-entry-form label{min-width:0;color:#536881;font-size:10px;font-weight:500}.finance-entry-form label>span{display:block;margin-bottom:5px}.finance-entry-form label b{color:#dd3544}.finance-entry-form input{width:100%;height:40px;padding:0 11px;border:1px solid #d8e1ec;border-radius:7px;color:#2f4662;background:#fff;font:inherit;outline:none}.finance-entry-form input:focus,.finance-filter select:focus{border-color:#73a8e5;box-shadow:0 0 0 3px rgba(45,121,210,.08)}
.finance-file-field{display:block}.finance-file-field input[type=file]{height:auto;min-height:40px;padding:5px;color:#718198}.finance-file-field input[type=file]::file-selector-button{min-height:28px;margin-right:10px;padding:0 12px;border:0;border-radius:6px;color:#345271;background:#edf2f7;font:inherit;cursor:pointer}.finance-file-field small{margin-top:5px;display:block;color:#8a98aa;font-size:8.5px}.finance-submit{width:100%;min-height:41px;margin-top:auto}.finance-submit.income-submit{background:var(--f-green)}.finance-submit.income-submit:hover{background:#0b8056}.finance-submit.expense-submit{background:var(--f-red)}.finance-submit.expense-submit:hover{background:#bf2636}
.finance-history{margin-top:18px;padding:15px;border:1px solid var(--f-border);border-radius:14px;background:#fff}.finance-history-head{margin-bottom:13px;display:flex;align-items:center;justify-content:space-between;gap:16px}.finance-history-title{min-width:0;display:flex;align-items:center;gap:10px}.finance-history-icon{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;color:#1169cc;background:#eaf3ff;border-radius:9px}.finance-history-title h3{margin:0;color:var(--f-navy);font-size:16px}.finance-history-title p{margin:2px 0 0;color:var(--f-muted);font-size:9px}.finance-history-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}.finance-history-actions form{display:inline-flex;margin:0}
.finance-table-wrap{width:100%;overflow-x:auto;border:1px solid #e7edf4;border-radius:10px}.finance-history-table{width:100%;min-width:1040px;border-collapse:collapse;background:#fff;font-size:10px}.finance-history-table th{padding:11px 12px;color:#63758d;background:#f3f7fb;text-align:left;font-weight:600;white-space:nowrap}.finance-history-table td{padding:11px 12px;color:#354b67;border-top:1px solid #edf1f5;vertical-align:middle}.finance-history-table td strong,.finance-history-table td small{display:block}.finance-history-table td small{margin-top:3px;color:#8593a6;font-size:8.5px}.finance-date-cell,.finance-amount{white-space:nowrap}.finance-type{padding:4px 9px;display:inline-flex;border-radius:999px;font-size:8.5px;font-weight:600;white-space:nowrap}.finance-type.income{color:#138157;background:#e4f7ee}.finance-type.expense{color:#bd3340;background:#ffe9ec}.finance-amount{font-weight:700}.finance-amount.income{color:var(--f-green)}.finance-amount.expense{color:var(--f-red)}.finance-evidence-link{display:inline-flex;align-items:center;gap:5px;color:#1768cf;font-weight:500;white-space:nowrap}.finance-muted{color:#9ca8b7}.finance-actions{display:flex;align-items:center;gap:5px}.finance-actions form{display:inline-flex;margin:0}.finance-actions .action-btn{border:0;cursor:pointer}.finance-actions .action-btn.restore{color:#16805b;background:#e8f7ef}.finance-empty{padding:28px!important;color:#8492a5!important;text-align:center}
@media(max-width:1250px){.finance-page-head{align-items:flex-start;flex-direction:column}.finance-filter{width:100%;grid-template-columns:82px 150px 110px max-content}.finance-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.finance-form-grid{grid-template-columns:1fr}.finance-history-head{align-items:flex-start;flex-direction:column}.finance-history-actions{width:100%;justify-content:flex-start}}
@media(max-width:620px){.finance-page-head{gap:16px}.finance-title-block h3{font-size:19px}.finance-filter{grid-template-columns:repeat(3,minmax(0,1fr))}.finance-filter .primary-btn{grid-column:1/-1;width:100%}.finance-summary{grid-template-columns:1fr}.finance-field-grid{grid-template-columns:1fr}.finance-history-actions,.finance-history-actions form,.finance-history-actions .secondary-btn{width:100%}.finance-history-actions .secondary-btn{justify-content:center}}
</style>

<?php include __DIR__ . '/../includes/confirmation_modal.php'; ?>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
