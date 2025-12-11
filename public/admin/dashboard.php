<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز الخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

$user_id = $_SESSION['user_id'];

// 1. جلب بيانات الإشعارات (للنافذة المنبثقة والزر)
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$user_id]);
$unread_count = $unread_stmt->fetchColumn();

// جلب آخر 5 إشعارات للعرض في النافذة
$latest_notifs_stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY is_read ASC, created_at DESC 
    LIMIT 5
");
$latest_notifs_stmt->execute([$user_id]);
$modal_notifications = $latest_notifs_stmt->fetchAll();

// 2. تحديد الدورة الحالية والإحصائيات العامة
$current_cycle = $pdo->query("SELECT * FROM evaluation_cycles WHERE status = 'active' ORDER BY year DESC LIMIT 1")->fetch();
$cycle_id = $current_cycle ? $current_cycle['id'] : null;
$cycle_year = $current_cycle ? $current_cycle['year'] : date('Y');

$total_employees = $pdo->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('admin', 'evaluator') AND status='active'")->fetchColumn();
$total_departments = $pdo->query("SELECT COUNT(*) FROM departments WHERE status='active'")->fetchColumn();

// تهيئة المتغيرات
$approved_evals = 0; $pending_evals = 0; $rejected_evals = 0; $draft_evals = 0;
$company_avg = 0; $completion_rate = 0;

if ($cycle_id) {
    $stats = $pdo->query("SELECT status, COUNT(*) as count FROM employee_evaluations WHERE cycle_id = $cycle_id GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $approved_evals = $stats['approved'] ?? 0;
    $pending_evals = $stats['submitted'] ?? 0;
    $rejected_evals = $stats['rejected'] ?? 0;
    $draft_evals = $stats['draft'] ?? 0;

    $avg_stmt = $pdo->query("SELECT AVG(total_score) FROM employee_evaluations WHERE cycle_id = $cycle_id AND status = 'approved'");
    $company_avg = round($avg_stmt->fetchColumn() ?: 0, 1);

    if ($total_employees > 0) {
        $completion_rate = round(($approved_evals / $total_employees) * 100, 1);
    }
}

// 3. بيانات الرسوم البيانية
$dept_labels = []; $dept_scores = [];
if ($cycle_id) {
    $dept_stmt = $pdo->query("SELECT d.name_ar, AVG(e.total_score) as avg_score FROM departments d JOIN users u ON u.department_id = d.id JOIN employee_evaluations e ON e.employee_id = u.id WHERE e.cycle_id = $cycle_id AND e.status = 'approved' GROUP BY d.id ORDER BY avg_score DESC LIMIT 5");
    while ($row = $dept_stmt->fetch()) {
        $dept_labels[] = $row['name_ar'];
        $dept_scores[] = round($row['avg_score'], 1);
    }
}

// 4. آخر النشاطات
$latest_activity = [];
if ($cycle_id) {
    $latest_stmt = $pdo->query("SELECT e.updated_at, u.name as emp_name, e.status, e.total_score FROM employee_evaluations e JOIN users u ON e.employee_id = u.id WHERE e.cycle_id = $cycle_id ORDER BY e.updated_at DESC LIMIT 6");
    $latest_activity = $latest_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { transition: transform 0.2s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-icon { position: absolute; left: 15px; top: 20px; opacity: 0.2; font-size: 3.5rem; transform: rotate(-15deg); }
        .progress-xs { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.3); }
        
        /* تنسيق زر الإشعارات النابض */
        .btn-pulse {
            animation: pulse-animation 2s infinite;
        }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        
        /* تنسيق قائمة الإشعارات في النافذة */
        .notification-item { border-bottom: 1px solid #eee; padding: 10px; transition: background 0.2s; }
        .notification-item:hover { background-color: #f8f9fa; }
        .notification-item:last-child { border-bottom: none; }
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
            <h3><i class="fas fa-tachometer-alt"></i> لوحة القيادة</h3>
            <span class="text-muted">الموسم الحالي: <strong><?= $cycle_year ?></strong></span>
        </div>
        
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn <?= $unread_count > 0 ? 'btn-warning text-dark btn-pulse' : 'btn-secondary' ?> position-relative" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                <i class="fas fa-bell <?= $unread_count > 0 ? 'fa-shake' : '' ?>"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $unread_count ?>
                        <span class="visually-hidden">unread</span>
                    </span>
                <?php endif; ?>
            </button>
            
            <span class="badge bg-<?= $cycle_id ? 'success' : 'secondary' ?> p-2 px-3">
                <?= $cycle_id ? 'الدورة نشطة' : 'لا توجد دورة' ?>
            </span>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase small opacity-75">إجمالي الموظفين</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $total_employees ?></h2>
                    <i class="fas fa-users card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-dark mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase small opacity-75">الإدارات الفعالة</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $total_departments ?></h2>
                    <i class="fas fa-building card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase small opacity-75">نسبة الإنجاز</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $completion_rate ?>%</h2>
                    <div class="progress progress-xs mt-3"><div class="progress-bar bg-white" style="width: <?= $completion_rate ?>%"></div></div>
                    <i class="fas fa-chart-pie card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary mb-3 stat-card h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase small opacity-75">متوسط الأداء</h6>
                    <h2 class="display-5 fw-bold mb-0"><?= $company_avg ?>%</h2>
                    <i class="fas fa-star-half-alt card-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 border-start border-5 border-success shadow-sm h-100 stat-card">
                <div class="card-body">
                    <h6 class="text-success text-uppercase small fw-bold">معتمد (Approved)</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= $approved_evals ?></h3>
                    <i class="fas fa-check-circle card-icon text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 border-start border-5 border-warning shadow-sm h-100 stat-card">
                <div class="card-body">
                    <h6 class="text-warning text-uppercase small fw-bold">انتظار (Pending)</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= $pending_evals ?></h3>
                    <i class="fas fa-clock card-icon text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 border-start border-5 border-danger shadow-sm h-100 stat-card">
                <div class="card-body">
                    <h6 class="text-danger text-uppercase small fw-bold">مرفوض (Rejected)</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= $rejected_evals ?></h3>
                    <i class="fas fa-times-circle card-icon text-danger"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 border-start border-5 border-secondary shadow-sm h-100 stat-card">
                <div class="card-body">
                    <h6 class="text-secondary text-uppercase small fw-bold">مسودة (Draft)</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?= $draft_evals ?></h3>
                    <i class="fas fa-pencil-alt card-icon text-secondary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold pt-3"><i class="fas fa-chart-doughnut text-primary"></i> توزيع الحالات</div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div style="width: 250px; height: 250px;"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold pt-3"><i class="fas fa-chart-bar text-success"></i> الأفضل أداءً</div>
                <div class="card-body">
                    <?php if (empty($dept_labels)): ?><div class="text-center text-muted py-5">لا توجد بيانات</div><?php else: ?><canvas id="deptChart"></canvas><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold pt-3"><i class="fas fa-history text-muted"></i> آخر النشاطات</div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($latest_activity)): ?>
                            <div class="text-center p-3 text-muted">لا توجد نشاطات</div>
                        <?php else: ?>
                            <?php foreach ($latest_activity as $act): ?>
                            <div class="list-group-item border-bottom-0">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 text-truncate fw-bold" style="max-width: 150px;"><?= htmlspecialchars($act['emp_name']) ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('m-d H:i', strtotime($act['updated_at'])) ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small>
                                        <?php 
                                        echo match($act['status']) {
                                            'approved' => '<span class="text-success"><i class="fas fa-check-circle"></i> معتمد</span>',
                                            'rejected' => '<span class="text-danger"><i class="fas fa-times-circle"></i> مرفوض</span>',
                                            'submitted' => '<span class="text-warning"><i class="fas fa-paper-plane"></i> مرسل وفي انتظار الموافقة</span>',
                                            default => '<span class="text-secondary">مسودة</span>'
                                        };
                                        ?>
                                    </small>
                                    <?php if($act['total_score']): ?><span class="badge bg-light text-dark border"><?= $act['total_score'] ?>%</span><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white text-center border-top-0 pb-3">
                    <a href="reports.php" class="btn btn-sm btn-light w-100">عرض الكل</a>
                </div>
            </div>
        </div>
    </div>

</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>

<div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="notificationsModalLabel"><i class="fas fa-bell"></i> آخر الإشعارات</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <?php if (empty($modal_notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="far fa-bell-slash fa-3x mb-3"></i><br>لا توجد إشعارات حالياً
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
            <?php foreach ($modal_notifications as $n): ?>
                <a href="notifications.php?read_id=<?= $n['id'] ?>" class="list-group-item list-group-item-action <?= $n['is_read'] ? '' : 'unread' ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1 fw-bold text-<?= $n['type'] == 'danger' ? 'danger' : ($n['type'] == 'success' ? 'success' : 'dark') ?>">
                            <?= htmlspecialchars($n['title']) ?>
                        </h6>
                        <small class="text-muted"><?= date('m-d H:i', strtotime($n['created_at'])) ?></small>
                    </div>
                    <p class="mb-1 small text-secondary"><?= mb_strimwidth(htmlspecialchars($n['message']), 0, 60, '...') ?></p>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <?php if ($unread_count > 0): ?>
            <a href="notifications.php?mark_all_read=1" class="btn btn-sm btn-outline-primary">تحديد الكل كمقروء</a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>
        <a href="notifications.php" class="btn btn-sm btn-secondary">عرض السجل الكامل</a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['معتمد', 'مرفوض', 'انتظار', 'مسودة'],
            datasets: [{
                data: [<?= $approved_evals ?>, <?= $rejected_evals ?>, <?= $pending_evals ?>, <?= $draft_evals ?>],
                backgroundColor: ['#198754', '#dc3545', '#ffc107', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    <?php if (!empty($dept_labels)): ?>
    const ctxDept = document.getElementById('deptChart').getContext('2d');
    new Chart(ctxDept, {
        type: 'bar',
        data: {
            labels: <?= json_encode($dept_labels) ?>,
            datasets: [{
                label: 'متوسط الدرجة',
                data: <?= json_encode($dept_scores) ?>,
                backgroundColor: '#0d6efd',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y', responsive: true,
            scales: { x: { beginAtZero: true, max: 100, grid: {display:false} }, y: {grid:{display:false}} },
            plugins: { legend: { display: false } }
        }
    });
    <?php endif; ?>
});
</script>
<script src="../assets/js/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>