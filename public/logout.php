<?php
session_start();

// (جديد) استدعاء ملفات النظام الضرورية للتسجيل
require_once '../app/core/db.php';
require_once '../app/core/Logger.php';

// التحقق من أن الطلب من نوع POST ويحتوي على رمز CSRF صالح
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['csrf_token']) && 
    isset($_SESSION['logout_csrf_token']) &&
    hash_equals($_POST['csrf_token'], $_SESSION['logout_csrf_token'])) 
{
    // === (جديد) تسجيل عملية الخروج ===
    if (isset($_SESSION['user_id'])) {
        $logger = new Logger($pdo);
        $logger->log('logout', 'قام بتسجيل الخروج من النظام', $_SESSION['user_id']);
    }
    // =================================

    // الرمز صالح، تابع عملية الخروج
    unset($_SESSION['logout_csrf_token']);
    session_destroy();
    header('Location: login.php?msg=logged_out');
    exit;
} else {
    // هجوم CSRF محتمل أو طلب غير صالح (GET)
    unset($_SESSION['logout_csrf_token']);
    header('Location: login.php?error=security_breach'); 
    exit;
}
?>