<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

$supervisor_id = $_SESSION['user_id'];

// 1. جلب الإشعارات
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$supervisor_id]);
$unread_count = $unread_stmt->fetchColumn();

$latest_notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$latest_notifs->execute([$supervisor_id]);
$modal_notifications = $latest_notifs->fetchAll();

// 2. الدورة الحالية
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();

// تهيئة المتغيرات
$employees = [];
$approved_count = 0; 
$rejected_count = 0; 
$pending_count = 0; 
$not_evaluated_count = 0;
$grade_dist = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0]; // (جديد) توزيع الدرجات
$team_avg = 0; // متوسط درجات الفريق
$total_scores_sum = 0;
$scored_employees_count = 0;

if ($active_cycle) {
    // جلب الموظفين التابعين للرئيس المباشر وحالاتهم
    $employees_stmt = $pdo->prepare("
        SELECT u.*, d.name_ar as dept_name,
               (SELECT status FROM employee_evaluations WHERE employee_id = u.id AND evaluator_role = 'supervisor' AND cycle_id = ?) as supervisor_status,
               (SELECT status FROM employee_evaluations WHERE employee_id = u.id AND evaluator_role = 'manager' AND cycle_id = ?) as manager_status,
               (SELECT total_score FROM employee_evaluations WHERE employee_id = u.id AND evaluator_role = 'supervisor' AND cycle_id = ?) as my_score
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.supervisor_id = ? AND u.role IN ('employee', 'evaluator') AND u.status = 'active'
        ORDER BY u.name
    ");
    $employees_stmt->execute([$active_cycle['id'], $active_cycle['id'], $active_cycle['id'], $supervisor_id]);
    $employees = $employees_stmt->fetchAll();

    // حساب الإحصائيات
    foreach ($employees as $e) {
        $supervisor_status = $e['supervisor_status'];
        
        // حالات التقييم (بناءً على تقييم المشرف نفسه)
        if ($supervisor_status === 'approved') { // ملاحظة: المشرف عادة يرسل 'submitted' للمدير، لكن لو اعتمد النظام 'approved'
            $approved_count++;
        } elseif ($supervisor_status === 'submitted') {
            $pending_count++; // تم الإرسال للمدير
        } elseif ($supervisor_status === 'draft') {
            $not_evaluated_count++; // يعتبر قيد العمل (أو يمكن فصله)
        } else {
            $not_evaluated_count++; // لم يبدأ
        }

        // حساب توزيع الدرجات (للتقييمات التي وضعها المشرف)
        if ($e['my_score'] !== null) {
            $s = $e['my_score'];
            $total_scores_sum += $s;
            $scored_employees_count++;

            if ($s >= 90) $grade_dist['A']++;
            elseif ($s >= 80) $grade_dist['B']++;
            elseif ($s >= 70) $grade_dist['C']++;
            else $grade_dist['D']++;
        }
    }

    if ($scored_employees_count > 0) {
        $team_avg = round($total_scores_sum / $scored_employees_count, 1);
    }
}

// جلب التقييمات المطلوبة (التي لم يرسلها المشرف بعد)
$required_evaluations_supervisor = [];
if ($active_cycle) {
    $required_eval_stmt = $pdo->prepare("
        SELECT u.id, u.name, u.job_title, d.name_ar as dept_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.supervisor_id = ? 
        AND u.role IN ('employee', 'evaluator')
        AND u.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM employee_evaluations e
            WHERE e.employee_id = u.id 
            AND e.cycle_id = ? 
            AND e.evaluator_role = 'supervisor'
            AND e.status IN ('submitted', 'approved')
        )
        ORDER BY u.name
    ");
    $required_eval_stmt->execute([$supervisor_id, $active_cycle['id']]);
    $required_evaluations_supervisor = $required_eval_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>لوحة الرئيس المباشر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
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
            <h3><i class="fas fa-user-tie"></i> لوحة الرئيس المباشر</h3>
            <span class="text-muted">مرحباً بك، <strong><?= htmlspecialchars($_SESSION['name']) ?></strong></span>
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
            <span class="badge bg-<?= $active_cycle ? 'success' : 'secondary' ?> p-2 px-3 d-flex align-items-center">
                <?= $active_cycle ? "الدورة نشطة ({$active_cycle['year']})" : 'التقييم مغلق' ?>
            </span>
        </div>
    </div>

    <div class="global-search-container mb-4">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن موظف...">
        <div id="search-results"></div>
    </div>

    <?php if (!$active_cycle): ?>
        <div class="alert alert-warning">لا توجد دورة تقييم نشطة حاليًا.</div>
    <?php else: ?>
        
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-primary h-100 stat-card border-0">
                    <div class="card-body">
                        <h6 class="text-white-50">إجمالي الفريق</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= count($employees) ?></h2>
                        <i class="fas fa-users card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-success h-100 stat-card border-0">
                    <div class="card-body">
                        <h6 class="text-white-50">تم التقييم (مرسل)</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= $pending_count + $approved_count ?></h2>
                        <i class="fas fa-paper-plane card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-warning h-100 stat-card border-0">
                    <div class="card-body text-dark">
                        <h6 class="text-dark text-opacity-50">مطلوب تقييمهم</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= count($required_evaluations_supervisor) ?></h2>
                        <i class="fas fa-exclamation-circle card-icon text-dark"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-white bg-info h-100 stat-card border-0">
                    <div class="card-body">
                        <h6 class="text-white-50">متوسط الدرجات</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= $team_avg ?>%</h2>
                        <i class="fas fa-chart-line card-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($required_evaluations_supervisor)): ?>
        <div class="card mb-4 border-danger shadow-sm">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-tasks"></i> مهام معلقة: تقييم الموظفين</span>
                <span class="badge bg-white text-danger"><?= count($required_evaluations_supervisor) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>الموظف</th>
                                <th>الوظيفة</th>
                                <th>الإدارة</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($required_evaluations_supervisor as $e): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($e['name']) ?></td>
                                <td><?= htmlspecialchars($e['job_title'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($e['dept_name'] ?? '—') ?></td>
                                <td>
                                    <a href="evaluate.php?employee=<?= $e['id'] ?>" class="btn btn-sm btn-danger px-3">
                                        <i class="fas fa-edit"></i> تقييم
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white fw-bold border-bottom-0 pt-3">
                        <i class="fas fa-chart-pie text-primary"></i> حالة الإنجاز
                    </div>
                    <div class="card-body d-flex justify-content-center">
                        <div style="width: 250px; height: 250px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white fw-bold border-bottom-0 pt-3">
                        <i class="fas fa-chart-bar text-success"></i> توزيع التقديرات (لفريقك)
                    </div>
                    <div class="card-body">
                        <canvas id="gradesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-list"></i> جميع الموظفين التابعين لك
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>الاسم</th>
                                <th>الوظيفة</th>
                                <th>الإدارة</th>
                                <th>حالة تقييمك</th>
                                <th>درجة تقييمك</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">لا يوجد موظفين مسجلين تحت إشرافك.</td></tr>
                            <?php else: ?>
                                <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['name']) ?></td>
                                    <td><?= htmlspecialchars($e['job_title'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($e['dept_name'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($e['supervisor_status']): ?>
                                            <?php if ($e['supervisor_status'] === 'submitted' || $e['supervisor_status'] === 'approved'): ?>
                                                <span class="badge bg-success">تم الإرسال</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">مسودة</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">لم يبدأ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($e['my_score'] !== null): ?>
                                            <span class="fw-bold text-primary"><?= $e['my_score'] ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="evaluate.php?employee=<?= $e['id'] ?>" class="btn btn-sm btn-primary">
                                            <?= ($e['supervisor_status'] === 'submitted' || $e['supervisor_status'] === 'approved') ? '<i class="fas fa-eye"></i> عرض' : '<i class="fas fa-edit"></i> تقييم' ?>
                                        </a>
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

<form id="logout-form" action="../logout.php" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logout_csrf_token) ?>">
</form>

<?php if ($active_cycle): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. مخطط الحالة (دائري)
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['تم التقييم', 'مسودة', 'لم يبدأ'],
            datasets: [{
                // استخدام البيانات التي حسبناها في PHP
                data: [<?= $pending_count + $approved_count ?>, <?= $not_evaluated_count - ($not_evaluated_count - $pending_count) ?>, <?= $not_evaluated_count ?>], // هنا نحتاج دقة أكثر، لذا سنستخدم: (المرسل + المعتمد), (المسودة), (الجديد)
                // تصحيح البيانات:
                // Approved + Submitted = مرسل
                // Draft = مسودة (وهي جزء من not_evaluated في الكود السابق، لذا يفضل فصلها في ال PHP إذا أردت دقة، لكن هنا سنبسطها)
                data: [<?= $pending_count + $approved_count ?>, 0, <?= $not_evaluated_count ?>], // للتبسيط حالياً
                backgroundColor: ['#198754', '#ffc107', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    });

    // 2. مخطط الدرجات (أعمدة)
    const ctxGrades = document.getElementById('gradesChart').getContext('2d');
    new Chart(ctxGrades, {
        type: 'bar',
        data: {
            labels: ['ممتاز (90+)', 'جيد جداً (80+)', 'جيد (70+)', 'أقل'],
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
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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