<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

// --- الحذف ---
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: departments.php?msg=deleted');
    exit;
}

// --- التعديل ---
if ($_POST && isset($_POST['edit_dept'])) {
    $id = $_POST['dept_id'];
    $name_ar = trim($_POST['name_ar']);
    $status = $_POST['status'];
    $pdo->prepare("UPDATE departments SET name_ar = ?, status = ? WHERE id = ?")
        ->execute([$name_ar, $status, $id]);
    header('Location: departments.php?msg=edited');
    exit;
}

// --- الإضافة ---
if ($_POST && isset($_POST['add_dept'])) {
    $name_ar = trim($_POST['name_ar']);
    if ($name_ar) {
        $pdo->prepare("INSERT INTO departments (name_ar) VALUES (?)")->execute([$name_ar]);
        header('Location: departments.php?msg=added');
        exit;
    }
}

// --- جلب القسم للتعديل ---
$dept_to_edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $dept_to_edit = $stmt->fetch();
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الإدارات - موظف التقييمات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>موظف التقييمات</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="users.php"><i class="fas fa-users"></i> إدارة المستخدمين</a>
    <a href="departments.php" class="active"><i class="fas fa-building"></i> الإدارات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-building"></i> إدارة الإدارات</h3>
    <hr>

    <!-- خانة البحث -->
    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم...">
        <div id="search-results"></div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success">تمت إضافة الإدارة.</div>
        <?php elseif ($_GET['msg'] === 'edited'): ?>
            <div class="alert alert-info">تم تحديث الإدارة.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-warning">تم حذف الإدارة.</div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-<?= $dept_to_edit ? 'warning' : 'primary' ?> text-white">
            <i class="fas fa-<?= $dept_to_edit ? 'edit' : 'plus' ?>"></i> 
            <?= $dept_to_edit ? 'تعديل إدارة' : 'إضافة إدارة جديدة' ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($dept_to_edit): ?>
                    <input type="hidden" name="dept_id" value="<?= $dept_to_edit['id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label>اسم الإدارة (بالعربية) <span class="text-danger">*</span></label>
                    <input type="text" name="name_ar" class="form-control" value="<?= $dept_to_edit['name_ar'] ?? '' ?>" required>
                </div>
                <?php if ($dept_to_edit): ?>
                <div class="mb-3">
                    <label>الحالة</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $dept_to_edit['status'] == 'active' ? 'selected' : '' ?>>نشطة</option>
                        <option value="inactive" <?= $dept_to_edit['status'] == 'inactive' ? 'selected' : '' ?>>معطلة</option>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" name="<?= $dept_to_edit ? 'edit_dept' : 'add_dept' ?>" class="btn btn-<?= $dept_to_edit ? 'warning' : 'success' ?>">
                    <?= $dept_to_edit ? 'حفظ التعديلات' : 'إضافة' ?>
                </button>
                <?php if ($dept_to_edit): ?>
                    <a href="departments.php" class="btn btn-secondary">إلغاء</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> قائمة الإدارات
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>الاسم</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['name_ar']) ?></td>
                            <td>
                                <span class="badge bg-<?= $d['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= $d['status'] === 'active' ? 'نشطة' : 'معطلة' ?>
                                </span>
                            </td>
                            <td>
                                <a href="?edit=<?= $d['id'] ?>" class="btn btn-sm btn-warning me-1">تعديل</a>
                                <a href="?delete=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف؟')">حذف</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>

<script>
function checkInternet() {
    fetch('https://www.google.com', { method: 'HEAD', mode: 'no-cors' })
        .then(() => {
            document.getElementById('internet-status').innerHTML = '<span class="badge bg-success">متصل</span>';
        })
        .catch(() => {
            document.getElementById('internet-status').innerHTML = '<span class="badge bg-danger">غير متصل</span>';
        });
}
setInterval(checkInternet, 10000);
checkInternet();
</script>

</body>
</html>