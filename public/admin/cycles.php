<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

if ($_POST && isset($_POST['add_cycle'])) {
    $year = $_POST['year'];
    if ($year >= 2020 && $year <= 2030) {
        $pdo->prepare("INSERT INTO evaluation_cycles (year) VALUES (?) ON DUPLICATE KEY UPDATE status = 'active'")->execute([$year]);
        header('Location: cycles.php?msg=added');
        exit;
    }
}

if (isset($_GET['toggle'])) {
    $status = $_GET['status'] === 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE evaluation_cycles SET status = ? WHERE id = ?")->execute([$status, $_GET['toggle']]);
    header('Location: cycles.php');
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

<nav class="admin-sidebar">
    <h5>المسؤول</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="users.php"><i class="fas fa-users"></i> إدارة المستخدمين</a>
    <a href="departments.php"><i class="fas fa-building"></i> الإدارات</a>
    <a href="cycles.php" class="active"><i class="fas fa-calendar-alt"></i> دورات التقييم</a>
    <a href="evaluation-fields.php"><i class="fas fa-list"></i> مجالات التقييم</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-calendar-alt"></i> دورات التقييم</h3>
    <hr>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
        <div class="alert alert-success">تم إنشاء دورة التقييم.</div>
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
                                <span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= $c['status'] === 'active' ? 'نشطة' : 'معطلة' ?>
                                </span>
                            </td>
                            <td>
                                <a href="?toggle=<?= $c['id'] ?>&status=<?= $c['status'] ?>" class="btn btn-sm btn-<?= $c['status'] === 'active' ? 'warning' : 'success' ?>">
                                    <?= $c['status'] === 'active' ? 'إيقاف' : 'تفعيل' ?>
                                </a>
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