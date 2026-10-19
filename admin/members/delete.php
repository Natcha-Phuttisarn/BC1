<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (empty($_SESSION['member_archive_csrf'])) {
    $_SESSION['member_archive_csrf'] = bin2hex(random_bytes(32));
}

$memberId = isset($_GET['id'])
    ? (int)$_GET['id']
    : (int)($_POST['member_id'] ?? 0);

if ($memberId <= 0) {
    $_SESSION['member_error'] = 'ไม่พบข้อมูลสมาชิก';
    header('Location: index.php');
    exit;
}

/* ดึงข้อมูลสมาชิก */
$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.user_id,
        m.student_id,
        u.first_name,
        u.last_name,
        u.email
    FROM members m
    INNER JOIN users u ON u.id = m.user_id
    WHERE m.id = ?
    LIMIT 1
");

$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['member_error'] = 'ไม่พบข้อมูลสมาชิก';
    header('Location: index.php');
    exit;
}


/* =========================================
   ยืนยันลบแล้ว
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['member_archive_csrf'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['member_error'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        header('Location: index.php');
        exit;
    }

    try {

        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM members WHERE id = ?');
        $delete->execute([$memberId]);
        if ($delete->rowCount() < 1) throw new RuntimeException('ไม่พบสมาชิกที่ต้องการลบ');
        $disable = $pdo->prepare("UPDATE users SET account_status='inactive' WHERE id=? AND role='user'");
        $disable->execute([(int)$member['user_id']]);
        $application = $pdo->prepare("UPDATE membership_applications SET application_status='rejected', admin_note='สมาชิกถูกลบโดยผู้ดูแลระบบ' WHERE user_id=? AND application_status='approved'");
        $application->execute([(int)$member['user_id']]);
        $pdo->commit();

        $_SESSION['member_success'] = 'ลบสมาชิกและปิดบัญชีเรียบร้อยแล้ว บัญชีนี้จะเข้าสู่ระบบไม่ได้';
        logAdminAction($pdo, 'delete', 'member', $memberId, 'ลบสมาชิกและปิดบัญชี: ' . $member['student_id'], $member, ['member_deleted'=>true,'account_status'=>'inactive']);

        header('Location: index.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        $_SESSION['member_error'] =
            'ไม่สามารถลบสมาชิกได้ เนื่องจากมีข้อมูลที่เกี่ยวข้อง';

        header('Location: index.php');
        exit;
    }
}


/* =========================================
   หน้า Confirm
========================================= */

$adminPageTitle = 'ยืนยันการลบสมาชิก';

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel">

    <div style="max-width:620px;margin:30px auto;text-align:center;padding:35px;">

        <div style="
            width:70px;
            height:70px;
            margin:0 auto 18px;
            border-radius:50%;
            background:#fff0f0;
            color:#d94b4b;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:27px;
        ">
            <i class="fa-regular fa-trash-can"></i>
        </div>

        <h2 style="color:#233f64;font-size:20px;margin-bottom:8px;">
            ยืนยันการลบสมาชิก
        </h2>

        <p style="color:#7f8da0;font-size:12px;line-height:1.8;">
            คุณต้องการลบ
            <strong style="color:#354f70;">
                <?= htmlspecialchars(
                    $member['first_name'] . ' ' . $member['last_name']
                ) ?>
            </strong>
            ออกจากรายชื่อสมาชิกและปิดบัญชีนี้หรือไม่?
        </p>

        <div style="
            margin:20px 0;
            padding:15px;
            background:#f8fafd;
            border:1px solid #e8edf4;
            border-radius:10px;
            text-align:left;
            font-size:11px;
            color:#63758d;
        ">
            <p>
                <strong>รหัสนิสิต:</strong>
                <?= htmlspecialchars($member['student_id']) ?>
            </p>

            <p style="margin-top:5px;">
                <strong>อีเมล:</strong>
                <?= htmlspecialchars($member['email']) ?>
            </p>
        </div>

        <div style="
            margin-bottom:20px;
            padding:12px;
            background:#fff8e8;
            border:1px solid #f6e2a9;
            border-radius:9px;
            color:#997000;
            font-size:10px;
            line-height:1.7;
        ">
            <i class="fa-solid fa-triangle-exclamation"></i>
            ข้อมูลการสมัครและการชำระเงินเดิมจะยังคงเก็บไว้ แต่สมาชิกจะถูกลบออกจากรายชื่อ และบัญชีจะไม่สามารถเข้าสู่ระบบได้
        </div>

        <form method="POST">

            <input
                type="hidden"
                name="member_id"
                value="<?= (int)$member['id'] ?>"
            >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['member_archive_csrf']) ?>">

            <div style="
                display:flex;
                justify-content:center;
                gap:10px;
            ">

                <a
                    href="index.php"
                    class="secondary-btn"
                >
                    ยกเลิก
                </a>

                <button
                    type="submit"
                    class="primary-btn"
                    style="background:#d94b4b;"
                >
                    <i class="fa-regular fa-trash-can"></i>
                    ยืนยันการลบและปิดบัญชี
                </button>

            </div>

        </form>

    </div>

</section>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
