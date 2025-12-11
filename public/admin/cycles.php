<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];
// =============================

require_once '../../app/core/db.php';

// إضافة دورة جديدة
if ($_POST && isset($_POST['add_cycle'])) {
    $year = $_POST['year'];
    if ($year >= 2020 && $year <= 2030) {
        $pdo->prepare("INSERT INTO evaluation_cycles (year) VALUES (?) ON DUPLICATE KEY UPDATE status = 'active'")->execute([$year]);
        header('Location: cycles.php?msg=added');
        exit;
    }
}

// تبديل الحالة (تفعيل/إيقاف)
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $current_status = $_GET['status'];
    
    if ($current_status === 'archived') {
        header('Location: cycles.php?msg=cannot_change_archived');
        exit;
    }

    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    // (تم إزالة الحماية التي كانت تمنع الإيقاف في حال وجود تقييمات معلقة)
    // للسماح للمسؤول بإيقاف الدورة ثم أرشفتها.

    $pdo->prepare("UPDATE evaluation_cycles SET status = ? WHERE id = ?")->execute([$new_status, $id]);
    header('Location: cycles.php');
    exit;
}

// أرشفة الدورة
if (isset($_GET['archive'])) {
    $id = $_GET['archive'];
    
    // 1. الموافقة التلقائية على جميع التقييمات المعلقة
    $pdo->prepare("UPDATE employee_evaluations SET status = 'approved', accepted_at = NOW() WHERE cycle_id = ? AND status = 'submitted'")->execute([$id]);
    
    // 2. تغيير حالة الدورة إلى 'archived'
    $pdo->prepare("UPDATE evaluation_cycles SET status = 'archived' WHERE id = ?")->execute([$id]);
    
    header('Location: cycles.php?msg=archived');
    exit;
}

// حذف الدورة
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $stmt_status = $pdo->prepare("SELECT status FROM evaluation_cycles WHERE id = ?");
    $stmt_status->execute([$id]);
    $cycle_status = $stmt_status->fetchColumn();

    if ($cycle_status === 'inactive') {
        $pdo->prepare("DELETE FROM evaluation_cycles WHERE id = ?")->execute([$id]);
        header('Location: cycles.php?msg=deleted');
    } else {
        header('Location: cycles.php?msg=cannot_delete_active');
    }
    exit;
}

$cycles = $pdo->query("SELECT * FROM evaluation_cycles ORDER BY year DESC")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>دورات التقييم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php 
// استدعاء الشريط الجانبي الموحد
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <h3><i class="fas fa-calendar-alt"></i> دورات التقييم</h3>
    <hr>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success">تم إنشاء دورة التقييم.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-warning">تم حذف الدورة بنجاح.</div>
        <?php elseif ($_GET['msg'] === 'archived'): ?>
            <div class="alert alert-info">تم أرشفة الدورة واعتماد جميع التقييمات المعلقة فيها.</div>
        <?php elseif ($_GET['msg'] === 'cannot_delete_active'): ?>
            <div class="alert alert-danger">لا يمكن حذف دورة نشطة أو مؤرشفة. يرجى إيقافها أولاً.</div>
        <?php elseif ($_GET['msg'] === 'cannot_change_archived'): ?>
            <div class="alert alert-danger">لا يمكن تغيير حالة دورة مؤرشفة.</div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-plus"></i> إنشاء دورة تقييم جديدة
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>السنة <span class="text-danger">*</span></label>
                    <select name="year" class="form-control" required>
                        <?php for ($y = date('Y'); $y <= date('Y') + 5; $y++): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" name="add_cycle" class="btn btn-success">إنشاء الدورة</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> الدورات الحالية
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>السنة</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                    <tbody>
                        <?php foreach ($cycles as $c): ?>
                        <tr>
                            <td><?= $c['year'] ?></td>
                            <td>
                                <?php if ($c['status'] === 'active'): ?>
                                    <span class="badge bg-success">نشطة</span>
                                <?php elseif ($c['status'] === 'inactive'): ?>
                                    <span class="badge bg-secondary">معطلة</span>
                                <?php else: ?>
                                    <span class="badge bg-dark"><i class="fas fa-archive"></i> مؤرشفة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['status'] !== 'archived'): ?>
                                    <a href="?toggle=<?= $c['id'] ?>&status=<?= $c['status'] ?>" class="btn btn-sm btn-<?= $c['status'] === 'active' ? 'warning' : 'success' ?> me-1">
                                        <?= $c['status'] === 'active' ? 'إيقاف' : 'تفعيل' ?>
                                    </a>
                                    
                                    <a href="?archive=<?= $c['id'] ?>" 
                                       class="btn btn-sm btn-info text-white me-1" 
                                       onclick="return confirm('هل أنت متأكد من الأرشفة؟ سيتم اعتماد جميع التقييمات المعلقة (موافق) ولن يمكن تعديل الدورة لاحقاً.')">
                                        أرشفة
                                    </a>

                                    <?php if ($c['status'] === 'inactive'): ?>
                                        <a href="?delete=<?= $c['id'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('هل أنت متأكد من حذف هذه الدورة بالكامل؟')">
                                            حذف
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">للقراءة فقط</span>
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
<script src="../assets/js/search.js"></script>
</body>
</html>