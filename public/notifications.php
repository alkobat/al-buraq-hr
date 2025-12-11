<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../app/core/db.php';

$user_id = $_SESSION['user_id'];

// جلب الإشعارات غير المقروءة
$unread_notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
$unread_notifications->execute([$user_id]);
$unread_notifications = $unread_notifications->fetchAll();

// جلب جميع الإشعارات (مقروءة وغير مقروءة)
$all_notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$all_notifications->execute([$user_id]);
$all_notifications = $all_notifications->fetchAll();

// علامة "تم قراءة كل الإشعارات"
if (isset($_GET['mark_all_as_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الإشعارات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>لوحة التحكم</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="evaluate.php"><i class="fas fa-star"></i> تقييم الموظفين</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> الإحصائيات</a>
    <a href="notifications.php" class="active"><i class="fas fa-bell"></i> الإشعارات</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-bell"></i> الإشعارات</h3>
    <hr>

    <!-- زر تم قراءة الكل -->
    <?php if (count($unread_notifications) > 0): ?>
    <div class="text-end mb-3">
        <a href="notifications.php?mark_all_as_read=1" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-check-double"></i> تم قراءة الكل
        </a>
    </div>
    <?php endif; ?>

    <!-- عرض الإشعارات غير المقروءة -->
    <?php if (count($unread_notifications) > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-bell me-2"></i> الإشعارات الجديدة (<?= count($unread_notifications) ?>)
        </div>
        <div class="card-body">
            <ul class="list-group">
                <?php foreach ($unread_notifications as $n): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($n['title']) ?></strong>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                        <small class="text-muted"><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></small>
                    </div>
                    <span class="badge bg-<?= $n['type'] ?>"><?= $n['type'] === 'info' ? 'معلومة' : ($n['type'] === 'success' ? 'نجاح' : ($n['type'] === 'warning' ? 'تحذير' : 'خطا')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- عرض جميع الإشعارات -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-history me-2"></i> سجل الإشعارات
        </div>
        <div class="card-body">
            <?php if (empty($all_notifications)): ?>
                <p class="text-muted">لا توجد إشعارات.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($all_notifications as $n): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center <?= $n['is_read'] ? 'bg-light' : '' ?>">
                        <div>
                            <strong><?= htmlspecialchars($n['title']) ?></strong>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></small>
                        </div>
                        <span class="badge bg-<?= $n['type'] ?>"><?= $n['type'] === 'info' ? 'معلومة' : ($n['type'] === 'success' ? 'نجاح' : ($n['type'] === 'warning' ? 'تحذير' : 'خطا')) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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