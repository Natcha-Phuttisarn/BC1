<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$adminPageTitle = 'บัญชีผู้ใช้งาน';
$search = trim($_GET['search'] ?? '');
$params = [];

$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.profile_image,
        u.role,
        u.account_status,
        u.created_at,
        m.id AS member_id,
        m.student_id
    FROM users u
    LEFT JOIN members m ON m.user_id = u.id
";

if ($search !== '') {
    $sql .= "
        WHERE u.first_name LIKE ?
           OR u.last_name LIKE ?
           OR u.email LIKE ?
           OR m.student_id LIKE ?
    ";
    $keyword = '%' . $search . '%';
    $params = [$keyword, $keyword, $keyword, $keyword];
}

$sql .= " ORDER BY u.role = 'admin' DESC, u.created_at DESC ";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_SESSION['user_admin_success'] ?? '';
$error = $_SESSION['user_admin_error'] ?? '';
unset($_SESSION['user_admin_success'], $_SESSION['user_admin_error']);

include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<section class="panel full-panel user-admin-page">
    <div class="panel-head">
        <div>
            <small>USER ACCOUNTS</small>
            <h3>บัญชีผู้ใช้งานทั้งหมด</h3>
            <p>แก้ไขข้อมูลบัญชี สิทธิ์ และสถานะการเข้าใช้งาน</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="form-alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="form-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="GET" class="user-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ อีเมล หรือรหัสนิสิต">
        <button type="submit">ค้นหา</button>
        <?php if ($search !== ''): ?><a href="index.php">ล้าง</a><?php endif; ?>
    </form>

    <div class="user-table-wrap">
        <table class="user-table">
            <thead>
                <tr>
                    <th>ผู้ใช้งาน</th>
                    <th>รหัสนิสิต</th>
                    <th>สิทธิ์</th>
                    <th>สถานะบัญชี</th>
                    <th>วันที่สร้าง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-identity">
                                    <div class="user-avatar">
                                        <?php if (!empty($user['profile_image'])): ?>
                                            <img src="<?= BC_BASE_PATH ?>/uploads/profiles/<?= htmlspecialchars(basename($user['profile_image'])) ?>" alt="">
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_substr($user['first_name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                        <small><?= htmlspecialchars($user['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['student_id'] ?: '-') ?></td>
                            <td><span class="user-badge role <?= htmlspecialchars($user['role']) ?>"><?= $user['role'] === 'admin' ? 'แอดมิน' : 'ผู้ใช้' ?></span></td>
                            <td><span class="user-badge status <?= htmlspecialchars($user['account_status']) ?>"><?= htmlspecialchars($user['account_status']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <a class="user-edit-btn" href="edit.php?id=<?= (int)$user['id'] ?>"><i class="fa-regular fa-pen-to-square"></i> แก้ไข</a>
                                <?php if (!empty($user['member_id'])): ?>
                                    <a class="user-member-btn" href="../members/edit.php?id=<?= (int)$user['member_id'] ?>">ข้อมูลสมาชิก</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="user-empty">ไม่พบบัญชีผู้ใช้งาน</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.user-admin-page .panel-head p{margin:4px 0 0;color:#8b98a8;font-size:10px}.user-search{margin:18px 0;display:flex;align-items:center;gap:8px}.user-search i{color:#8795a7}.user-search input{min-width:260px;max-width:430px;flex:1;height:42px;padding:0 12px;border:1px solid #dbe3ec;border-radius:9px;font-family:inherit}.user-search button,.user-search a{height:42px;padding:0 15px;border:0;border-radius:9px;display:inline-flex;align-items:center;background:#1768cf;color:#fff;font-family:inherit;font-size:10px;text-decoration:none;cursor:pointer}.user-search a{background:#eef3f8;color:#526c88}.user-table-wrap{overflow:auto}.user-table{width:100%;border-collapse:collapse}.user-table th,.user-table td{padding:13px 10px;border-bottom:1px solid #e8edf3;text-align:left;font-size:10px;white-space:nowrap}.user-table th{color:#718198;background:#f8fafc}.user-identity{display:flex;align-items:center;gap:10px}.user-identity strong,.user-identity small{display:block}.user-identity small{margin-top:2px;color:#8b97a6}.user-avatar{width:38px;height:38px;overflow:hidden;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#eaf2fd;color:#1768cf;font-weight:600}.user-avatar img{width:100%;height:100%;object-fit:cover}.user-badge{padding:5px 9px;border-radius:999px;font-size:9px}.user-badge.role.admin{background:#e8f1ff;color:#155eb8}.user-badge.role.user{background:#eef1f4;color:#667385}.user-badge.status.active{background:#e7f7ee;color:#198052}.user-badge.status.inactive,.user-badge.status.blocked{background:#fff0f1;color:#b34750}.user-edit-btn,.user-member-btn{margin-right:5px;padding:7px 9px;border-radius:7px;background:#eaf2fd;color:#1768cf;text-decoration:none;font-size:9px}.user-member-btn{background:#eef6ef;color:#377549}.user-empty{padding:45px!important;text-align:center!important;color:#8a96a6}@media(max-width:650px){.user-search{align-items:stretch;flex-wrap:wrap}.user-search input{min-width:100%;max-width:none}.user-table{min-width:780px}}
</style>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
