<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

// --- الحذف ---
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: users.php?msg=deleted');
    exit;
}

// --- التعديل ---
if ($_POST && isset($_POST['edit_user'])) {
    $id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $dept = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $manager = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
    $supervisor = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $job = trim($_POST['job_title']);
    $status = $_POST['status'];

    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->execute([$email, $id]);
    if ($stmt_check->fetch()) {
        $error = "البريد الإلكتروني مستخدم من قبل مستخدم آخر.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, role = ?, department_id = ?, manager_id = ?, supervisor_id = ?, job_title = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $role, $dept, $manager, $supervisor, $job, $status, $id]);
        header('Location: users.php?msg=edited');
        exit;
    }
}

// --- الإضافة ---
if ($_POST && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $dept = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $manager = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
    $supervisor = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
    $job = trim($_POST['job_title']);
    
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) {
        $error = "البريد الإلكتروني مستخدم مسبقًا.";
    } else {
        $password = !empty($_POST['password']) ? $_POST['password'] : bin2hex(random_bytes(4));
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, department_id, manager_id, supervisor_id, job_title, force_password_change)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$name, $email, $hashed, $role, $dept, $manager, $supervisor, $job]);
        header('Location: users.php?msg=added');
        exit;
    }
}

// --- جلب بيانات المستخدم للتعديل ---
$user_to_edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $user_to_edit = $stmt->fetch();
}

// جلب البيانات للقوائم المنسدلة
$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM users WHERE role IN ('manager', 'evaluator')")->fetchAll();
$supervisors = $pdo->query("SELECT id, name FROM users WHERE role IN ('supervisor', 'evaluator')")->fetchAll();

// === معالجة فلاتر المسؤولين/موظفي التقييمات ===
$admin_filters = [
    'role' => $_GET['admin_role'] ?? '',
    'sort' => $_GET['admin_sort'] ?? 'name_asc'
];

// === معالجة فلاتر المستخدمين العاديين ===
$regular_filters = [
    'role' => $_GET['regular_role'] ?? '',
    'department' => $_GET['regular_department'] ?? '',
    'sort' => $_GET['regular_sort'] ?? 'name_asc'
];

// === جلب جميع المستخدمين ===
$all_users = $pdo->query("
    SELECT u.*, d.name_ar as dept_name, 
           m.name as manager_name, 
           s.name as supervisor_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users m ON u.manager_id = m.id
    LEFT JOIN users s ON u.supervisor_id = s.id
    ORDER BY u.name
")->fetchAll();

// فصل المستخدمين
$admin_users = [];
$evaluator_users = [];
$regular_users = [];

foreach ($all_users as $u) {
    if ($u['role'] === 'admin') {
        $admin_users[] = $u;
    } elseif ($u['role'] === 'evaluator') {
        $evaluator_users[] = $u;
        $regular_users[] = $u;
    } else {
        $regular_users[] = $u;
    }
}

$admin_evaluators = $admin_users;

// === جلب المسؤولين وموظفي التقييمات مع الفلاتر ===
$admin_sql = "
    SELECT u.*, d.name_ar as dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role = 'admin'
";
$admin_params = [];
$sortOptions = [
    'name_asc' => 'u.name ASC',
    'name_desc' => 'u.name DESC',
    'date_asc' => 'u.created_at ASC',
    'date_desc' => 'u.created_at DESC'
];
$admin_orderBy = $sortOptions[$admin_filters['sort']] ?? 'u.name ASC';
$admin_sql .= " ORDER BY $admin_orderBy";
$admin_stmt = $pdo->prepare($admin_sql);
$admin_stmt->execute($admin_params);
$admin_evaluators = $admin_stmt->fetchAll();

// === جلب المستخدمين العاديين مع الفلاتر ===
$regular_sql = "
    SELECT u.*, d.name_ar as dept_name, 
           m.name as manager_name, 
           s.name as supervisor_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users m ON u.manager_id = m.id
    LEFT JOIN users s ON u.supervisor_id = s.id
    WHERE u.role IN ('manager', 'supervisor', 'employee', 'evaluator')
";
$regular_params = [];
if (!empty($regular_filters['role'])) {
    $regular_sql .= " AND u.role = ?";
    $regular_params[] = $regular_filters['role'];
}
if (!empty($regular_filters['department'])) {
    $regular_sql .= " AND u.department_id = ?";
    $regular_params[] = $regular_filters['department'];
}
$regular_orderBy = $sortOptions[$regular_filters['sort']] ?? 'u.name ASC';
$regular_sql .= " ORDER BY $regular_orderBy";
$regular_stmt = $pdo->prepare($regular_sql);
$regular_stmt->execute($regular_params);
$regular_users = $regular_stmt->fetchAll();

$roles = ['admin', 'manager', 'supervisor', 'employee', 'evaluator'];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة المستخدمين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>المسؤول</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="users.php" class="active"><i class="fas fa-users"></i> إدارة المستخدمين</a>
    <a href="departments.php"><i class="fas fa-building"></i> الإدارات</a>
    <a href="cycles.php"><i class="fas fa-calendar-alt"></i> دورات التقييم</a>
    <a href="evaluation-fields.php"><i class="fas fa-list"></i> مجالات التقييم</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-users"></i> إدارة المستخدمين</h3>
    <hr>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success">تم إضافة المستخدم بنجاح.</div>
        <?php elseif ($_GET['msg'] === 'edited'): ?>
            <div class="alert alert-info">تم تحديث بيانات المستخدم.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-warning">تم حذف المستخدم.</div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-<?= $user_to_edit ? 'warning' : 'primary' ?> text-white">
            <i class="fas fa-<?= $user_to_edit ? 'edit' : 'plus' ?>"></i> 
            <?= $user_to_edit ? 'تعديل مستخدم' : 'إضافة مستخدم جديد' ?>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?php if ($user_to_edit): ?>
                    <input type="hidden" name="user_id" value="<?= $user_to_edit['id'] ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= $user_to_edit['name'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= $user_to_edit['email'] ?? '' ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>الدور <span class="text-danger">*</span></label>
                        <select name="role" class="form-control" required onchange="toggleFields(this.value)">
                            <option value="">اختر...</option>
                            <option value="admin" <?= ($user_to_edit && $user_to_edit['role'] == 'admin') ? 'selected' : '' ?>>مسؤول</option>
                            <option value="manager" <?= ($user_to_edit && $user_to_edit['role'] == 'manager') ? 'selected' : '' ?>>مدير إدارة</option>
                            <option value="supervisor" <?= ($user_to_edit && $user_to_edit['role'] == 'supervisor') ? 'selected' : '' ?>>رئيس مباشر</option>
                            <option value="employee" <?= ($user_to_edit && $user_to_edit['role'] == 'employee') ? 'selected' : '' ?>>موظف</option>
                            <option value="evaluator" <?= ($user_to_edit && $user_to_edit['role'] == 'evaluator') ? 'selected' : '' ?>>موظف تقييمات</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>الحالة</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= (!$user_to_edit || $user_to_edit['status'] == 'active') ? 'selected' : '' ?>>نشط</option>
                            <option value="inactive" <?= ($user_to_edit && $user_to_edit['status'] == 'inactive') ? 'selected' : '' ?>>معطل</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>الإدارة</label>
                        <select name="department_id" class="form-control dep-field" 
                                <?= ($user_to_edit && in_array($user_to_edit['role'], ['employee','evaluator','manager','supervisor'])) ? '' : 'disabled' ?>>
                            <option value="">بدون</option>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" 
                                        <?= ($user_to_edit && $user_to_edit['department_id'] == $d['id']) ? 'selected' : '' ?>
                                        <?= $d['status'] !== 'active' ? 'style="color:#6c757d;"' : '' ?>>
                                    <?= htmlspecialchars($d['name_ar']) ?>
                                    <?= $d['status'] !== 'active' ? ' (معطلة)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option disabled>لا توجد إدارات</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>كلمة المرور</label>
                        <input type="password" name="password" class="form-control" placeholder="اترك فارغًا للإبقاء على الحالية">
                    </div>
                </div>
                
                <div class="row manager-supervisor-fields" 
                     style="display:<?= ($user_to_edit && in_array($user_to_edit['role'], ['employee','evaluator'])) ? 'block' : 'none' ?>;">
                    <div class="col-md-6 mb-3">
                        <label>مدير الإدارة</label>
                        <select name="manager_id" class="form-control">
                            <option value="">بدون</option>
                            <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($user_to_edit && $user_to_edit['manager_id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>الرئيس المباشر</label>
                        <select name="supervisor_id" class="form-control" 
                                <?= ($user_to_edit && in_array($user_to_edit['role'], ['employee','evaluator'])) ? '' : 'disabled' ?>>
                            <option value="">بدون</option>
                            <?php foreach ($supervisors as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($user_to_edit && $user_to_edit['supervisor_id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label>الوظيفة</label>
                    <input type="text" name="job_title" class="form-control" value="<?= $user_to_edit['job_title'] ?? '' ?>">
                </div>
                <button type="submit" name="<?= $user_to_edit ? 'edit_user' : 'add_user' ?>" class="btn btn-<?= $user_to_edit ? 'warning' : 'success' ?>">
                    <?= $user_to_edit ? 'حفظ التعديلات' : 'إضافة المستخدم' ?>
                </button>
                <?php if ($user_to_edit): ?>
                    <a href="users.php" class="btn btn-secondary">إلغاء</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="global-search-container">
        <input type="text" id="global-search" class="form-control" placeholder="ابحث عن مستخدم (الاسم أو البريد)...">
        <div id="search-results"></div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user-shield"></i> المسؤولون وموظفو التقييمات</span>
            <span class="badge bg-light text-dark"><?= count($admin_users) + count($evaluator_users) ?></span>
        </div>
        <div class="card-body">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-filter"></i> فلاتر المسؤولين
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <label>الدور</label>
                            <select name="admin_role" class="form-control" disabled>
                                <option value="">الكل</option>
                                <option value="admin" selected>مسؤول</option>
                                <option value="evaluator">موظف تقييمات</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>الفرز</label>
                            <select name="admin_sort" class="form-control">
                                <option value="name_asc" <?= $admin_filters['sort'] == 'name_asc' ? 'selected' : '' ?>>الاسم (أ-ي)</option>
                                <option value="name_desc" <?= $admin_filters['sort'] == 'name_desc' ? 'selected' : '' ?>>الاسم (ي-أ)</option>
                                <option value="date_desc" <?= $admin_filters['sort'] == 'date_desc' ? 'selected' : '' ?>>الأحدث أولًا</option>
                                <option value="date_asc" <?= $admin_filters['sort'] == 'date_asc' ? 'selected' : '' ?>>الأقدم أولًا</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">تطبيق</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php 
            $combined_admin = array_merge($admin_users, $evaluator_users);
            ?>
            <?php if (empty($combined_admin)): ?>
                <div class="text-center text-muted">لا توجد حسابات مسؤول أو موظف تقييمات.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>البريد</th>
                                <th>الدور</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($combined_admin as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?= match($u['role']) {
                                        'admin' => 'مسؤول',
                                        'evaluator' => 'موظف تقييمات',
                                        default => $u['role']
                                    } ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= $u['status'] === 'active' ? 'نشط' : 'معطل' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning me-1">تعديل</a>
                                    <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">حذف</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-users"></i> المستخدمون العاديون (المدراء، الرؤساء، الموظفون، موظفو التقييمات)</span>
            <span class="badge bg-light text-dark"><?= count($regular_users) ?></span>
        </div>
        <div class="card-body">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-filter"></i> فلاتر المستخدمين العاديين
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-3">
                            <label>الدور</label>
                            <select name="regular_role" class="form-control">
                                <option value="">الكل</option>
                                <option value="manager" <?= ($regular_filters['role'] == 'manager') ? 'selected' : '' ?>>مدير إدارة</option>
                                <option value="supervisor" <?= ($regular_filters['role'] == 'supervisor') ? 'selected' : '' ?>>رئيس مباشر</option>
                                <option value="employee" <?= ($regular_filters['role'] == 'employee') ? 'selected' : '' ?>>موظف</option>
                                <option value="evaluator" <?= ($regular_filters['role'] == 'evaluator') ? 'selected' : '' ?>>موظف تقييمات</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>الإدارة</label>
                            <select name="regular_department" class="form-control">
                                <option value="">الكل</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($regular_filters['department'] == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['name_ar']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>الفرز</label>
                            <select name="regular_sort" class="form-control">
                                <option value="name_asc" <?= $regular_filters['sort'] == 'name_asc' ? 'selected' : '' ?>>الاسم (أ-ي)</option>
                                <option value="name_desc" <?= $regular_filters['sort'] == 'name_desc' ? 'selected' : '' ?>>الاسم (ي-أ)</option>
                                <option value="date_desc" <?= $regular_filters['sort'] == 'date_desc' ? 'selected' : '' ?>>الأحدث أولًا</option>
                                <option value="date_asc" <?= $regular_filters['sort'] == 'date_asc' ? 'selected' : '' ?>>الأقدم أولًا</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">تطبيق</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (empty($regular_users)): ?>
                <div class="text-center text-muted">لا توجد حسابات مستخدمين عاديين.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>البريد</th>
                                <th>الدور</th>
                                <th>الإدارة</th>
                                <th>مدير الإدارة</th>
                                <th>الرئيس المباشر</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regular_users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?= match($u['role']) {
                                        'manager' => 'مدير إدارة',
                                        'supervisor' => 'رئيس مباشر',
                                        'employee' => 'موظف',
                                        'evaluator' => 'موظف تقييمات',
                                        default => $u['role']
                                    } ?>
                                </td>
                                <td><?= $u['dept_name'] ?? '—' ?></td>
                                <td><?= $u['manager_name'] ?? '—' ?></td>
                                <td><?= $u['supervisor_name'] ?? '—' ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= $u['status'] === 'active' ? 'نشط' : 'معطل' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning me-1">تعديل</a>
                                    <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">حذف</a>
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

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>

<script>
function toggleFields(role) {
    const managerSupervisorFields = document.querySelector('.manager-supervisor-fields');
    const depField = document.querySelector('select[name="department_id"]');
    const supervisorField = document.querySelector('select[name="supervisor_id"]');
    
    if (role === 'employee' || role === 'evaluator' || role === 'manager' || role === 'supervisor') {
        depField.disabled = false;
        managerSupervisorFields.style.display = 'block';
        
        if (role === 'employee' || role === 'evaluator') {
            supervisorField.disabled = false;
        } else {
            supervisorField.disabled = true;
            supervisorField.value = '';
        }
    } else {
        depField.disabled = true;
        depField.value = '';
        managerSupervisorFields.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    if (roleSelect) {
        toggleFields(roleSelect.value);
    }
});

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