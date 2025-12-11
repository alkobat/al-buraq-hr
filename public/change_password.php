<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once '../app/core/db.php';

// 1. توليد رموز CSRF للخروج وللنموذج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

$error = '';
$is_first_login = isset($_GET['first']);
$role = $_SESSION['role'];

// 2. تحديد المسار الصحيح لشريط التنقل الموحد ومتغيرات الروابط
$sidebar_file = '';
$role_folder = '';
$nav_path = '';    // بادئة لروابط القائمة (مثل "admin/")
$logout_path = 'logout.php'; // مسار الخروج الصحيح من هذا الملف

switch ($role) {
    case 'admin':
    case 'manager':
    case 'supervisor':
    case 'evaluator':
        $role_folder = $role;
        $sidebar_file = $role_folder . '/_sidebar_nav.php';
        $nav_path = $role_folder . '/'; // إضافة اسم المجلد للروابط
        break;
    default:
        $sidebar_file = ''; 
        break;
}

if ($_POST) {
    $new_pass = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if ($new_pass !== $confirm) {
        $error = "كلمتا المرور غير متطابقتين.";
    } elseif (strlen($new_pass) < 6) {
        $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?")
            ->execute([$hashed, $_SESSION['user_id']]);
        $_SESSION['force_change'] = 0;
        
        // التوجيه إلى index.php ليتم توجيهه حسب دوره الجديد
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تغيير كلمة المرور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css"> 
    <style>
        .admin-main-content {
            margin-right: <?= $sidebar_file ? '250px' : '0' ?>; 
            width: <?= $sidebar_file ? 'calc(100% - 250px)' : '100%' ?>;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .admin-main-content {
                margin-right: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="<?= $sidebar_file ? 'admin-dashboard' : '' ?>">

<?php 
// 3. استدعاء شريط التنقل الموحد
if ($sidebar_file && file_exists($sidebar_file)) {
    $current_page = basename(__FILE__);
    // المتغيرات $nav_path و $logout_path ستستخدم داخل ملف الشريط الجانبي
    require_once $sidebar_file;
}
?>

<main class="admin-main-content">
    <div class="row justify-content-center w-100">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-key"></i> تغيير كلمة المرور
                </div>
                <?php if ($is_first_login): ?>
                    <div class="alert alert-warning text-center m-3">
                        <strong>تنبيه أمني:</strong> يرجى تغيير كلمة المرور الافتراضية للمتابعة.
                    </div>
                <?php endif; ?>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label>تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">تحديث كلمة المرور</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="internet-status" style="display: <?= $sidebar_file ? 'block' : 'none' ?>;">
    <span class="badge bg-success">متصل</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($sidebar_file): ?>
<script src="assets/js/search.js" defer></script>
<?php endif; ?>

</body>
</html>