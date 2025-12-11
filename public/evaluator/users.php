<?php
// === جمل use يجب أن تكون في الأعلى ===
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

// 1. استدعاء محمل المكتبات
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    die('<div style="padding:20px; direction:rtl; font-family:tahoma;">خطأ: ملف <code>vendor/autoload.php</code> غير موجود.</div>');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header('Location: ../login.php');
    exit;
}

// === CSRF ===
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';
require_once '../../app/core/Logger.php';
$logger = new Logger($pdo);
// (جديد) استدعاء كلاس المراسلة
require_once '../../app/core/Mailer.php';

// إعدادات البحث والصفحات
$q = trim($_GET['q'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// فلاتر
$role_filter = $_GET['role_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$department_filter = $_GET['department_filter'] ?? '';

// شرط WHERE (يستثني المسؤولين دائماً)
$where_clauses = ["u.role != 'admin'"];
$params = [];

if ($q) {
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR u.job_title LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($role_filter) {
    $where_clauses[] = "u.role = ?";
    $params[] = $role_filter;
}
if ($status_filter) {
    $where_clauses[] = "u.status = ?";
    $params[] = $status_filter;
}
if ($department_filter) {
    $where_clauses[] = "u.department_id = ?";
    $params[] = $department_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// --- الاستيراد ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_users'])) {
    // (نفس كود الاستيراد السابق تماماً)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: رمز CSRF غير صالح.";
    } elseif (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $file = $_FILES['excel_file']['tmp_name'];
        try {
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) { throw new Exception("مكتبة Excel غير محملة."); }
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            $count = 0; $errors = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $name = trim($row[0] ?? ''); $email = trim($row[1] ?? ''); $role = strtolower(trim($row[2] ?? 'employee'));
                $job = trim($row[3] ?? ''); $dept_name = trim($row[4] ?? ''); $mgr_email = trim($row[5] ?? ''); $sup_email = trim($row[6] ?? '');

                if ($role === 'admin') continue;
                if (empty($name) || empty($email)) continue;

                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) { $errors++; continue; }

                $dept_id = null; if($dept_name){ $s=$pdo->prepare("SELECT id FROM departments WHERE name_ar=?"); $s->execute([$dept_name]); $dept_id=$s->fetchColumn()?:null; }
                $mgr_id = null; if($mgr_email){ $s=$pdo->prepare("SELECT id FROM users WHERE email=?"); $s->execute([$mgr_email]); $mgr_id=$s->fetchColumn()?:null; }
                $sup_id = null; if($sup_email){ $s=$pdo->prepare("SELECT id FROM users WHERE email=?"); $s->execute([$sup_email]); $sup_id=$s->fetchColumn()?:null; }

                $pass = password_hash('123456', PASSWORD_DEFAULT);
                $stmt_insert = $pdo->prepare("INSERT INTO users (name, email, password, role, job_title, department_id, manager_id, supervisor_id, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt_insert->execute([$name, $email, $pass, $role, $job, $dept_id, $mgr_id, $sup_id]);
                $count++;
            }
            header("Location: users.php?msg=imported&count=$count&errors=$errors"); exit;
        } catch (Exception $e) { $error = "خطأ: " . $e->getMessage(); }
    } else { $error = "يرجى اختيار ملف صالح."; }
}

// --- التصدير ---
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // (نفس كود التصدير السابق تماماً)
    if (ob_get_length()) ob_clean();
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) { die('مكتبة Excel غير موجودة.'); }
    
    $role_map = ['manager'=>'مدير إدارة','supervisor'=>'رئيس مباشر','evaluator'=>'موظف تقييمات','employee'=>'موظف'];
    $spreadsheet = new Spreadsheet(); 
    $sheet = $spreadsheet->getActiveSheet(); 
    $sheet->setRightToLeft(true);
    $sheet->fromArray(['الاسم', 'البريد', 'الدور', 'الإدارة', 'مدير الإدارة', 'الرئيس المباشر', 'الوظيفة', 'الحالة'], NULL, 'A1');
    
    $sql_ex = "
        SELECT 
            u.name, u.email, u.role, u.job_title, u.status,
            d.name_ar AS dept_name, 
            m.name AS manager_name, 
            s.name AS supervisor_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN users m ON u.manager_id = m.id
        LEFT JOIN users s ON u.supervisor_id = s.id
        WHERE $where_sql
        ORDER BY u.name ASC
    ";
    $stmt = $pdo->prepare($sql_ex);
    $stmt->execute($params);
    $row = 2;
    while ($r = $stmt->fetch()) {
        $sheet->fromArray([
            $r['name'], 
            $r['email'], 
            $role_map[$r['role']] ?? $r['role'], 
            $r['dept_name'] ?? '—', 
            $r['manager_name'] ?? '—', 
            $r['supervisor_name'] ?? '—', 
            $r['job_title'] ?? '—', 
            $r['status'] === 'active' ? 'نشط' : 'معطل'
        ], NULL, 'A'.$row);
        $row++;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="users_report.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet); $writer->save('php://output'); exit;
}

// --- الحذف ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?"); 
    $stmt->execute([$_GET['delete']]);

// (جديد) تسجيل النشاط
$logger->log('delete', "تم حذف بيانات المستخدم رقم: $id");
////////////
    $role = $stmt->fetchColumn();
    if ($role !== 'admin') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete']]);
        header('Location: users.php?msg=deleted');
    } else { 
        header('Location: users.php?msg=error_admin'); 
    }
    exit;
}

// --- التعديل ---
if ($_POST && isset($_POST['edit_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) { 
        $error = "خطأ أمني: طلب غير صالح."; 
    } else {
        unset($_SESSION['csrf_token']);
        $id = $_POST['user_id']; 
        $name = trim($_POST['name']); 
        $email = trim($_POST['email']);
        $role = $_POST['role']; 
        $dept = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $manager = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        $supervisor = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
        $job = trim($_POST['job_title']);
        $status = $_POST['status'];

        if ($role === 'admin') { 
            $error = "لا يمكنك تعيين هذا المستخدم كمسؤول."; 
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check->execute([$email, $id]);
            if ($stmt_check->fetch()) { 
                $error = "البريد الإلكتروني مستخدم من قبل شخص آخر."; 
            } else {
                $password_sql = "";
                $params_up = [$name, $email, $role, $dept, $manager, $supervisor, $job, $status];
                
                if (!empty($_POST['password'])) {
                    $password_sql = ", password = ?";
                    $params_up[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                $params_up[] = $id;

                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, department_id = ?, manager_id = ?, supervisor_id = ?, job_title = ?, status = ? $password_sql WHERE id = ? AND role != 'admin'");
                $stmt->execute($params_up);

// (جديد) تسجيل النشاط
$logger->log('update', "تم تعديل بيانات المستخدم رقم: $id");
////////////////
                
                try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
                $_SESSION['csrf_token'] = $new_csrf_token;
                
                $qs = http_build_query(['edit' => $id, 'page' => $page, 'limit' => $limit, 'q' => $q, 'msg' => 'edited']);
                header("Location: users.php?$qs");
                exit;
            }
        }
    }
}

// --- الإضافة (تم التعديل لإضافة البريد التلقائي) ---
if ($_POST && isset($_POST['add_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) { 
        $error = "خطأ أمني: طلب غير صالح."; 
    } else {
        unset($_SESSION['csrf_token']);
        $name = trim($_POST['name']); 
        $email = trim($_POST['email']);
        $role = $_POST['role']; 
        $dept = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $manager = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        $supervisor = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
        $job = trim($_POST['job_title']);
        
        if ($role === 'admin') { 
            $error = "لا يمكنك إنشاء حساب مسؤول."; 
        } else {
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

// (جديد) تسجيل النشاط
$logger->log('create', "تمت إضافة مستخدم جديد: $name ($email)");
                // === (جديد) إرسال بريد الترحيب ===
                $settings = $pdo->query("SELECT value FROM system_settings WHERE `key`='auto_send_user'")->fetchColumn();
                if ($settings == '1') {
                    $mailer = new Mailer($pdo);
                    $plain_password = !empty($_POST['password']) ? $_POST['password'] : $password; 
                    $mailer->sendEmail($email, $name, 'new_user', [
                        'name' => $name,
                        'email' => $email,
                        'password' => $plain_password
                    ]);
                }
                // =================================

                try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
                $_SESSION['csrf_token'] = $new_csrf_token;

                header('Location: users.php?msg=added');
                exit;
            }
        }
    }
}

// --- تحديد وضع العرض والبيانات ---
$user_to_edit = null; 
$next_user_id = null; 
$prev_user_id = null;
$show_form = isset($_GET['edit']) || isset($_GET['add']);

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$_GET['edit']]);
    $user_to_edit = $stmt->fetch();
    
    if (!$user_to_edit) { header('Location: users.php?msg=error_admin'); exit; }

    $s_next = $pdo->prepare("SELECT id FROM users WHERE role != 'admin' AND name > ? ORDER BY name ASC LIMIT 1");
    $s_next->execute([$user_to_edit['name']]);
    $next_user_id = $s_next->fetchColumn();

    $s_prev = $pdo->prepare("SELECT id FROM users WHERE role != 'admin' AND name < ? ORDER BY name DESC LIMIT 1");
    $s_prev->execute([$user_to_edit['name']]);
    $prev_user_id = $s_prev->fetchColumn();
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM users WHERE role IN ('manager', 'evaluator')")->fetchAll();
$supervisors = $pdo->query("SELECT id, name FROM users WHERE role IN ('supervisor', 'evaluator')")->fetchAll();

// --- الاستعلام الرئيسي للقائمة ---
$users_list = [];
$total_rows = 0;
$total_pages = 0;

if (!$show_form) {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    $sql = "
        SELECT 
            u.*, 
            d.name_ar as dept_name, 
            m.name as manager_name, 
            s.name as supervisor_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN users m ON u.manager_id = m.id
        LEFT JOIN users s ON u.supervisor_id = s.id
        WHERE $where_sql
        ORDER BY u.name ASC
        LIMIT $limit OFFSET $offset
    ";
    $stmt_users = $pdo->prepare($sql);
    $stmt_users->execute($params);
    $users_list = $stmt_users->fetchAll();
}

$role_map = [
    'manager' => 'مدير إدارة',
    'supervisor' => 'رئيس مباشر',
    'evaluator' => 'موظف تقييمات',
    'employee' => 'موظف'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة المستخدمين - موظف التقييمات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script src="../assets/js/search.js" defer></script>
</head>
<body class="admin-dashboard">

<?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info">
            <?= $_GET['msg'] == 'imported' ? 'تم استيراد ' . ($_GET['count']??0) . ' مستخدم.' : '' ?>
            <?= $_GET['msg'] == 'added' ? 'تمت الإضافة بنجاح (وإرسال البريد إذا كان مفعلاً).' : '' ?>
            <?= $_GET['msg'] == 'edited' ? 'تم تحديث بيانات المستخدم.' : '' ?>
            <?= $_GET['msg'] == 'deleted' ? 'تم حذف المستخدم.' : '' ?>
            <?= $_GET['msg'] == 'error_admin' ? 'خطأ: لا تملك صلاحية على هذا الحساب.' : '' ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($show_form): ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>
                <i class="fas fa-<?= $user_to_edit ? 'edit' : 'user-plus' ?>"></i> 
                <?= $user_to_edit ? 'تعديل مستخدم' : 'إضافة جديد' ?>
            </h3>
            <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right"></i> عودة للقائمة</a>
        </div>

        <div class="card mb-4 border-<?= $user_to_edit ? 'warning' : 'primary' ?> shadow-sm">
            <div class="card-header bg-<?= $user_to_edit ? 'warning' : 'primary' ?> text-white d-flex justify-content-between align-items-center">
                <span><?= $user_to_edit ? 'المستخدم: ' . htmlspecialchars($user_to_edit['name']) : 'بيانات المستخدم' ?></span>
                <?php if ($user_to_edit): ?>
                <div class="btn-group" dir="ltr">
                    <?php if ($next_user_id): ?><a href="?edit=<?= $next_user_id ?>&page=<?= $page ?>&limit=<?= $limit ?>" class="btn btn-sm btn-light"><i class="fas fa-chevron-right"></i></a><?php else: ?><button class="btn btn-sm btn-light disabled"><i class="fas fa-chevron-right"></i></button><?php endif; ?>
                    <a href="users.php" class="btn btn-sm btn-light"><i class="fas fa-list"></i></a>
                    <?php if ($prev_user_id): ?><a href="?edit=<?= $prev_user_id ?>&page=<?= $page ?>&limit=<?= $limit ?>" class="btn btn-sm btn-light"><i class="fas fa-chevron-left"></i></a><?php else: ?><button class="btn btn-sm btn-light disabled"><i class="fas fa-chevron-left"></i></button><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <?php if ($user_to_edit): ?><input type="hidden" name="user_id" value="<?= $user_to_edit['id'] ?>"><?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>الاسم الكامل <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="<?= $user_to_edit['name']??'' ?>" required></div>
                        <div class="col-md-6 mb-3"><label>البريد الإلكتروني <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" value="<?= $user_to_edit['email']??'' ?>" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label>الدور <span class="text-danger">*</span></label>
                            <select name="role" class="form-control" required onchange="toggleFields(this.value)">
                                <option value="">اختر...</option>
                                <?php foreach(['manager'=>'مدير إدارة','supervisor'=>'رئيس مباشر','employee'=>'موظف','evaluator'=>'موظف تقييمات'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= ($user_to_edit && $user_to_edit['role'] == $k) ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"><label>الحالة</label><select name="status" class="form-control"><option value="active" <?= (!$user_to_edit||$user_to_edit['status']=='active')?'selected':'' ?>>نشط</option><option value="inactive" <?= ($user_to_edit&&$user_to_edit['status']=='inactive')?'selected':'' ?>>معطل</option></select></div>
                        <div class="col-md-3 mb-3"><label>الإدارة</label><select name="department_id" class="form-control dep-field"><option value="">بدون</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= ($user_to_edit&&$user_to_edit['department_id']==$d['id'])?'selected':'' ?>><?= htmlspecialchars($d['name_ar']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3 mb-3"><label>كلمة المرور</label><input type="password" name="password" class="form-control" placeholder="اترك فارغًا للإبقاء"></div>
                    </div>
                    <div class="row manager-supervisor-fields" style="display:none;">
                        <div class="col-md-6 mb-3"><label>مدير الإدارة</label><select name="manager_id" class="form-control"><option value="">بدون</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" <?= ($user_to_edit&&$user_to_edit['manager_id']==$m['id'])?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6 mb-3"><label>الرئيس المباشر</label><select name="supervisor_id" class="form-control"><option value="">بدون</option><?php foreach ($supervisors as $s): ?><option value="<?= $s['id'] ?>" <?= ($user_to_edit&&$user_to_edit['supervisor_id']==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3"><label>الوظيفة</label><input type="text" name="job_title" class="form-control" value="<?= $user_to_edit['job_title']??'' ?>"></div>
                    <button type="submit" name="<?= $user_to_edit?'edit_user':'add_user' ?>" class="btn btn-<?= $user_to_edit?'warning':'success' ?>"><?= $user_to_edit?'حفظ التعديلات':'إضافة' ?></button>
                </form>
            </div>
        </div>

    <?php else: ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-users"></i> إدارة المستخدمين</h3>
            <div>
                <a href="users.php?add=1" class="btn btn-primary me-2"><i class="fas fa-user-plus"></i> إضافة مستخدم</a>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">استيراد</button>
                <a href="?export=excel&q=<?= urlencode($q) ?>" class="btn btn-success">تصدير الكل</a>
            </div>
        </div>

        <div class="card mb-3 bg-light border-0 shadow-sm">
            <div class="card-body p-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-3"><input type="text" name="q" class="form-control" placeholder="بحث بالاسم، البريد..." value="<?= htmlspecialchars($q) ?>"></div>
                    <div class="col-md-2"><select name="role_filter" class="form-control"><option value="">الدور: الكل</option><option value="manager"<?= $role_filter=='manager'?'selected':''?>>مدير إدارة</option><option value="supervisor"<?= $role_filter=='supervisor'?'selected':''?>>رئيس مباشر</option><option value="employee"<?= $role_filter=='employee'?'selected':''?>>موظف</option><option value="evaluator"<?= $role_filter=='evaluator'?'selected':''?>>موظف تقييمات</option></select></div>
                    <div class="col-md-3"><select name="department_filter" class="form-control"><option value="">الإدارة: الكل</option><?php foreach($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $department_filter==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name_ar']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><select name="status_filter" class="form-control"><option value="">الحالة: الكل</option><option value="active" <?= $status_filter=='active'?'selected':''?>>نشط</option><option value="inactive" <?= $status_filter=='inactive'?'selected':''?>>معطل</option></select></div>
                    <div class="col-md-1"><select name="limit" class="form-control" onchange="this.form.submit()"><option value="10"<?= $limit==10?'selected':''?>>10</option><option value="20"<?= $limit==20?'selected':''?>>20</option><option value="50"<?= $limit==50?'selected':''?>>50</option><option value="100"<?= $limit==100?'selected':''?>>100</option></select></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">بحث</button></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white d-flex justify-content-between">
                <span>قائمة المستخدمين</span>
                <span class="badge bg-light text-dark">العدد: <?= $total_rows ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light"><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الإدارة</th><th>مدير الإدارة</th><th>الرئيس المباشر</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                        <tbody>
                            <?php if(empty($users_list)): ?><tr><td colspan="8" class="text-center py-4">لا توجد نتائج.</td></tr><?php else: ?>
                            <?php foreach ($users_list as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= $role_map[$u['role']] ?? $u['role'] ?></td>
                                <td><?= htmlspecialchars($u['dept_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($u['manager_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($u['supervisor_name'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $u['status']=='active'?'success':'secondary' ?>"><?= $u['status']=='active'?'نشط':'معطل' ?></span></td>
                                <td>
                                    <div class="btn-group">
                                       <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning me-1"><i class="fas fa-edit"></i></a>
                                        
                                        <a href="history.php?employee_id=<?= $u['id'] ?>" class="btn btn-sm btn-info text-white me-1" title="سجل التقييمات">
                                            <i class="fas fa-history"></i>
                                        </a>

                                        <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white d-flex justify-content-center">
                    <nav><ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>&role_filter=<?= $role_filter ?>&status_filter=<?= $status_filter ?>&department_filter=<?= $department_filter ?>">السابق</a></li>
                        <?php for($i=1; $i<=$total_pages; $i++): ?><li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>&role_filter=<?= $role_filter ?>&status_filter=<?= $status_filter ?>&department_filter=<?= $department_filter ?>"><?= $i ?></a></li><?php endfor; ?>
                        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>&role_filter=<?= $role_filter ?>&status_filter=<?= $status_filter ?>&department_filter=<?= $department_filter ?>">التالي</a></li>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>

<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <div class="modal-header"><h5 class="modal-title">استيراد موظفين</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="alert alert-info"><small>Excel: <strong>الاسم | البريد | الدور | الوظيفة | الإدارة | بريد المدير | بريد الرئيس</strong></small></div>
            <input class="form-control" type="file" name="excel_file" accept=".xlsx, .xls" required>
          </div>
          <div class="modal-footer"><button type="submit" name="import_users" class="btn btn-primary">استيراد</button></div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFields(role) {
    const fields = document.querySelector('.manager-supervisor-fields');
    if (fields) fields.style.display = (role === 'employee' || role === 'evaluator' || role === 'manager' || role === 'supervisor') ? 'flex' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    if (roleSelect) toggleFields(roleSelect.value);
});
</script>
</body>
</html>