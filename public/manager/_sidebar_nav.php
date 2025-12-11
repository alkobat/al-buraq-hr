<?php 
$nav_path = isset($nav_path) ? $nav_path : ''; 
$logout_path = isset($logout_path) ? $logout_path : '../logout.php';
?>

<nav class="admin-sidebar">
    <h5>مدير الإدارة</h5>
    <a href="<?= $nav_path ?>dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i> لوحة التحكم
    </a>
    <a href="<?= $nav_path ?>evaluate.php" class="<?= $current_page === 'evaluate.php' ? 'active' : '' ?>">
        <i class="fas fa-star"></i> تقييم الموظفين
    </a>
    <a href="<?= $nav_path ?>reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> الإحصائيات
    </a>
    <a href="<?= $nav_path ?>notifications.php" class="<?= $current_page === 'notifications.php' ? 'active' : '' ?>">
        <i class="fas fa-bell"></i> الإشعارات
    </a>
    <a href="<?= $nav_path ?>settings.php" class="<?= $current_page === 'settings.php' ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> الإعدادات
    </a>
    <a href="javascript:void(0);" onclick="document.getElementById('logout-form').submit();" class="logout-link">
        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
    </a>
</nav>

<form id="logout-form" action="<?= $logout_path ?>" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logout_csrf_token) ?>">
</form>