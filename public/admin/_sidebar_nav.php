<?php 
// تعيين قيم افتراضية إذا لم يتم تعريفها (للتوافق مع الاستدعاء من داخل المجلد)
$nav_path = isset($nav_path) ? $nav_path : ''; 
$logout_path = isset($logout_path) ? $logout_path : '../logout.php';
?>

<nav class="admin-sidebar">
    <h5>المسؤول</h5>
    <a href="<?= $nav_path ?>dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i> لوحة التحكم
    </a>
    <a href="<?= $nav_path ?>users.php" class="<?= $current_page === 'users.php' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> إدارة المستخدمين
    </a>
    <a href="<?= $nav_path ?>departments.php" class="<?= $current_page === 'departments.php' ? 'active' : '' ?>">
        <i class="fas fa-building"></i> الإدارات
    </a>
    <a href="<?= $nav_path ?>cycles.php" class="<?= $current_page === 'cycles.php' ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> دورات التقييم
    </a>
    <a href="<?= $nav_path ?>evaluation-fields.php" class="<?= $current_page === 'evaluation-fields.php' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> مجالات التقييم
    </a>
    <a href="<?= $nav_path ?>reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> التقارير والإحصائيات
    </a>
	    <a href="<?= $nav_path ?>bulk_email.php" class="<?= $current_page === 'bulk_email.php' ? 'active' : '' ?>">
        <i class="fas fa-mail-bulk"></i> الإرسال الجماعي
    </a>
	<a href="<?= $nav_path ?>reminders.php" class="<?= $current_page === 'reminders.php' ? 'active' : '' ?>">
        <i class="fas fa-bullhorn"></i> نظام التذكير
    </a>
    <a href="<?= $nav_path ?>settings.php" class="<?= $current_page === 'settings.php' ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> الإعدادات
    </a>
    <a href="<?= $nav_path ?>notifications.php" class="<?= $current_page === 'notifications.php' ? 'active' : '' ?>">
        <i class="fas fa-bell"></i> الإشعارات
    </a>
    <a href="<?= $nav_path ?>activity_logs.php" class="<?= $current_page === 'activity_logs.php' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> سجل النشاطات
    </a>
	<a href="<?= $nav_path ?>org_chart.php" class="<?= $current_page === 'org_chart.php' ? 'active' : '' ?>">
        <i class="fas fa-sitemap"></i> الهيكل التنظيمي
    </a>
	
    <a href="javascript:void(0);" onclick="document.getElementById('logout-form').submit();" class="logout-link">
        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
    </a>
</nav>

<form id="logout-form" action="<?= $logout_path ?>" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logout_csrf_token) ?>">
</form>