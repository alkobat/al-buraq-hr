<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

// --- الاحصائيات الجديدة ---
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();

$employees = [];
if ($active_cycle) {
    $employees_stmt = $pdo->prepare("
        SELECT u.*, d.name_ar as dept_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role IN ('employee', 'evaluator')
        ORDER BY u.name
    ");
    $employees_stmt->execute();
    $employees = $employees_stmt->fetchAll();
}

$approved_count = $rejected_count = $pending_count = $not_evaluated_count = 0;
if ($active_cycle) {
    // جلب جميع التقييمات في الدورة النشطة
    $all_evals = $pdo->prepare("
        SELECT employee_id, evaluator_role, status
        FROM employee_evaluations
        WHERE cycle_id = ?
    ");
    $all_evals->execute([$active_cycle['id']]);
    $all_evals = $all_evals->fetchAll();

    // تنظيم التقييمات حسب الموظف
    $employee_evals = [];
    foreach ($all_evals as $eval) {
        $employee_evals[$eval['employee_id']][] = $eval;
    }

    foreach ($employees as $emp) {
        $emp_id = $emp['id'];
        $evals = $employee_evals[$emp_id] ?? [];

        $supervisor_status = null;
        $manager_status = null;

        foreach ($evals as $e) {
            if ($e['evaluator_role'] === 'supervisor') {
                $supervisor_status = $e['status'];
            } elseif ($e['evaluator_role'] === 'manager') {
                $manager_status = $e['status'];
            }
        }

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

// --- الاحصائيات القديمة ---
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$activeCycles = $pdo->query("SELECT COUNT(*) FROM evaluation_cycles WHERE status = 'active'")->fetchColumn();
$pendingApprovals = $pdo->query("SELECT COUNT(*) FROM employee_evaluations WHERE status = 'submitted'")->fetchColumn();
$departmentsCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم - المسؤول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css  " rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css  ">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">

    <h5>المسؤول</h5>
    <a href="dashboard.php" class="active">
        <i class="fas fa-home"></i> لوحة التحكم
    </a>
    <a href="users.php">
        <i class="fas fa-users"></i> إدارة المستخدمين
    </a>
    <a href="departments.php">
        <i class="fas fa-building"></i> الإدارات
    </a>
    <a href="cycles.php">
        <i class="fas fa-calendar-alt"></i> دورات التقييم
    </a>
    <a href="evaluation-fields.php">
        <i class="fas fa-list"></i> مجالات التقييم
    </a>
    <a href="reports.php">
        <i class="fas fa-chart-bar"></i> التقارير والإحصائيات
    </a>
    <a href="settings.php">
        <i class="fas fa-cog"></i> الإعدادات
    </a>
	<a href="notifications.php" class="active"><i class="fas fa-bell"></i> الإشعارات</a>
    <a href="../logout.php" class="logout-link">
        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
    </a>
</nav>

<main class="admin-main-content">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fas fa-home"></i> لوحة التحكم</h3>
	 <span class="text-muted">مرحباً، <?= htmlspecialchars($_SESSION['name']) ?></span>
<div id="realtime-notifications" class="btn-group position-relative">
    <button type="button" class="btn btn-outline-primary position-relative" id="notifications-btn">
        <i class="fas fa-bell"></i>
        <span id="notification-count" class="position-bsolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            0
        </span>
    </button>
    <div id="notifications-dropdown" class="dropdown-menu" style="max-width: 300px; max-height: 300px; overflow-y: auto; display: none; position: fixed; z-index: 1000; background: white; border: 1px solid #ddd; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 10px;">
        <!-- الإشعارات ستُملأ هنا بواسطة JavaScript -->
    </div>
</div>
</div>
 

    <!-- خانة البحث العالمية -->
    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم (الاسم أو البريد)...">
        <div id="search-results"></div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-users card-icon"></i></h5>
                    <h4><?= $totalEmployees ?></h4>
                    <p class="mb-0">إجمالي الموظفين</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-calendar-check card-icon"></i></h5>
                    <h4><?= $activeCycles ?></h4>
                    <p class="mb-0">دورات نشطة</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card text-white bg-warning h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-clock card-icon"></i></h5>
                    <h4><?= $pendingApprovals ?></h4>
                    <p class="mb-0">تقييمات بانتظار الموافقة</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card text-white bg-info h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-building card-icon"></i></h5>
                    <h4><?= $departmentsCount ?></h4>
                    <p class="mb-0">عدد الإدارات</p>
                </div>
            </div>
        </div>
    </div>

    <!-- الإحصائيات الجديدة -->
    <?php if ($active_cycle): ?>
    <div class="row mb-4">
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
            <div class="card text-white bg-danger h-100">
                <div class="card-body text-center">
                    <h5><i class="fas fa-times-circle card-icon"></i></h5>
                    <h4><?= $rejected_count ?></h4>
                    <p class="mb-0">مرفوضون</p>
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
                    <h5><i class="fas fa-ban card-icon"></i></h5>
                    <h4><?= $not_evaluated_count ?></h4>
                    <p class="mb-0">لم يُقيّموا</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-bell"></i> الإشعارات الحديثة
        </div>
        <div class="card-body">
            <?php
            $nots = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5");
            $nots->execute([$_SESSION['user_id']]);
            $notifications = $nots->fetchAll();
            if ($notifications): ?>
                <ul class="list-group">
                    <?php foreach ($notifications as $n): ?>
                    <li class="list-group-item <?= $n['is_read'] ? '' : 'fw-bold bg-light' ?>">
                        <?= htmlspecialchars($n['message']) ?>
                        <small class="text-muted float-end"><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted">لا توجد إشعارات جديدة.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>

<script>
function checkInternet() {
    fetch('https://www.google.com  ', { method: 'HEAD', mode: 'no-cors' })
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
<script>
function updateNotifications() {
    fetch('../realtime-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) return;

            const count = data.count;
            document.getElementById('notification-count').textContent = count;

            // إذا كان هناك إشعارات جديدة، اعرضها في القائمة
            const dropdown = document.getElementById('notifications-dropdown');
            let notificationList = '';

            if (count > 0) {
                data.notifications.forEach(n => {
                    notificationList += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${n.title}</strong>
                                <p class="mb-0">${n.message.substring(0, 50)}...</p>
                                <small class="text-muted">${new Date(n.created_at).toLocaleString()}</small>
                            </div>
                            <span class="badge bg-${n.type}">${n.type === 'info' ? 'معلومة' : (n.type === 'success' ? 'نجاح' : (n.type === 'warning' ? 'تحذير' : 'خطا'))}</span>
                        </li>
                    `;
                });
                dropdown.innerHTML = `<ul class="list-group">${notificationList}</ul>`;
                dropdown.style.display = 'block';
            } else {
                dropdown.innerHTML = '<p class="text-center text-muted mt-3">لا توجد إشعارات.</p>';
                dropdown.style.display = 'none';
            }

            // حساب موقع القائمة
            if (dropdown.style.display === 'block') {
                const btn = document.getElementById('notifications-btn');
                const btnRect = btn.getBoundingClientRect();
                const dropdownWidth = 300; // عرض القائمة
                const dropdownHeight = 300; // ارتفاع القائمة

                // حساب المسافة من اليمين
                const right = window.innerWidth - btnRect.right;
                const top = btnRect.bottom + window.scrollY;

              
   			    //dropdown.style.left = `${left}px`;
			    dropdown.style.left = `50px`;
                dropdown.style.top = `${top}px`;
            }
        })
        .catch(err => console.log('Error:', err));
}

// تحديث الإشعارات كل 30 ثانية
setInterval(updateNotifications, 30000);

// عرض/إخفاء القائمة عند النقر على الزر
document.getElementById('notifications-btn').addEventListener('click', function() {
    const dropdown = document.getElementById('notifications-dropdown');
    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        // إعادة حساب الموقع
        const btn = document.getElementById('notifications-btn');
        const btnRect = btn.getBoundingClientRect();
        const right = window.innerWidth - btnRect.right;
        const top = btnRect.bottom + window.scrollY;
        //dropdown.style.right = `${right}px`;
        dropdown.style.left = `50px`;
        dropdown.style.top = `${top}px`;
    } else {
        dropdown.style.display = 'none';
    }
});

// إغلاق القائمة عند النقر خارجها
document.addEventListener('click', function(event) {
    const btn = document.getElementById('notifications-btn');
    const dropdown = document.getElementById('notifications-dropdown');
    if (!btn.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});

// تحديث عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', updateNotifications);
</script>
</body>
</html>