<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// (جديد) توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];
// =============================
require_once '../../app/core/db.php';

$supervisor_id = $_SESSION['user_id'];
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
        WHERE u.supervisor_id = ? 
        AND e.cycle_id = ?
        AND e.evaluator_role = 'supervisor'
        ORDER BY u.name
    ");
    $reports->execute([$supervisor_id, $active_cycle['id']]);
    $reports = $reports->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>التقارير - الرئيس المباشر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
</head>
<body class="admin-dashboard">

<?php 
// (مُعدَّل) استدعاء شريط التنقل الموحد بدلاً من الأكواد المكررة
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <h3><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h3>
    <hr>

    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم...">
        <div id="search-results"></div>
    </div>

    <?php if (!$active_cycle): ?>
        <div class="alert alert-warning">لا توجد دورة تقييم نشطة حاليًا.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> تقييمات موظفيك (دورة <?= $active_cycle['year'] ?>)
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
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
                                <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['employee_name']) ?></td>
                                    <td><?= htmlspecialchars($r['email']) ?></td>
                                    <td><small><?= $r['dept_name'] ?? '—' ?></small></td>
                                    <td><small>
                                        <?= htmlspecialchars($r['evaluator_name']) ?>
                                        (<?= $r['evaluator_role'] === 'manager' ? 'مدير إدارة' : 'رئيس مباشر' ?>)
                                   </small> </td>
                                    <td><?= $r['total_score'] ?? '—' ?></td>
                                    <td>
                                        <?php
                                        $status = $r['evaluation_status'];
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
                                    <td><small><?= $r['updated_at'] ? date('Y-m-d H:i', strtotime($r['updated_at'])) : '—' ?></td>
                                   </small> <td>
                                        <?php 
                                        // جلب الـ token
                                        $token_stmt = $pdo->prepare("
                                            SELECT unique_token FROM employee_evaluation_links 
                                            WHERE employee_id = ? AND cycle_id = ?
                                        ");
                                        $token_stmt->execute([$r['employee_id'], $active_cycle['id']]);
                                        $token = $token_stmt->fetchColumn();
                                        ?>
                                        <?php if ($token): ?>
                                            <a href="../view-ev-report.php?token=<?= $token ?>" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-link"></i> عرض
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                <i class="fas fa-spinner fa-spin"></i> يُقيم...
                                            </button>
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

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-users"></i></h5>
                        <h4><?= count($reports) ?></h4>
                        <p class="mb-0">عدد التقييمات</p>
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