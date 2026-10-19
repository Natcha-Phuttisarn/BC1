<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// form.php
// หน้านี้ใช้สำหรับเพิ่มหรือแก้ไขข้อมูลบุคลากรในระบบ Admin

require_once __DIR__ . '/../includes/admin_auth.php';

$id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

$isEdit = $id > 0;

$adminPageTitle =
    $isEdit
    ? 'แก้ไขบุคลากร'
    : 'เพิ่มบุคลากร';


$person = [
    'id' => 0,
    'type' => 'committee',
    'name' => '',
    'position' => '',
    'department' => '',
    'description' => '',
    'phone' => '',
    'email' => '',
    'image' => '',
    'started_at' => '',
    'ended_at' => '',
    'show_phone' => 0,
    'show_email' => 0,
    'display_order' => 1,
    'status' => 'active'
];


// =========================================
// ถ้าเป็นการแก้ไข
// =========================================

if ($isEdit) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM personnel
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $result = $stmt->fetch();

    if (!$result) {

        $_SESSION['team_error'] =
            'ไม่พบข้อมูลบุคลากร';

        header('Location: index.php');
        exit;
    }

    $person = $result;
}


$error = $_SESSION['team_error'] ?? '';

unset($_SESSION['team_error']);

if (empty($_SESSION['team_csrf'])) {
    $_SESSION['team_csrf'] = bin2hex(random_bytes(32));
}


include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';

?>


<section class="panel full-panel">

    <div class="panel-head">

        <div>

            <small>
                PERSONNEL FORM
            </small>

            <h3>

                <?= $isEdit
                    ? 'แก้ไขข้อมูลบุคลากร'
                    : 'เพิ่มบุคลากรใหม่'
                ?>

            </h3>

        </div>


        <a
            href="index.php"
            class="secondary-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            กลับ

        </a>

    </div>


    <?php if ($error): ?>

        <div class="form-alert error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <form
        action="save.php"
        method="POST"
        enctype="multipart/form-data"
        class="activity-form"
    >

        <input
            type="hidden"
            name="id"
            value="<?= (int)$person['id'] ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['team_csrf']) ?>"
        >

        <!-- =====================================
             ข้อมูลบุคลากร
        ====================================== -->

        <div class="form-section">

            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-solid fa-user"></i>

                </div>

                <div>

                    <h4>
                        ข้อมูลบุคลากร
                    </h4>

                    <p>
                        ข้อมูลสำหรับแสดงบนหน้าเว็บไซต์
                    </p>

                </div>

            </div>


            <div class="form-grid">


                <!-- ประเภท -->

                <div class="form-group">

                    <label>
                        ประเภทบุคลากร
                        <span>*</span>
                    </label>


                    <select
                        name="type"
                        required
                    >

                        <option
                            value="advisor"
                            <?= $person['type'] === 'advisor'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            อาจารย์ที่ปรึกษา
                        </option>


                        <option
                            value="committee"
                            <?= $person['type'] === 'committee'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            คณะกรรมการชมรม
                        </option>

                    </select>

                </div>


                <!-- ลำดับ -->

                <div class="form-group">

                    <label>
                        ลำดับการแสดงผล
                    </label>

                    <input
                        type="number"
                        name="display_order"
                        value="<?= (int)$person['display_order'] ?>"
                        min="1"
                    >

                    <small class="form-help">
                        เลขน้อยจะแสดงก่อน
                    </small>

                </div>


                <!-- ชื่อ -->

                <div class="form-group full">

                    <label>
                        ชื่อ - นามสกุล
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($person['name']) ?>"
                        placeholder="กรอกชื่อ - นามสกุล"
                        required
                    >

                </div>


                <!-- ตำแหน่ง -->

                <div class="form-group full">

                    <label>
                        ตำแหน่ง
                        <span>*</span>
                    </label>


                    <input
                        type="text"
                        name="position"
                        list="positionList"
                        value="<?= htmlspecialchars(
                            $person['position']
                        ) ?>"
                        placeholder="คลิกเพื่อเลือกหรือพิมพ์ตำแหน่ง"
                        required
                    >


                    <datalist id="positionList">

                        <option value="อาจารย์ที่ปรึกษาชมรม">

                        <option value="ประธานชมรม">

                        <option value="รองประธานชมรมฝ่ายกิจกรรม">

                        <option value="รองประธานชมรมฝ่ายบริหาร">

                        <option value="เลขาธิการ">

                        <option value="เหรัญญิก">

                        <option value="หัวหน้าฝ่ายกิจกรรม">

                        <option value="ประชาสัมพันธ์">

                    </datalist>


                    <small class="form-help">
                        สามารถเลือกจากรายการหรือพิมพ์ตำแหน่งอื่นได้
                    </small>

                </div>


                <!-- สาขา -->

                <div class="form-group full">

                    <label>
                        สาขา / หน่วยงาน
                    </label>

                    <input
                        type="text"
                        name="department"
                        value="<?= htmlspecialchars(
                            $person['department'] ?? ''
                        ) ?>"
                        placeholder="เช่น สาขาคอมพิวเตอร์ธุรกิจ"
                    >

                </div>


                <!-- โทรศัพท์ -->

                <div class="form-group">

                    <label>
                        เบอร์โทรศัพท์
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars(
                            $person['phone'] ?? ''
                        ) ?>"
                        placeholder="กรอกเบอร์โทรศัพท์"
                    >

                    <label class="team-visibility-option">
                        <input
                            type="checkbox"
                            name="show_phone"
                            value="1"
                            <?= !empty($person['show_phone']) ? 'checked' : '' ?>
                        >
                        แสดงเบอร์โทรบนหน้าเว็บไซต์
                    </label>

                </div>


                <!-- Email -->

                <div class="form-group">

                    <label>
                        อีเมล
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $person['email'] ?? ''
                        ) ?>"
                        placeholder="example@msu.ac.th"
                    >

                    <label class="team-visibility-option">
                        <input
                            type="checkbox"
                            name="show_email"
                            value="1"
                            <?= !empty($person['show_email']) ? 'checked' : '' ?>
                        >
                        แสดงอีเมลบนหน้าเว็บไซต์
                    </label>

                </div>


                <!-- Description -->

                <div class="form-group full">

                    <label>
                        รายละเอียดเพิ่มเติม
                    </label>

                    <textarea
                        name="description"
                        rows="5"
                        placeholder="รายละเอียดเกี่ยวกับบุคลากร"
                    ><?= htmlspecialchars(
                        $person['description'] ?? ''
                    ) ?></textarea>

                </div>

            </div>

        </div>


        <!-- =====================================
             ระยะเวลาการปฏิบัติหน้าที่
        ====================================== -->

        <div class="form-section">

            <div class="form-section-title">
                <div class="form-section-icon">
                    <i class="fa-regular fa-calendar"></i>
                </div>
                <div>
                    <h4>ระยะเวลาการปฏิบัติหน้าที่</h4>
                    <p>ไม่บังคับกำหนด อาจารย์ที่ปรึกษาระยะยาวสามารถเว้นว่างได้</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>วันที่เริ่มทำหน้าที่</label>
                    <input
                        type="date"
                        name="started_at"
                        value="<?= htmlspecialchars($person['started_at'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>วันที่สิ้นสุดหน้าที่</label>
                    <input
                        type="date"
                        name="ended_at"
                        value="<?= htmlspecialchars($person['ended_at'] ?? '') ?>"
                    >
                    <small class="form-help">เว้นว่างหากยังปฏิบัติหน้าที่อยู่</small>
                </div>
            </div>

        </div>


        <!-- =====================================
             รูปภาพ
        ====================================== -->

        <div class="form-section">

            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-regular fa-image"></i>

                </div>

                <div>

                    <h4>
                        รูปภาพบุคลากร
                    </h4>

                    <p>
                        ใช้สำหรับแสดงในหน้าบุคลากร
                    </p>

                </div>

            </div>


            <?php if (!empty($person['image'])): ?>

                <div class="team-form-preview">

                    <img
                        src="<?= BC_BASE_PATH ?>/<?= htmlspecialchars(
                            $person['image']
                        ) ?>"
                        alt="รูปปัจจุบัน"
                    >

                    <div>

                        <strong>
                            รูปปัจจุบัน
                        </strong>

                        <span>
                            หากไม่เลือกรูปใหม่ ระบบจะใช้รูปเดิม
                        </span>

                        <label class="team-remove-image">
                            <input type="checkbox" name="remove_image" value="1">
                            ลบรูปปัจจุบัน
                        </label>

                    </div>

                </div>

            <?php endif; ?>


            <div class="form-group full">

                <label>
                    เลือกรูปภาพ
                </label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small class="form-help">
                    JPG, PNG หรือ WEBP ไม่เกิน 5 MB
                </small>

            </div>

        </div>


        <!-- =====================================
             สถานะ
        ====================================== -->

        <div class="form-section">

            <div class="form-section-title">

                <div class="form-section-icon">

                    <i class="fa-regular fa-eye"></i>

                </div>

                <div>

                    <h4>
                        การแสดงผล
                    </h4>

                    <p>
                        กำหนดว่าจะให้แสดงบนเว็บไซต์หรือไม่
                    </p>

                </div>

            </div>


            <div class="form-group">

                <label>
                    สถานะ
                </label>

                <select name="status">

                    <option
                        value="active"
                        <?= $person['status'] === 'active'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        แสดงบนเว็บไซต์
                    </option>


                    <option
                        value="hidden"
                        <?= $person['status'] === 'hidden'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        ซ่อนจากเว็บไซต์
                    </option>

                    <option
                        value="former"
                        <?= $person['status'] === 'former'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        สิ้นสุดหน้าที่ (เก็บประวัติ)
                    </option>

                </select>

                <small class="form-help">
                    แนะนำให้ใช้ “สิ้นสุดหน้าที่” แทนการลบ เพื่อเก็บข้อมูลเดิมไว้
                </small>

            </div>

        </div>


        <!-- =====================================
             BUTTON
        ====================================== -->

        <div class="form-actions">

            <a
                href="index.php"
                class="secondary-btn"
            >
                ยกเลิก
            </a>


            <button
                type="submit"
                class="primary-btn"
            >

                <i class="fa-solid fa-floppy-disk"></i>

                <?= $isEdit
                    ? 'บันทึกการแก้ไข'
                    : 'เพิ่มบุคลากร'
                ?>

            </button>

        </div>

    </form>

</section>

<style>
.team-visibility-option {
    margin-top: 4px;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    color: #647891 !important;
    font-size: 9px !important;
    cursor: pointer;
}

.team-visibility-option input {
    width: auto !important;
    height: auto !important;
}

.team-remove-image {
    margin-top: 7px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #a84c55;
    font-size: 9px;
    cursor: pointer;
}
</style>


<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
