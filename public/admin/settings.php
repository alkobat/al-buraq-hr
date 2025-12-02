<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

if ($_POST && isset($_POST['save_settings'])) {
    $primary = $_POST['primary_color'];
    $secondary = $_POST['secondary_color'];
    
    $pdo->prepare("UPDATE system_settings SET value = ? WHERE `key` = 'primary_color'")->execute([$primary]);
    $pdo->prepare("UPDATE system_settings SET value = ? WHERE `key` = 'secondary_color'")->execute([$secondary]);
    
    if (!empty($_FILES['logo']['name'])) {
        $upload_dir = '../../storage/uploads/';
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'logo.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) {
            $pdo->prepare("UPDATE system_settings SET value = ? WHERE `key` = 'logo_path'")->execute([$filename]);
        }
    }
    
    header('Location: settings.php?msg=saved');
    exit;
}

$settings = [];
$stmt = $pdo->query("SELECT `key`, `value` FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الإعدادات</title>
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
    <a href="evaluation-fields.php"><i class="fas fa-list"></i> مجالات التقييم</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
    <a href="settings.php" class="active"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-cog"></i> الإعدادات العامة</h3>
    <hr>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert alert-success">تم حفظ الإعدادات.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>الشعار</label><br>
                    <?php if (!empty($settings['logo_path'])): ?>
                        <img src="../storage/uploads/<?= htmlspecialchars($settings['logo_path']) ?>" height="50" class="mb-2">
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/*" class="form-control">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>اللون الأساسي</label>
                        <input type="color" name="primary_color" class="form-control form-control-color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>" title="اختر اللون">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>اللون الثانوي</label>
                        <input type="color" name="secondary_color" class="form-control form-control-color" value="<?= htmlspecialchars($settings['secondary_color'] ?? '#6c757d') ?>" title="اختر اللون">
                    </div>
                </div>
                <button type="submit" name="save_settings" class="btn btn-primary">حفظ الإعدادات</button>
            </form>
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