<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

$manager_id = $_SESSION['user_id'];
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();

$employees = [];
if ($active_cycle) {
    $employees_stmt = $pdo->prepare("
        SELECT u.*, d.name_ar as dept_name,
               (SELECT status FROM employee_evaluations WHERE employee_id = u.id AND evaluator_role = 'supervisor' AND cycle_id = ?) as supervisor_status,
               (SELECT status FROM employee_evaluations WHERE employee_id = u.id AND evaluator_role = 'manager' AND cycle_id = ?) as manager_status
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.manager_id = ? AND u.role IN ('employee', 'evaluator')
        ORDER BY u.name
    ");
    $employees_stmt->execute([$active_cycle['id'], $active_cycle['id'], $manager_id]);
    $employees = $employees_stmt->fetchAll();
}

// حساب الإحصائيات
$approved_count = $rejected_count = $pending_count = $not_evaluated_count = 0;
if ($active_cycle) {
    foreach ($employees as $e) {
        $supervisor_status = $e['supervisor_status'];
        $manager_status = $e['manager_status'];
        
        if ($supervisor_status === 'approved' && $manager_status === 'approved') {
            $approved_count++;
        } elseif ($supervisor_status === 'rejected' || $manager_status === 'rejected') {
            $rejected_count++;
        } elseif ($supervisor_status === 'submitted' || $manager_status === 'submitted') {
            $pending_count++;
        } else {
            $not_evaluated_count++;
        }
    }
}

// جلب أداء الإدارات
$dept_performance = [];
if ($active_cycle) {
    $stmt_dept = $pdo->prepare("
        SELECT d.name_ar, AVG(e.total_score) as avg_score
        FROM users u
        JOIN departments d ON u.department_id = d.id
        JOIN employee_evaluations e ON u.id = e.employee_id
        WHERE e.cycle_id = ? AND e.status = 'approved'
        GROUP BY d.id
        ORDER BY avg_score DESC
    ");
    $stmt_dept->execute([$active_cycle['id']]);
    $dept_performance = $stmt_dept->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>لوحة مدير الإدارة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>مدير الإدارة</h5>
    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="evaluate.php"><i class="fas fa-star"></i> تقييم الموظفين</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> الإحصائيات</a>
    <a href="notifications.php"><i class="fas fa-bell"></i> الإشعارات</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-building"></i> لوحة مدير الإدارة</h3>
    <hr>

    <!-- خانة البحث -->
    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم...">
        <div id="search-results"></div>
    </div>

    <?php if (!$active_cycle): ?>
        <div class="alert alert-warning">لا توجد دورة تقييم نشطة حاليًا.</div>
    <?php else: ?>
        <!-- البطاقات الإحصائية -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-primary h-100">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-users card-icon"></i></h5>
                        <h4><?= count($employees) ?></h4>
                        <p class="mb-0">إجمالي موظفي الإدارة</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-success h-100">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-check-circle card-icon"></i></h5>
                        <h4><?= $approved_count ?></h4>
                        <p class="mb-0">تم تقييمهم</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-warning h-100">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-edit card-icon"></i></h5>
                        <h4><?= $pending_count ?></h4>
                        <p class="mb-0">في طور التقييم</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-secondary h-100">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-times-circle card-icon"></i></h5>
                        <h4><?= $not_evaluated_count ?></h4>
                        <p class="mb-0">لم يتم تقييمهم</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- رسوم بيانية -->
        <div class="row mb-4">
            <!-- رسم بياني دائري: حالة التقييمات -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> حالة التقييمات
                    </div>
                    <div class="card-body">
                        <canvas id="evaluationStatusChart" width="400" height="400"></canvas>
                    </div>
                </div>
            </div>

            <!-- رسم بياني شريطي: أداء الإدارات -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i> أداء الإدارات
                    </div>
                    <div class="card-body">
                        <canvas id="departmentsPerformanceChart" width="400" height="400"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول الموظفين -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users"></i> موظفو إدارتك (دورة <?= $active_cycle['year'] ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>الوظيفة</th>
                                <th>الدور</th>
                                <th>الإدارة</th>
                                <th>تقييم الرئيس المباشر</th>
                                <th>تقييمك</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr><td colspan="7" class="text-center">لا توجد موظفين في إدارتك.</td></tr>
                            <?php else: ?>
                                <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['name']) ?></td>
                                    <td><?= htmlspecialchars($e['job_title'] ?? '—') ?></td>
                                    <td>
                                        <?= $e['role'] === 'evaluator' ? 'موظف تقييمات' : 'موظف' ?>
                                    </td>
                                    <td><?= $e['dept_name'] ?? '—' ?></td>
                                    <td>
                                        <?php if ($e['supervisor_status']): ?>
                                            <span class="status-badge bg-<?= $e['supervisor_status'] === 'submitted' ? 'info' : ($e['supervisor_status'] === 'approved' ? 'success' : 'danger') ?>">
                                                <?= $e['supervisor_status'] === 'submitted' ? 'بانتظار' : ($e['supervisor_status'] === 'approved' ? 'موافق' : 'مرفوض') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge bg-secondary">لم يُقيّم</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($e['manager_status']): ?>
                                            <span class="status-badge bg-<?= $e['manager_status'] === 'submitted' ? 'info' : ($e['manager_status'] === 'approved' ? 'success' : 'danger') ?>">
                                                <?= $e['manager_status'] === 'submitted' ? 'بانتظار' : ($e['manager_status'] === 'approved' ? 'موافق' : 'مرفوض') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge bg-secondary">لم يُقيّم</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$e['manager_status']): ?>
                                            <a href="evaluate.php?employee=<?= $e['id'] ?>" class="btn btn-sm btn-primary">تقييم</a>
                                        <?php else: ?>
                                            <a href="evaluate.php?employee=<?= $e['id'] ?>" class="btn btn-sm btn-info">عرض/تعديل</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

<?php if ($active_cycle): ?>
<script>
// رسم بياني دائري: حالة التقييمات
const evaluationStatusCtx = document.getElementById('evaluationStatusChart').getContext('2d');
const evaluationStatusChart = new Chart(evaluationStatusCtx, {
    type: 'pie',
    data: {
        labels: ['موافق', 'مرفوض', 'بانتظار', 'لم يُقيّم'],
        datasets: [{
            data: [<?= $approved_count ?>, <?= $rejected_count ?>, <?= $pending_count ?>, <?= $not_evaluated_count ?>],
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)', // أخضر
                'rgba(220, 53, 69, 0.8)', // أحمر
                'rgba(255, 193, 7, 0.8)', // أصفر
                'rgba(108, 117, 125, 0.8)' // رمادي
            ],
            borderColor: [
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)',
                'rgba(255, 193, 7, 1)',
                'rgba(108, 117, 125, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed;
                    }
                }
            }
        }
    }
});

// رسم بياني شريطي: أداء الإدارات
const deptCtx = document.getElementById('departmentsPerformanceChart').getContext('2d');
const deptLabels = [<?php foreach ($dept_performance as $dept) { echo '"' . addslashes($dept['name_ar']) . '",'; } ?>];
const deptScores = [<?php foreach ($dept_performance as $dept) { echo $dept['avg_score'] . ','; } ?>];

const departmentsPerformanceChart = new Chart(deptCtx, {
    type: 'bar',
    data: {
        labels: deptLabels,
        datasets: [{
            label: 'المتوسط',
            data: deptScores,
            backgroundColor: 'rgba(0, 123, 255, 0.8)',
            borderColor: 'rgba(0, 123, 255, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>