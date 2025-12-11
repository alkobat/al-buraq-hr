<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

$employee_id = $_GET['employee_id'] ?? null;

if (!$employee_id) {
    header('Location: users.php');
    exit;
}

// جلب بيانات الموظف
$employee = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$employee->execute([$employee_id]);
$user_data = $employee->fetch();

if (!$user_data) {
    die('الموظف غير موجود');
}

// جلب سجل التقييمات
// ندمج جدول التقييمات مع الدورات ومع جدول الروابط للحصول على رابط العرض
$history_stmt = $pdo->prepare("
    SELECT 
        c.year,
        e.status,
        e.total_score,
        e.evaluator_role,
        ev.name as evaluator_name,
        l.unique_token
    FROM employee_evaluations e
    JOIN evaluation_cycles c ON e.cycle_id = c.id
    LEFT JOIN users ev ON e.evaluator_id = ev.id
    LEFT JOIN employee_evaluation_links l ON l.employee_id = e.employee_id AND l.cycle_id = e.cycle_id
    WHERE e.employee_id = ? AND e.evaluator_role = 'manager' -- نركز على التقييم النهائي المعتمد (من المدير)
    ORDER BY c.year DESC
");
$history_stmt->execute([$employee_id]);
$evaluations = $history_stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>سجل التقييمات - <?= htmlspecialchars($user_data['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php 
$current_page = 'users.php'; // لكي يظل تبويب "المستخدمين" نشطاً
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-history"></i> سجل تقييمات: <?= htmlspecialchars($user_data['name']) ?></h3>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة للمستخدمين
        </a>
    </div>
    <hr>

    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <i class="fas fa-user-circle"></i> بيانات الموظف
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><strong>الاسم:</strong> <?= htmlspecialchars($user_data['name']) ?></div>
                <div class="col-md-4"><strong>الوظيفة:</strong> <?= htmlspecialchars($user_data['job_title'] ?? '—') ?></div>
                <div class="col-md-4"><strong>البريد:</strong> <?= htmlspecialchars($user_data['email']) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list-alt"></i> التقييمات السابقة
        </div>
        <div class="card-body">
            <?php if (empty($evaluations)): ?>
                <div class="alert alert-warning text-center">لا يوجد سجل تقييمات لهذا الموظف حتى الآن.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>السنة</th>
                                <th>المُقيّم (المدير)</th>
                                <th>الدرجة النهائية</th>
                                <th>الحالة</th>
                                <th>عرض التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evaluations as $eval): ?>
                            <tr>
                                <td><strong><?= $eval['year'] ?></strong></td>
                                <td><?= htmlspecialchars($eval['evaluator_name']) ?></td>
                                <td>
                                    <?php if ($eval['total_score'] !== null): ?>
                                        <span class="fw-bold <?= $eval['total_score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                            <?= $eval['total_score'] ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $badge = match($eval['status']) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'submitted' => 'warning',
                                        default => 'secondary'
                                    };
                                    $text = match($eval['status']) {
                                        'approved' => 'معتمد',
                                        'rejected' => 'مرفوض',
                                        'submitted' => 'بانتظار الاعتماد',
                                        default => 'مسودة'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= $text ?></span>
                                </td>
                                <td>
                                    <?php if ($eval['unique_token']): ?>
                                        <a href="../view-ev-report.php?token=<?= $eval['unique_token'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> عرض التقرير
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">غير متوفر</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="../assets/js/search.js"></script>

</body>
</html>