<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// CSRF (كود الحماية المعتاد)
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

// إعدادات الصفحات والفلترة
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$filter_user = trim($_GET['user'] ?? '');
$filter_action = $_GET['action'] ?? '';
$filter_date = $_GET['date'] ?? '';

$where = ["1=1"];
$params = [];

if ($filter_user) {
    $where[] = "(user_name LIKE ? OR description LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
}
if ($filter_action) {
    $where[] = "action = ?";
    $params[] = $filter_action;
}
if ($filter_date) {
    $where[] = "DATE(created_at) = ?";
    $params[] = $filter_date;
}

$where_sql = implode(' AND ', $where);

// حساب العدد الكلي
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE $where_sql");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// جلب البيانات
$sql = "SELECT * FROM activity_logs WHERE $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>سجل النشاطات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-history"></i> سجل نشاطات النظام</h3>
        <span class="badge bg-secondary">العدد: <?= $total_rows ?></span>
    </div>
    <hr>

    <div class="card mb-4 bg-light border-0 shadow-sm">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="user" class="form-control" placeholder="بحث باسم المستخدم أو الوصف..." value="<?= htmlspecialchars($filter_user) ?>">
                </div>
                <div class="col-md-3">
                    <select name="action" class="form-select">
                        <option value="">كل العمليات</option>
                        <option value="login" <?= $filter_action=='login'?'selected':'' ?>>تسجيل دخول</option>
						<option value="logout" <?= $filter_action=='logout'?'selected':'' ?>>تسجيل خروج</option>
                        <option value="create" <?= $filter_action=='create'?'selected':'' ?>>إضافة</option>
                        <option value="update" <?= $filter_action=='update'?'selected':'' ?>>تعديل</option>
                        <option value="delete" <?= $filter_action=='delete'?'selected':'' ?>>حذف</option>
                        <option value="evaluation" <?= $filter_action=='evaluation'?'selected':'' ?>>تقييم</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>الوقت</th>
                            <th>المستخدم</th>
                            <th>الدور</th>
                            <th>نوع العملية</th>
                            <th>التفاصيل</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">لا توجد سجلات.</td></tr>
                        <?php else: ?>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td dir="ltr" class="text-end"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($log['user_name']) ?></td>
								  <?php 
                                $role_map = match($log['role']) {
        'admin' => 'مسؤول',
        'manager' => 'مدير إدارة',
        'supervisor' => 'رئيس مباشر',
        'evaluator' => 'موظف تقييمات',
        'employee' => 'موظف'
                                };
                          
                                ?>
                                <td><span class="badge bg-secondary"><?= $role_map ?></span></td>
                                <td>
                                   <?php 
                                $cls = match($log['action']) {
                                    'login' => 'info',
                                    'logout' => 'secondary',
                                    'create' => 'success', 
                                    'delete' => 'danger', 
                                    'update' => 'warning', 
                                    default => 'dark'
                                };
                                $txt = match($log['action']) {
                                    'login' => 'دخول',
                                    'logout' => 'خروج',
                                    'create' => 'إضافة',
                                    'delete' => 'حذف',
                                    'update' => 'تعديل',
                                    default => $log['action']
                                };
                                ?>
                                    <span class="badge bg-<?= $cls ?>"><?= $txt ?></span>
                                </td>
                                <td><?= htmlspecialchars($log['description']) ?></td>
                                <td class="small text-muted"><?= $log['ip_address'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-center">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&user=<?= urlencode($filter_user) ?>&action=<?= $filter_action ?>&date=<?= $filter_date ?>">السابق</a>
                    </li>
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= $i==$page?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&user=<?= urlencode($filter_user) ?>&action=<?= $filter_action ?>&date=<?= $filter_date ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&user=<?= urlencode($filter_user) ?>&action=<?= $filter_action ?>&date=<?= $filter_date ?>">التالي</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</main>
<script src="../assets/js/search.js"></script>
</body>
</html>