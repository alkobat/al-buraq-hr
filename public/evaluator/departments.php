<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header('Location: ../login.php');
    exit;
}

// === CSRF: 1. إنشاء الرمز السري للجلسة و للخروج ===
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];
// =================================================

require_once '../../app/core/db.php';

// --- الحذف (مُعدَّل مع التحقق من الأمان) ---
if (isset($_GET['delete'])) {
    $dept_id = $_GET['delete'];
    
    // 1. التحقق من وجود موظفين مرتبطين
    $stmt_users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
    $stmt_users->execute([$dept_id]);
    $user_count = $stmt_users->fetchColumn();
    
    // 2. التحقق من حالة الإدارة
    $stmt_status = $pdo->prepare("SELECT status FROM departments WHERE id = ?");
    $stmt_status->execute([$dept_id]);
    $status = $stmt_status->fetchColumn();

    if ($user_count > 0) {
        header('Location: departments.php?msg=cannot_delete_linked');
        exit;
    } elseif ($status === 'active') {
        header('Location: departments.php?msg=cannot_delete_active');
        exit;
    } elseif ($status === 'inactive') {
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$dept_id]);
        header('Location: departments.php?msg=deleted');
        exit;
    }
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

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <h3><i class="fas fa-building"></i> إدارة الإدارات</h3>
    <hr>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success">تمت إضافة الإدارة.</div>
        <?php elseif ($_GET['msg'] === 'edited'): ?>
            <div class="alert alert-info">تم تحديث الإدارة.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">تم حذف الإدارة المعطلة بنجاح.</div>
        <?php elseif ($_GET['msg'] === 'cannot_delete_active'): ?>
            <div class="alert alert-danger">لا يمكن حذف إدارة نشطة. يرجى تعطيلها أولاً.</div>
        <?php elseif ($_GET['msg'] === 'cannot_delete_linked'): ?>
            <div class="alert alert-danger">لا يمكن حذف الإدارة لوجود موظفين مرتبطين بها.</div>
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
                                <?php if ($d['status'] === 'inactive'): ?>
                                <a href="?delete=<?= $d['id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذه الإدارة بالكامل؟')">
                                    حذف
                                </a>
                                <?php endif; ?>
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
// وظيفة checkInternet() يتم استدعاؤها من search.js
</script>
</body>
</html>