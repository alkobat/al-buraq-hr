<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];

// التوجيه حسب الدور
switch($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'manager':
        header('Location: manager/dashboard.php');
        break;
    case 'supervisor':
        header('Location: supervisor/dashboard.php');
        break;
    case 'evaluator':
        header('Location: evaluator/dashboard.php'); // ← المسار الصحيح الآن
        break;
    default:
        session_destroy();
        header('Location: login.php');
}
exit;
?>