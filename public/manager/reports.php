<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

$manager_id = $_SESSION['user_id'];
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();

// جلب تقييمات موظفيه مع اسم المُقيّم
$reports = [];
if ($active_cycle) {
    $reports = $pdo->prepare("
        SELECT 
            u.id as employee_id,
            u.name as employee_name,
            u.email,
            d.name_ar as dept_name,
            e.total_score,
            e.status as evaluation_status,
            e.created_at,
            e.updated_at,
            ev.name as evaluator_name,
            e.evaluator_role
        FROM employee_evaluations e
        JOIN users u ON e.employee_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        JOIN users ev ON e.evaluator_id = ev.id
        WHERE u.manager_id = ? 
        AND e.cycle_id = ?
        AND e.evaluator_role IN ('supervisor', 'manager')
        ORDER BY u.name, e.evaluator_role
    ");
    $reports->execute([$manager_id, $active_cycle['id']]);
    $reports = $reports->fetchAll();
}

// تنظيم البيانات حسب الموظف
$employee_reports = [];
foreach ($reports as $r) {
    $employee_reports[$r['employee_name']][] = $r;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>التقارير - مدير الإدارة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>مدير الإدارة</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="evaluate.php"><i class="fas fa-star"></i> تقييم الموظفين</a>
    <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> الإحصائيات</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h3>
	<!-- زر تصدير إلى Excel -->
<div class="d-flex justify-content-between mb-3">
    <h3><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h3>
    <a href="../export-reports.php" class="btn btn-outline-success">
        <i class="fas fa-file-excel"></i> تصدير إلى Excel
    </a>
</div>
    <hr>

    <!-- خانة البحث -->
    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم...">
        <div id="search-results"></div>
    </div>

    <?php if (!$active_cycle): ?>
        <div class="alert alert-warning">لا توجد دورة تقييم نشطة حاليًا.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list"></i> تقييمات موظفيك (دورة <?= $active_cycle['year'] ?>)</span>
            </div>
            <div class="card-body">
                <?php if (empty($employee_reports)): ?>
                    <div class="text-center text-muted">لا توجد تقييمات حتى الآن.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>البريد</th>
                                    <th>الإدارة</th>
                                    <th>المُقيّم</th>
                                    <th>الدرجة</th>
                                    <th>الحالة</th>
                                    <th>تاريخ التحديث</th>
                                    <th>رابط التقييم</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employee_reports as $emp_name => $evals): ?>
                                    <?php 
                                    // جلب أول تقييم للحصول على معرف الموظف
                                    $first_eval = $evals[0];
                                    $employee_id = $first_eval['employee_id'];
                                    
                                    // جلب الـ token من قاعدة البيانات
                                    $token_stmt = $pdo->prepare("
                                        SELECT unique_token FROM employee_evaluation_links 
                                        WHERE employee_id = ? AND cycle_id = ?
                                    ");
                                    $token_stmt->execute([$employee_id, $active_cycle['id']]);
                                    $token = $token_stmt->fetchColumn();
                                    ?>
                                    <?php foreach ($evals as $index => $e): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                        <td rowspan="<?= count($evals) ?>"><?= htmlspecialchars($emp_name) ?></td>
                                        <td rowspan="<?= count($evals) ?>"><?= htmlspecialchars($e['email']) ?></td>
                                        <td rowspan="<?= count($evals) ?>"><?= $e['dept_name'] ?? '—' ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?= htmlspecialchars($e['evaluator_name']) ?>
                                            (<?= $e['evaluator_role'] === 'manager' ? 'مدير إدارة' : 'رئيس مباشر' ?>)
                                        </td>
                                        <td><?= $e['total_score'] ?? '—' ?></td>
                                        <td>
                                            <?php
                                            $status = $e['evaluation_status'];
                                            $badge_class = 'secondary';
                                            $status_text = '—';
                                            if ($status === 'draft') {
                                                $badge_class = 'warning';
                                                $status_text = 'مسودة';
                                            } elseif ($status === 'submitted') {
                                                $badge_class = 'info';
                                                $status_text = 'بانتظار';
                                            } elseif ($status === 'approved') {
                                                $badge_class = 'success';
                                                $status_text = 'موافق';
                                            } elseif ($status === 'rejected') {
                                                $badge_class = 'danger';
                                                $status_text = 'مرفوض';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $badge_class ?>"><?= $status_text ?></span>
                                        </td>
                                        <td><?= $e['updated_at'] ? date('Y-m-d H:i', strtotime($e['updated_at'])) : '—' ?></td>
                                        <?php if ($index === 0): ?>
                                        <td rowspan="<?= count($evals) ?>">
                                            <?php if ($token): ?>
                                                <a href="../view-evaluation.php?token=<?= $token ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-link"></i> عرض
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-spinner fa-spin"></i> يُنشَأ...
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ملخص الإحصائيات -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-users"></i></h5>
                        <h4><?= count($employee_reports) ?></h4>
                        <p class="mb-0">عدد الموظفين</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-check-circle"></i></h5>
                        <?php
                        $approved = array_filter($reports, fn($r) => $r['evaluation_status'] === 'approved');
                        ?>
                        <h4><?= count($approved) ?></h4>
                        <p class="mb-0">موافقات</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-clock"></i></h5>
                        <?php
                        $pending = array_filter($reports, fn($r) => $r['evaluation_status'] === 'submitted');
                        ?>
                        <h4><?= count($pending) ?></h4>
                        <p class="mb-0">بانتظار الموافقة</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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