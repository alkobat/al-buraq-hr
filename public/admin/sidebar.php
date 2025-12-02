<!-- sidebar.php -->
<style>
.sidebar { height: 100vh; background: #2c3e50; color: white; position: fixed; width: 250px; }
.sidebar a { color: #ecf0f1; padding: 10px 15px; display: block; text-decoration: none; }
.sidebar a:hover, .sidebar a.active { background: #34495e; }
.sidebar h5 { padding: 15px 0; text-align: center; border-bottom: 1px solid #34495e; }
</style>
<nav class="sidebar">
    <h5>المسؤول</h5>
    <a href="dashboard.php" <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-home"></i> لوحة التحكم
    </a>
    <a href="users.php" <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-users"></i> إدارة المستخدمين
    </a>
    <a href="departments.php" <?= basename($_SERVER['PHP_SELF']) === 'departments.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-building"></i> الإدارات
    </a>
    <a href="cycles.php" <?= basename($_SERVER['PHP_SELF']) === 'cycles.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-calendar-alt"></i> دورات التقييم
    </a>
    <a href="fields.php" <?= basename($_SERVER['PHP_SELF']) === 'fields.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-list"></i> مجالات التقييم
    </a>
    <a href="reports.php" <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-chart-bar"></i> التقارير
    </a>
    <a href="settings.php" <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'class="active"' : '' ?>>
        <i class="fas fa-cog"></i> الإعدادات
    </a>
    <a href="../logout.php" style="margin-top: auto;">
        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
    </a>
</nav>