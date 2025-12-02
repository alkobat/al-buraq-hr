<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

$cycles = $pdo->query("SELECT * FROM evaluation_cycles WHERE status = 'active'")->fetchAll();
$selected_cycle = $_GET['cycle'] ?? ($cycles ? $cycles[0]['id'] : null);
$tab = $_GET['tab'] ?? 'numeric'; // numeric أو text

// --- معالجة الحقول الرقمية ---
if ($_POST && isset($_POST['add_numeric_field']) && $selected_cycle) {
    $title_ar = trim($_POST['title_ar']);
    $max_score = (int)$_POST['max_score'];
    if ($title_ar && $max_score > 0) {
        $pdo->prepare("INSERT INTO evaluation_fields (cycle_id, title_ar, max_score) VALUES (?, ?, ?)")
            ->execute([$selected_cycle, $title_ar, $max_score]);
        header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=numeric&msg=numeric_added");
        exit;
    }
}

if (isset($_GET['delete_numeric'])) {
    $pdo->prepare("DELETE FROM evaluation_fields WHERE id = ?")->execute([$_GET['delete_numeric']]);
    header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=numeric&msg=numeric_deleted");
    exit;
}

if ($_POST && isset($_POST['edit_numeric_field'])) {
    $id = $_POST['field_id'];
    $title_ar = trim($_POST['title_ar']);
    $max_score = (int)$_POST['max_score'];
    $pdo->prepare("UPDATE evaluation_fields SET title_ar = ?, max_score = ? WHERE id = ?")
        ->execute([$title_ar, $max_score, $id]);
    header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=numeric&msg=numeric_edited");
    exit;
}

// --- معالجة الحقول النصية ---
if ($_POST && isset($_POST['add_text_field']) && $selected_cycle) {
    $title_ar = trim($_POST['text_title_ar']);
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    if ($title_ar) {
        $pdo->prepare("INSERT INTO evaluation_custom_text_fields (cycle_id, title_ar, is_required) VALUES (?, ?, ?)")
            ->execute([$selected_cycle, $title_ar, $is_required]);
        header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=text&msg=text_added");
        exit;
    }
}

if (isset($_GET['delete_text'])) {
    $pdo->prepare("DELETE FROM evaluation_custom_text_fields WHERE id = ?")->execute([$_GET['delete_text']]);
    header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=text&msg=text_deleted");
    exit;
}

if ($_POST && isset($_POST['edit_text_field'])) {
    $id = $_POST['field_id'];
    $title_ar = trim($_POST['text_title_ar']);
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $pdo->prepare("UPDATE evaluation_custom_text_fields SET title_ar = ?, is_required = ? WHERE id = ?")
        ->execute([$title_ar, $is_required, $id]);
    header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=text&msg=text_edited");
    exit;
}

// جلب البيانات
$numeric_fields = $text_fields = [];
if ($selected_cycle) {
    $nf_stmt = $pdo->prepare("SELECT * FROM evaluation_fields WHERE cycle_id = ? ORDER BY order_index");
    $nf_stmt->execute([$selected_cycle]);
    $numeric_fields = $nf_stmt->fetchAll();
    
    $tf_stmt = $pdo->prepare("SELECT * FROM evaluation_custom_text_fields WHERE cycle_id = ? ORDER BY order_index");
    $tf_stmt->execute([$selected_cycle]);
    $text_fields = $tf_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>مجالات التقييم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>المسؤول</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="users.php"><i class="fas fa-users"></i> إدارة المستخدمين</a>
    <a href="departments.php"><i class="fas fa-building"></i> الإدارات</a>
    <a href="cycles.php"><i class="fas fa-calendar-alt"></i> دورات التقييم</a>
    <a href="evaluation-fields.php" class="active"><i class="fas fa-list"></i> مجالات التقييم</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-list"></i> مجالات التقييم</h3>
    <hr>

    <?php if (empty($cycles)): ?>
        <div class="alert alert-warning">لا توجد دورات تقييم نشطة. يرجى إنشاء دورة أولاً.</div>
    <?php else: ?>
        <!-- اختيار الدورة -->
        <form method="GET" class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <select name="cycle" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($cycles as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_cycle ? 'selected' : '' ?>>
                            <?= $c['year'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <!-- علامات التبويب -->
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'numeric' ? 'active' : '' ?>" 
                   href="?cycle=<?= $selected_cycle ?>&tab=numeric">
                    <i class="fas fa-hashtag"></i> الحقول الرقمية
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'text' ? 'active' : '' ?>" 
                   href="?cycle=<?= $selected_cycle ?>&tab=text">
                    <i class="fas fa-font"></i> الحقول النصية
                </a>
            </li>
        </ul>

        <?php if (isset($_GET['msg'])): ?>
            <?php
            $msg_map = [
                'numeric_added' => 'تمت إضافة المجال الرقمي.',
                'numeric_edited' => 'تم تحديث المجال الرقمي.',
                'numeric_deleted' => 'تم حذف المجال الرقمي.',
                'text_added' => 'تمت إضافة الحقل النصي.',
                'text_edited' => 'تم تحديث الحقل النصي.',
                'text_deleted' => 'تم حذف الحقل النصي.'
            ];
            $msg_key = $_GET['msg'];
            if (isset($msg_map[$msg_key])): ?>
                <div class="alert alert-success"><?= $msg_map[$msg_key] ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'numeric'): ?>
            <!-- الحقول الرقمية -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-plus"></i> إضافة مجال تقييم رقمي
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label>عنوان المجال (بالعربية) <span class="text-danger">*</span></label>
                                <input type="text" name="title_ar" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الدرجة القصوى <span class="text-danger">*</span></label>
                                <input type="number" name="max_score" class="form-control" min="1" max="100" required>
                            </div>
                        </div>
                        <button type="submit" name="add_numeric_field" class="btn btn-success">إضافة المجال</button>
                    </form>
                </div>
            </div>

            <?php if ($numeric_fields): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> المجالات الرقمية الحالية (دورة <?= $cycles[array_search($selected_cycle, array_column($cycles, 'id'))]['year'] ?>)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead><tr><th>المجال</th><th>الدرجة القصوى</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($numeric_fields as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['title_ar']) ?></td>
                                    <td><?= $f['max_score'] ?></td>
                                    <td>
                                        <a href="?cycle=<?= $selected_cycle ?>&tab=numeric&edit_numeric=<?= $f['id'] ?>" class="btn btn-sm btn-warning me-1">تعديل</a>
                                        <a href="?cycle=<?= $selected_cycle ?>&tab=numeric&delete_numeric=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف المجال؟')">حذف</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-success">
                                    <td><strong>المجموع</strong></td>
                                    <td><strong><?= array_sum(array_column($numeric_fields, 'max_score')) ?>/100</strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'text'): ?>
            <!-- الحقول النصية -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-plus"></i> إضافة حقل نصي جديد
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>عنوان الحقل (بالعربية) <span class="text-danger">*</span></label>
                            <input type="text" name="text_title_ar" class="form-control" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_required" class="form-check-input" id="is_required">
                            <label class="form-check-label" for="is_required">هل الحقل إلزامي؟</label>
                        </div>
                        <button type="submit" name="add_text_field" class="btn btn-success">إضافة الحقل</button>
                    </form>
                </div>
            </div>

            <?php if ($text_fields): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> الحقول النصية الحالية (دورة <?= $cycles[array_search($selected_cycle, array_column($cycles, 'id'))]['year'] ?>)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead><tr><th>العنوان</th><th>إلزامي</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($text_fields as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['title_ar']) ?></td>
                                    <td>
                                        <?php if ($f['is_required']): ?>
                                            <span class="badge bg-success">نعم</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">لا</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?cycle=<?= $selected_cycle ?>&tab=text&edit_text=<?= $f['id'] ?>" class="btn btn-sm btn-warning me-1">تعديل</a>
                                        <a href="?cycle=<?= $selected_cycle ?>&tab=text&delete_text=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف الحقل؟')">حذف</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
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