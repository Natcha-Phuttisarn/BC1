<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location:index.php');
    exit;
}

$statement = $pdo->prepare("
    SELECT *
    FROM finance_expenses
    WHERE id = ? AND status = 'active'
    LIMIT 1
");
$statement->execute([$id]);
$expense = $statement->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    $_SESSION['finance_error'] = 'ไม่พบรายจ่าย';
    header('Location:index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_SESSION['finance_csrf'])
        || !hash_equals(
            $_SESSION['finance_csrf'],
            $_POST['csrf_token'] ?? ''
        )
    ) {
        $_SESSION['finance_error'] = 'คำขอไม่ถูกต้อง';
        header('Location:index.php');
        exit;
    }

    $date = trim($_POST['expense_date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);

    if (
        !$parsedDate
        || $parsedDate->format('Y-m-d') !== $date
        || $category === ''
        || $description === ''
        || $amount <= 0
    ) {
        $_SESSION['finance_error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        header('Location:expense_edit.php?id=' . $id);
        exit;
    }

    $update = $pdo->prepare("
        UPDATE finance_expenses
        SET expense_date = ?, category = ?, description = ?, amount = ?
        WHERE id = ?
    ");
    $update->execute([
        $date,
        $category,
        $description,
        number_format($amount, 2, '.', ''),
        $id
    ]);

    logAdminAction(
        $pdo,
        'update',
        'finance_expense',
        $id,
        'แก้ไขรายจ่าย: ' . $category,
        $expense,
        [
            'expense_date' => $date,
            'amount' => $amount,
            'description' => $description
        ]
    );

    $_SESSION['finance_success'] = 'แก้ไขรายจ่ายแล้ว';
    header('Location:index.php?month=' . rawurlencode(substr($date, 0, 7)));
    exit;
}

$evidenceFile = trim((string)($expense['evidence_file'] ?? ''));
$evidenceExtension = strtolower(pathinfo($evidenceFile, PATHINFO_EXTENSION));
$evidenceIsImage = in_array(
    $evidenceExtension,
    ['jpg', 'jpeg', 'png', 'webp'],
    true
);
$evidenceUrl = 'evidence.php?id=' . $id;

$adminPageTitle = 'แก้ไขรายจ่าย';
include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel expense-edit-page">
    <div class="expense-edit-heading">
        <div class="expense-edit-title-icon" aria-hidden="true">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div>
            <small>FINANCE MANAGEMENT</small>
            <h3>แก้ไขรายจ่าย</h3>
            <p>ปรับปรุงข้อมูลรายจ่ายที่บันทึกไว้ โดยไม่เปลี่ยนหลักฐานเดิม</p>
        </div>
    </div>

    <div class="expense-edit-card">
        <div class="expense-edit-card-head">
            <div class="expense-edit-card-icon" aria-hidden="true">
                <i class="fa-regular fa-file-lines"></i>
            </div>
            <div>
                <h4>ข้อมูลรายการ</h4>
                <p>กรุณาตรวจสอบและแก้ไขข้อมูลรายจ่ายให้ถูกต้อง</p>
            </div>
        </div>

        <form method="POST" class="expense-edit-form">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['finance_csrf'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="expense-edit-fields">
                <label>
                    <span>วันที่จ่าย <b>*</b></span>
                    <input
                        type="date"
                        name="expense_date"
                        value="<?= htmlspecialchars(
                            $expense['expense_date'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>จำนวนเงิน (บาท) <b>*</b></span>
                    <div class="expense-amount-input">
                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $expense['amount'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                        <span>฿</span>
                    </div>
                </label>

                <label>
                    <span>หมวดหมู่ <b>*</b></span>
                    <input
                        type="text"
                        name="category"
                        maxlength="120"
                        value="<?= htmlspecialchars(
                            $expense['category'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>รายละเอียด <b>*</b></span>
                    <input
                        type="text"
                        name="description"
                        maxlength="500"
                        value="<?= htmlspecialchars(
                            $expense['description'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </label>
            </div>

            <div class="expense-evidence-section">
                <div class="expense-evidence-title">
                    <div>
                        <span>หลักฐานการจ่ายเงินเดิม</span>
                        <small>หน้านี้แสดงหลักฐานที่บันทึกไว้เท่านั้น</small>
                    </div>

                    <?php if ($evidenceFile !== ''): ?>
                        <a
                            class="secondary-btn expense-evidence-open"
                            href="<?= htmlspecialchars($evidenceUrl, ENT_QUOTES, 'UTF-8') ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            เปิดหลักฐาน
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($evidenceFile !== ''): ?>
                    <div class="expense-evidence-file">
                        <?php if ($evidenceIsImage): ?>
                            <a
                                class="expense-evidence-preview"
                                href="<?= htmlspecialchars($evidenceUrl, ENT_QUOTES, 'UTF-8') ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                <img
                                    src="<?= htmlspecialchars($evidenceUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="หลักฐานการจ่ายเงิน"
                                >
                            </a>
                        <?php else: ?>
                            <div class="expense-evidence-pdf" aria-hidden="true">
                                <i class="fa-regular fa-file-pdf"></i>
                            </div>
                        <?php endif; ?>

                        <div class="expense-evidence-meta">
                            <strong><?= htmlspecialchars(
                                basename($evidenceFile),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></strong>
                            <small>
                                <?= $evidenceExtension !== ''
                                    ? strtoupper(htmlspecialchars(
                                        $evidenceExtension,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ))
                                    : 'ไฟล์หลักฐาน' ?>
                            </small>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="expense-evidence-empty">
                        <i class="fa-solid fa-file-circle-xmark"></i>
                        <span>รายการนี้ไม่มีไฟล์หลักฐาน</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="expense-edit-actions">
                <button class="primary-btn" type="submit">
                    <i class="fa-regular fa-floppy-disk"></i>
                    บันทึกการแก้ไข
                </button>
                <a
                    class="secondary-btn"
                    href="index.php?month=<?= htmlspecialchars(
                        substr($expense['expense_date'], 0, 7),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    <i class="fa-solid fa-xmark"></i>
                    ยกเลิก
                </a>
            </div>
        </form>
    </div>
</section>

<style>
.expense-edit-page {
    --expense-edit-blue: #0b5dcc;
    --expense-edit-navy: #17345f;
    --expense-edit-border: #dce5f0;
    --expense-edit-muted: #74859b;
}

.expense-edit-heading {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 13px;
}

.expense-edit-title-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: grid;
    place-items: center;
    color: #ffffff;
    background: linear-gradient(145deg, #2378dd, #0d50b5);
    border-radius: 12px;
    font-size: 18px;
    box-shadow: 0 8px 20px rgba(22, 91, 185, .16);
}

.expense-edit-heading small {
    color: #3976c8;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: .08em;
}

.expense-edit-heading h3 {
    margin: 1px 0 3px;
    color: var(--expense-edit-navy);
    font-size: 22px;
}

.expense-edit-heading p {
    margin: 0;
    color: var(--expense-edit-muted);
    font-size: 11px;
}

.expense-edit-card {
    overflow: hidden;
    border: 1px solid #e2e9f2;
    border-radius: 16px;
    background: #ffffff;
}

.expense-edit-card-head {
    min-height: 90px;
    padding: 19px 24px;
    display: flex;
    align-items: center;
    gap: 13px;
    background: linear-gradient(110deg, #edf6ff, #f7fbff);
}

.expense-edit-card-icon {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    display: grid;
    place-items: center;
    color: var(--expense-edit-blue);
    background: #ffffff;
    border: 1px solid #d9e9fb;
    border-radius: 11px;
    font-size: 19px;
}

.expense-edit-card-head h4 {
    margin: 0;
    color: var(--expense-edit-navy);
    font-size: 18px;
}

.expense-edit-card-head p {
    margin: 3px 0 0;
    color: var(--expense-edit-muted);
    font-size: 10px;
}

.expense-edit-form {
    padding: 24px;
}

.expense-edit-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 24px;
}

.expense-edit-fields label {
    min-width: 0;
    color: #314967;
    font-size: 11px;
    font-weight: 600;
}

.expense-edit-fields label > span {
    margin-bottom: 7px;
    display: block;
}

.expense-edit-fields label b {
    color: #e03948;
}

.expense-edit-fields input {
    width: 100%;
    height: 45px;
    padding: 0 13px;
    border: 1px solid var(--expense-edit-border);
    border-radius: 9px;
    color: #233b59;
    background: #ffffff;
    font: inherit;
    font-weight: 400;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}

.expense-edit-fields input:focus {
    border-color: #6ba4e6;
    box-shadow: 0 0 0 3px rgba(28, 105, 202, .08);
}

.expense-amount-input {
    position: relative;
}

.expense-amount-input input {
    padding-right: 48px;
}

.expense-amount-input > span {
    width: 42px;
    position: absolute;
    top: 1px;
    right: 1px;
    bottom: 1px;
    display: grid;
    place-items: center;
    color: #34516f;
    background: #f3f6fa;
    border-left: 1px solid var(--expense-edit-border);
    border-radius: 0 8px 8px 0;
    font-weight: 600;
}

.expense-evidence-section {
    margin-top: 22px;
    overflow: hidden;
    border: 1px solid var(--expense-edit-border);
    border-radius: 11px;
    background: #fbfdff;
}

.expense-evidence-title {
    min-height: 62px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    border-bottom: 1px solid #e6edf5;
}

.expense-evidence-title span,
.expense-evidence-title small {
    display: block;
}

.expense-evidence-title span {
    color: #314967;
    font-size: 11px;
    font-weight: 600;
}

.expense-evidence-title small {
    margin-top: 3px;
    color: #8492a4;
    font-size: 9px;
}

.expense-evidence-open {
    flex: 0 0 auto;
}

.expense-evidence-file {
    min-height: 78px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.expense-evidence-preview,
.expense-evidence-pdf {
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    overflow: hidden;
    display: grid;
    place-items: center;
    border: 1px solid #d8e2ed;
    border-radius: 8px;
    background: #ffffff;
}

.expense-evidence-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.expense-evidence-pdf {
    color: #d3404f;
    background: #fff1f2;
    font-size: 23px;
}

.expense-evidence-meta strong,
.expense-evidence-meta small {
    display: block;
}

.expense-evidence-meta strong {
    color: #2e4765;
    font-size: 11px;
    word-break: break-word;
}

.expense-evidence-meta small {
    margin-top: 3px;
    color: #8090a4;
    font-size: 9px;
}

.expense-evidence-empty {
    min-height: 78px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #8a98aa;
    font-size: 10px;
}

.expense-edit-actions {
    margin-top: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.expense-edit-actions .primary-btn,
.expense-edit-actions .secondary-btn {
    min-width: 150px;
    min-height: 43px;
}

@media (max-width: 720px) {
    .expense-edit-card-head,
    .expense-edit-form {
        padding-left: 16px;
        padding-right: 16px;
    }

    .expense-edit-fields {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .expense-evidence-title {
        align-items: stretch;
        flex-direction: column;
    }

    .expense-evidence-open {
        width: 100%;
    }

    .expense-edit-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .expense-edit-actions .primary-btn,
    .expense-edit-actions .secondary-btn {
        width: 100%;
    }
}
</style>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
