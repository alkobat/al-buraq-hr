<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

// جلب الإحصائيات
$totalManagers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$totalSupervisors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();
// الآن يشمل الموظفين العاديين وموظفي التقييمات
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('employee', 'evaluator')")->fetchColumn();
$departmentsCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>لوحة موظف التقييمات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
    <style>
        .sidebar { background: #6f42c1; }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.3em 0.6em;
        }
    </style>
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>موظف التقييمات</h5>
    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="users.php"><i class="fas fa-users"></i> إدارة المستخدمين</a>
    <a href="departments.php"><i class="fas fa-building"></i> الإدارات</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-tasks"></i> لوحة موظف التقييمات</h3>
    <hr>

    <!-- خانة البحث -->
    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم...">
        <div id="search-results"></div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-user-tie card-icon"></i></h5>
                    <h4><?= $totalManagers ?></h4>
                    <p class="mb-0">مدراء الإدارات</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-user-check card-icon"></i></h5>
                    <h4><?= $totalSupervisors ?></h4>
                    <p class="mb-0">الرؤساء المباشرون</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-warning h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-users card-icon"></i></h5>
                    <h4><?= $totalEmployees ?></h4>
                    <p class="mb-0">الموظفون (بما فيهم موظفو التقييمات)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-info h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-building card-icon"></i></h5>
                    <h4><?= $departmentsCount ?></h4>
                    <p class="mb-0">عدد الإدارات</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> مهامك الرئيسية
        </div>
        <div class="card-body">
            <ul>
                <li>إضافة وتعديل المدراء والرؤساء المباشرين والموظفين</li>
                <li>إدارة الإدارات (إضافة/تعديل/حذف)</li>
                <li>التأكد من اكتمال بيانات المستخدمين قبل بدء التقييم</li>
            </ul>
            <a href="users.php" class="btn btn-primary mt-3">
                <i class="fas fa-users"></i> إدارة المستخدمين
            </a>
            <a href="departments.php" class="btn btn-info mt-3 me-2">
                <i class="fas fa-building"></i> إدارة الإدارات
            </a>
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