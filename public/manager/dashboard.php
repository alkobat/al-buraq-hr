<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit;
}

// ====================================================
// (هام جداً) هذا الجزء هو المسؤول عن إصلاح تسجيل الخروج
// ====================================================
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];
// ====================================================

require_once '../../app/core/db.php';

$manager_id = $_SESSION['user_id'];

// 1. الإشعارات
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$manager_id]);
$unread_count = $unread_stmt->fetchColumn();

$latest_notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$latest_notifs->execute([$manager_id]);
$modal_notifications = $latest_notifs->fetchAll();

// 2. الدورة الحالية
$current_cycle = $pdo->query("SELECT * FROM evaluation_cycles WHERE status = 'active' ORDER BY year DESC LIMIT 1")->fetch();
$cycle_id = $current_cycle ? $current_cycle['id'] : null;
$cycle_year = $current_cycle ? $current_cycle['year'] : date('Y');

// 3. الإحصائيات
$my_employees_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE manager_id = ? AND status='active'");
$my_employees_count->execute([$manager_id]);
$total_staff = $my_employees_count->fetchColumn();

$completed_evals = 0;
$pending_evals = 0;
$avg_score = 0;
$completion_rate = 0;
$required_action_count = 0; // للمربع الجديد

if ($cycle_id && $total_staff > 0) {
    // التقييمات المكتملة
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN e.status IN ('submitted', 'approved') THEN 1 END) as completed,
            AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as average
        FROM users u
        LEFT JOIN employee_evaluations e ON u.id = e.employee_id AND e.cycle_id = ? AND e.evaluator_role = 'manager'
        WHERE u.manager_id = ? AND u.status='active'
    ");
    $stats_stmt->execute([$cycle_id, $manager_id]);
    $stats = $stats_stmt->fetch();

    $completed_evals = $stats['completed'] ?? 0;
    $pending_evals = $total_staff - $completed_evals; 
    $avg_score = round($stats['average'] ?? 0, 1);
    $completion_rate = round(($completed_evals / $total_staff) * 100, 1);

    // حساب عدد الموظفين الذين يحتاجون لتقييم (العدد الدقيق للمربع)
    $action_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users u
        LEFT JOIN employee_evaluations e ON u.id = e.employee_id AND e.cycle_id = ? AND e.evaluator_role = 'manager'
        WHERE u.manager_id = ? 
          AND u.status = 'active'
          AND (e.status IS NULL OR e.status IN ('draft', 'rejected'))
    ");
    $action_stmt->execute([$cycle_id, $manager_id]);
    $required_action_count = $action_stmt->fetchColumn();
}

// 4. الرسوم البيانية
$grade_dist = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
if ($cycle_id) {
    $grades_stmt = $pdo->prepare("
        SELECT total_score FROM employee_evaluations e
        JOIN users u ON e.employee_id = u.id
        WHERE u.manager_id = ? AND e.cycle_id = ? AND e.status = 'approved'
    ");
    $grades_stmt->execute([$manager_id, $cycle_id]);
    while ($row = $grades_stmt->fetch()) {
        $s = $row['total_score'];
        if ($s >= 90) $grade_dist['A']++;
        elseif ($s >= 80) $grade_dist['B']++;
        elseif ($s >= 70) $grade_dist['C']++;
        else $grade_dist['D']++;
    }
}

// 5. آخر النشاطات
$recent_activity = [];
if ($cycle_id) {
    $activity_stmt = $pdo->prepare("
        SELECT u.name, e.updated_at, e.status, e.total_score
        FROM employee_evaluations e
        JOIN users u ON e.employee_id = u.id
        WHERE u.manager_id = ? AND e.cycle_id = ? AND e.evaluator_role = 'manager'
        ORDER BY e.updated_at DESC LIMIT 5
    ");
    $activity_stmt->execute([$manager_id, $cycle_id]);
    $recent_activity = $activity_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>لوحة المدير</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { transition: transform 0.2s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-icon { position: absolute; left: 15px; top: 20px; opacity: 0.2; font-size: 3.5rem; transform: rotate(-15deg); }
        .btn-pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        .notification-item.unread { background-color: #e8f4ff; border-left: 4px solid #0d6efd; }
    </style>
</head>
<body class="admin-dashboard">

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="fas fa-home"></i> لوحة إدارة القسم</h3>
            <span class="text-muted">أهلاً بك، <strong><?= htmlspecialchars($_SESSION['name']) ?></strong></span>
        </div>
        
        <div class="d-flex gap-2">
            <button type="button" class="btn <?= $unread_count > 0 ? 'btn-warning text-dark btn-pulse' : 'btn-secondary' ?> position-relative" data-bs-toggle="modal" data-bs-target="#notifModal">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $unread_count ?>
                    </span>
                <?php endif; ?>
            </button>
            <span class="badge bg-<?= $cycle_id ? 'success' : 'secondary' ?> p-2 px-3 d-flex align-items-center">
                <?= $cycle_id ? "دورة $cycle_year نشطة" : 'التقييم مغلق' ?>
            </span>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <?php if ($required_action_count > 0): ?>
                <div class="alert alert-warning border-warning shadow-sm d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1">تنبيه مهام</h5>
                        <p class="mb-0 fs-5">
                            يوجد لديك عدد <strong>(<?= $required_action_count ?>)</strong> موظف لم يتم تقييمه بعد. 
                            <a href="evaluate.php" class="btn btn-sm btn-dark ms-2">ابدأ التقييم الآن <i class="fas fa-arrow-left"></i></a>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success border-success shadow-sm d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1">عمل ممتاز!</h5>
                        <p class="mb-0 fs-5">لا يوجد موظفين لكي تقيمهم حالياً. جميع التقييمات المطلوبة منك مكتملة.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-white-50 text-uppercase">فريقي (الموظفين)</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $total_staff ?></h2>
                    <i class="fas fa-users card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-white-50 text-uppercase">تم التقييم</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $completed_evals ?></h2>
                    <div class="progress mt-3" style="height: 5px; background: rgba(255,255,255,0.3)">
                        <div class="progress-bar bg-white" style="width: <?= $completion_rate ?>%"></div>
                    </div>
                    <i class="fas fa-check-double card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3 stat-card h-100 border-0">
                <div class="card-body text-dark">
                    <h6 class="card-title text-dark text-opacity-50 text-uppercase">متبقي للتقييم</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $pending_evals ?></h2>
                    <i class="fas fa-hourglass-half card-icon text-dark"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-white-50 text-uppercase">متوسط درجات القسم</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $avg_score ?>%</h2>
                    <i class="fas fa-chart-line card-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold border-bottom-0 pt-3">
                    <i class="fas fa-chart-pie text-primary"></i> نسبة الإنجاز
                </div>
                <div class="card-body d-flex justify-content-center">
                    <div style="width: 250px; height: 250px;">
                        <canvas id="completionChart"></canvas>
                    </div>
                </div>
                <div class="card-footer bg-white text-center border-0">
                    <small class="text-muted">مكتمل: <?= $completed_evals ?> | متبقي: <?= $pending_evals ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold border-bottom-0 pt-3">
                    <i class="fas fa-chart-bar text-success"></i> توزيع الأداء (المعتمد)
                </div>
                <div class="card-body">
                    <canvas id="gradesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold border-bottom-0 pt-3">
                    <i class="fas fa-history text-muted"></i> آخر التقييمات المرسلة
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($recent_activity)): ?>
                            <div class="text-center py-5 text-muted">لا توجد نشاطات حديثة</div>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $act): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($act['name']) ?></h6>
                                    <small class="text-muted"><?= date('m-d', strtotime($act['updated_at'])) ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small>
                                        <?php 
                                        echo match($act['status']) {
                                            'approved' => '<span class="text-success"><i class="fas fa-check"></i> تم الاعتماد</span>',
                                            'rejected' => '<span class="text-danger"><i class="fas fa-times"></i> مرفوض</span>',
                                            'submitted' => '<span class="text-warning"><i class="fas fa-paper-plane"></i> بانتظار الموافقة</span>',
                                            default => '<span class="text-secondary">مسودة</span>'
                                        };
                                        ?>
                                    </small>
                                    <span class="badge bg-light text-dark border"><?= $act['total_score'] ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white text-center border-0 pb-3">
                    <a href="evaluate.php" class="btn btn-sm btn-primary w-100">بدء تقييم جديد</a>
                </div>
            </div>
        </div>
    </div>

</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>

<div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title"><i class="fas fa-bell text-warning"></i> التنبيهات</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <?php if (empty($modal_notifications)): ?>
            <div class="text-center py-4 text-muted">لا توجد إشعارات جديدة</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
            <?php foreach ($modal_notifications as $n): ?>
                <a href="notifications.php" class="list-group-item list-group-item-action <?= $n['is_read'] ? '' : 'unread' ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <strong class="text-<?= $n['type'] == 'danger' ? 'danger' : 'dark' ?>"><?= htmlspecialchars($n['title']) ?></strong>
                        <small class="text-muted" style="font-size:0.7rem"><?= date('H:i d/m', strtotime($n['created_at'])) ?></small>
                    </div>
                    <p class="mb-0 small text-secondary"><?= mb_strimwidth(htmlspecialchars($n['message']), 0, 50, '...') ?></p>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="notifications.php" class="btn btn-sm btn-secondary w-100">عرض كل الإشعارات</a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. مخطط الإنجاز
    new Chart(document.getElementById('completionChart'), {
        type: 'doughnut',
        data: {
            labels: ['مكتمل', 'متبقي'],
            datasets: [{
                data: [<?= $completed_evals ?>, <?= $pending_evals ?>],
                backgroundColor: ['#198754', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    });

    // 2. مخطط توزيع الدرجات
    new Chart(document.getElementById('gradesChart'), {
        type: 'bar',
        data: {
            labels: ['ممتاز (90+)', 'جيد جداً (80+)', 'جيد (70+)', 'أخرى'],
            datasets: [{
                label: 'عدد الموظفين',
                data: [<?= $grade_dist['A'] ?>, <?= $grade_dist['B'] ?>, <?= $grade_dist['C'] ?>, <?= $grade_dist['D'] ?>],
                backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#dc3545'],
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
<script src="../assets/js/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>