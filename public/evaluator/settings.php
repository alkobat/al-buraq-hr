<?php
session_start();
// !!! هام: غيّر 'manager' إلى 'supervisor' أو 'evaluator' حسب المجلد !!!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';
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

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <h3><i class="fas fa-cog"></i> إعدادات الحساب</h3>
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center p-5">
                    <h5 class="card-title mb-4"><i class="fas fa-key fa-3x text-warning mb-3"></i><br>الأمان وتسجيل الدخول</h5>
                    <p class="card-text text-muted mb-4">قم بتحديث كلمة المرور الخاصة بك لضمان أمان حسابك.</p>
                    <a href="../change_password.php" class="btn btn-primary btn-lg">
                        تغيير كلمة المرور
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="../assets/js/search.js"></script>
</body>
</html>