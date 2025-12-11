<?php
// === جمل use يجب أن تكون في الأعلى ===
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

// 1. استدعاء محمل المكتبات (Autoloader) - ضروري جداً
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    // في حال عدم وجود الملف، نوقف التنفيذ برسالة واضحة
    die('<div style="padding:20px; direction:rtl; font-family:tahoma;">خطأ: ملف <code>vendor/autoload.php</code> غير موجود.<br>يرجى التأكد من رفع مجلد vendor بالكامل.</div>');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// === CSRF: 1. إنشاء الرمز السري للجلسة ===
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];
// ==========================================

require_once '../../app/core/db.php';
require_once '../../app/core/Logger.php';
$logger = new Logger($pdo);

// (جديد) تهيئة متغيرات الصفحات (للاستخدام لاحقاً في الجدول الثاني)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // العدد الافتراضي
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// (جديد) تحديد وضع العرض: هل نحن في وضع "فورم" (تعديل/إضافة) أم "قائمة"؟
$is_form_mode = isset($_GET['edit']) || isset($_GET['add']);
$user_to_edit = null;
$next_user_id = null;
$prev_user_id = null;

// (جديد) --- معالجة استيراد المستخدمين من Excel ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_users'])) {
    // التحقق من CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: رمز CSRF غير صالح.";
    } elseif (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $file = $_FILES['excel_file']['tmp_name'];
        
        try {
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                throw new Exception("مكتبة PhpSpreadsheet غير محملة.");
            }

            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            $count = 0;
            $errors = 0;

            // تخطي الصف الأول (العناوين)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                // الترتيب المتوقع: الاسم، البريد، الدور، الوظيفة، اسم الإدارة، بريد المدير، بريد الرئيس المباشر
                $name = trim($row[0] ?? '');
                $email = trim($row[1] ?? '');
                $role = strtolower(trim($row[2] ?? 'employee'));
                $job_title = trim($row[3] ?? '');
                $dept_name = trim($row[4] ?? '');
                $manager_email = trim($row[5] ?? '');
                $supervisor_email = trim($row[6] ?? '');

                if (empty($name) || empty($email)) continue;

                // التحقق من عدم وجود البريد مسبقاً
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors++; 
                    continue; 
                }

                // محاولة إيجاد معرف الإدارة
                $dept_id = null;
                if ($dept_name) {
                    $stmt_dept = $pdo->prepare("SELECT id FROM departments WHERE name_ar = ? LIMIT 1");
                    $stmt_dept->execute([$dept_name]);
                    $dept_id = $stmt_dept->fetchColumn() ?: null;
                }

                // محاولة إيجاد معرف المدير
                $manager_id = null;
                if ($manager_email) {
                    $stmt_mgr = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $stmt_mgr->execute([$manager_email]);
                    $manager_id = $stmt_mgr->fetchColumn() ?: null;
                }

                // محاولة إيجاد معرف الرئيس المباشر
                $supervisor_id = null;
                if ($supervisor_email) {
                    $stmt_sup = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $stmt_sup->execute([$supervisor_email]);
                    $supervisor_id = $stmt_sup->fetchColumn() ?: null;
                }

                // تعيين كلمة مرور افتراضية (123456)
                $password = password_hash('123456', PASSWORD_DEFAULT);
                
                // التأكد من صحة الدور
                $valid_roles = ['admin', 'manager', 'supervisor', 'employee', 'evaluator'];
                if (!in_array($role, $valid_roles)) $role = 'employee';

                $stmt_insert = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, job_title, department_id, manager_id, supervisor_id, force_password_change)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt_insert->execute([$name, $email, $password, $role, $job_title, $dept_id, $manager_id, $supervisor_id]);
                $count++;
            }
            
            header("Location: users.php?msg=imported&count=$count&errors=$errors");
            exit;

        } catch (Exception $e) {
            $error = "حدث خطأ أثناء معالجة الملف: " . $e->getMessage();
        }
    } else {
        $error = "يرجى اختيار ملف صالح.";
    }
}

// --- معالجة التصدير إلى Excel (تم الإصلاح: ob_clean) ---
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // تنظيف مخزن الإخراج لمنع تلف الملف
    if (ob_get_length()) ob_clean();

    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        die('<div style="padding:20px; font-family:tahoma; direction:rtl;">خطأ: مكتبة PhpSpreadsheet غير مثبتة.</div>');
    }

    $role_map = [
        'admin' => 'مسؤول',
        'manager' => 'مدير إدارة',
        'supervisor' => 'رئيس مباشر',
        'evaluator' => 'موظف تقييمات',
        'employee' => 'موظف'
    ];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true); // اتجاه اليمين لليسار
    
    $excel_headers = ['الاسم', 'البريد الإلكتروني', 'الدور', 'الإدارة', 'مدير الإدارة', 'الرئيس المباشر', 'الوظيفة', 'الحالة'];
    $sheet->fromArray($excel_headers, NULL, 'A1');

    // استعلام شامل لجميع المستخدمين
    $stmt = $pdo->query("
        SELECT 
            u.name, u.email, u.role, u.job_title, u.status,
            d.name_ar AS dept_name, 
            m.name AS manager_name, 
            s.name AS supervisor_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN users m ON u.manager_id = m.id
        LEFT JOIN users s ON u.supervisor_id = s.id
        ORDER BY u.name
    ");
    $row = 2;
    while ($r = $stmt->fetch()) {
        $data = [
            $r['name'], 
            $r['email'], 
            $role_map[$r['role']] ?? $r['role'],
            $r['dept_name'] ?? '—', 
            $r['manager_name'] ?? '—', 
            $r['supervisor_name'] ?? '—', 
            $r['job_title'] ?? '—',
            $r['status'] === 'active' ? 'نشط' : 'معطل'
        ];

        $sheet->fromArray($data, NULL, 'A' . $row);
        $row++;
    }
    
    // إرسال الترويسات للمتصفح
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="system_users_report.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit; // إيقاف السكربت فوراً
}

// --- الحذف ---
// تم إزالة الحذف عبر GET - يجب استخدام POST مع CSRF
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: طلب حذف غير صالح (CSRF).";
    } else {
        unset($_SESSION['csrf_token']);
        
        $id = (int)$_POST['user_id'];
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        
        // تسجيل النشاط
        $logger->log('delete', "تم حذف بيانات المستخدم رقم: $id");
        
        // توليد CSRF token جديد
        try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
        $_SESSION['csrf_token'] = $new_csrf_token;
        
        header('Location: users.php?msg=deleted');
        exit;
    }
}

// --- التعديل ---
if ($_POST && isset($_POST['edit_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: طلب تعديل غير صالح أو منتهي الصلاحية (CSRF).";
        try { $csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
        $_SESSION['csrf_token'] = $csrf_token;
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

        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $id]);
        if ($stmt_check->fetch()) {
            $error = "البريد الإلكتروني مستخدم من قبل مستخدم آخر.";
        } else {
            // التحقق من كلمة المرور (تعديل: إضافة منطق تحديث كلمة المرور)
            $password_sql = "";
            $params = [$name, $email, $role, $dept, $manager, $supervisor, $job, $status];
            
            if (!empty($_POST['password'])) {
                $password_sql = ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $params[] = $id;

            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, email = ?, role = ?, department_id = ?, manager_id = ?, supervisor_id = ?, job_title = ?, status = ? $password_sql
                WHERE id = ?
            ");
            $stmt->execute($params);

// (جديد) تسجيل النشاط
$logger->log('update', "تم تعديل بيانات المستخدم رقم: $id");
////////////////////
            
            try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
            $_SESSION['csrf_token'] = $new_csrf_token;
            
            // (تعديل) إعادة التوجيه لصفحة التعديل نفسها
            header("Location: users.php?edit=$id&msg=edited");
            exit;
        }
    }
}

// --- الإضافة ---
if ($_POST && isset($_POST['add_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: طلب إضافة غير صالح أو منتهي الصلاحية (CSRF).";
        try { $csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
        $_SESSION['csrf_token'] = $csrf_token;
    } else {
        unset($_SESSION['csrf_token']); 

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
          
// (جديد) تسجيل النشاط
$logger->log('create', "تمت إضافة مستخدم جديد: $name ($email)");
/////////////
            try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
            $_SESSION['csrf_token'] = $new_csrf_token;
// === (جديد) إرسال بريد الترحيب ===
require_once '../../app/core/Mailer.php';
$settings = $pdo->query("SELECT value FROM system_settings WHERE `key`='auto_send_user'")->fetchColumn();
if ($settings == '1') {
    $mailer = new Mailer($pdo);
    // نرسل كلمة المرور الأصلية (التي تم توليدها أو كتابتها) وليس المشفرة
    $plain_password = !empty($_POST['password']) ? $_POST['password'] : $password; 
    $mailer->sendEmail($email, $name, 'new_user', [
        'name' => $name,
        'email' => $email,
        'password' => $plain_password
    ]);
}
// =================================
            header('Location: users.php?msg=added');
            exit;
        }
    }
}

// --- جلب بيانات المستخدم للتعديل ---
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $user_to_edit = $stmt->fetch();
    
    // (جديد) جلب السابق والتالي للتنقل
    if ($user_to_edit) {
        $stmt_next = $pdo->prepare("SELECT id FROM users WHERE name > ? ORDER BY name ASC LIMIT 1");
        $stmt_next->execute([$user_to_edit['name']]);
        $next_user_id = $stmt_next->fetchColumn();

        $stmt_prev = $pdo->prepare("SELECT id FROM users WHERE name < ? ORDER BY name DESC LIMIT 1");
        $stmt_prev->execute([$user_to_edit['name']]);
        $prev_user_id = $stmt_prev->fetchColumn();
    }
}

// جلب البيانات للقوائم المنسدلة
$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM users WHERE role IN ('manager', 'evaluator')")->fetchAll();
$supervisors = $pdo->query("SELECT id, name FROM users WHERE role IN ('supervisor', 'evaluator')")->fetchAll();

// === معالجة فلاتر المسؤولين/موظفي التقييمات ===
$admin_filters = [
    'role' => $_GET['admin_role'] ?? '',
    'status' => $_GET['admin_status'] ?? '',
    'sort' => $_GET['admin_sort'] ?? 'name_asc'
];

// === معالجة فلاتر المستخدمين العاديين ===
$regular_filters = [
    'role' => $_GET['regular_role'] ?? '',
    'department' => $_GET['regular_department'] ?? '',
    'status' => $_GET['regular_status'] ?? '',
    'sort' => $_GET['regular_sort'] ?? 'name_asc',
    'q' => $_GET['q'] ?? '' // (جديد) البحث بالاسم
];

// === جلب المسؤولين وموظفي التقييمات مع الفلاتر ===
$admin_sql = "
    SELECT u.*, d.name_ar as dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role IN ('admin', 'evaluator')
";
$admin_params = [];
if (!empty($admin_filters['role'])) {
    $admin_sql .= " AND u.role = ?";
    $admin_params[] = $admin_filters['role'];
}
if (!empty($admin_filters['status'])) {
    $admin_sql .= " AND u.status = ?";
    $admin_params[] = $admin_filters['status'];
}

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

// === جلب المستخدمين العاديين مع الفلاتر والصفحات ===
$regular_sql_base = "
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users m ON u.manager_id = m.id
    LEFT JOIN users s ON u.supervisor_id = s.id
    WHERE u.role IN ('manager', 'supervisor', 'employee')
";
$regular_params = [];

// (جديد) فلتر البحث
if (!empty($regular_filters['q'])) {
    $regular_sql_base .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.job_title LIKE ?)";
    $regular_params[] = "%{$regular_filters['q']}%";
    $regular_params[] = "%{$regular_filters['q']}%";
    $regular_params[] = "%{$regular_filters['q']}%";
}

if (!empty($regular_filters['role'])) {
    $regular_sql_base .= " AND u.role = ?";
    $regular_params[] = $regular_filters['role'];
}
if (!empty($regular_filters['department'])) {
    $regular_sql_base .= " AND u.department_id = ?";
    $regular_params[] = $regular_filters['department'];
}
if (!empty($regular_filters['status'])) {
    $regular_sql_base .= " AND u.status = ?";
    $regular_params[] = $regular_filters['status'];
}

// (جديد) حساب العدد الكلي للصفحات
$count_stmt = $pdo->prepare("SELECT COUNT(*) " . $regular_sql_base);
$count_stmt->execute($regular_params);
$total_regular_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_regular_users / $limit);

$regular_orderBy = $sortOptions[$regular_filters['sort']] ?? 'u.name ASC';
$regular_sql = "
    SELECT u.*, d.name_ar as dept_name, 
           m.name as manager_name, 
           s.name as supervisor_name
    " . $regular_sql_base . " 
    ORDER BY $regular_orderBy 
    LIMIT $limit OFFSET $offset
";

$regular_stmt = $pdo->prepare($regular_sql);
$regular_stmt->execute($regular_params);
$regular_users = $regular_stmt->fetchAll();

$roles = ['admin', 'manager', 'supervisor', 'employee', 'evaluator'];

// مصفوفة تعريب الأدوار
$role_map = [
    'admin' => 'مسؤول',
    'manager' => 'مدير إدارة',
    'supervisor' => 'رئيس مباشر',
    'employee' => 'موظف',
    'evaluator' => 'موظف تقييمات'
];
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

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success">تم إضافة المستخدم بنجاح.</div>
        <?php elseif ($_GET['msg'] === 'edited'): ?>
            <div class="alert alert-info">تم تحديث بيانات المستخدم.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-warning">تم حذف المستخدم.</div>
        <?php elseif ($_GET['msg'] === 'imported'): ?>
            <div class="alert alert-success">
                تم استيراد <strong><?= htmlspecialchars($_GET['count'] ?? 0) ?></strong> مستخدم بنجاح.
                <?php if (!empty($_GET['errors'])): ?>
                    <br>تم تخطي <strong><?= htmlspecialchars($_GET['errors']) ?></strong> مستخدم لوجود البريد الإلكتروني مسبقاً.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($is_form_mode): ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>
                <i class="fas fa-<?= $user_to_edit ? 'edit' : 'user-plus' ?>"></i> 
                <?= $user_to_edit ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد' ?>
            </h3>
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right"></i> عودة للقائمة
            </a>
        </div>

        <div class="card mb-4 border-<?= $user_to_edit ? 'warning' : 'primary' ?> shadow-sm">
            <div class="card-header bg-<?= $user_to_edit ? 'warning' : 'primary' ?> text-white d-flex justify-content-between align-items-center">
                <span>
                    <?= $user_to_edit ? 'المستخدم: ' . htmlspecialchars($user_to_edit['name']) : 'بيانات المستخدم الجديد' ?>
                </span>
                
                <?php if ($user_to_edit): ?>
                <div class="btn-group" dir="ltr">
                    <?php if ($next_user_id): ?>
                        <a href="?edit=<?= $next_user_id ?>&page=<?= $page ?>&limit=<?= $limit ?>" class="btn btn-sm btn-light" title="التالي"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-light disabled"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                    
                    <?php if ($prev_user_id): ?>
                        <a href="?edit=<?= $prev_user_id ?>&page=<?= $page ?>&limit=<?= $limit ?>" class="btn btn-sm btn-light" title="السابق"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-light disabled"><i class="fas fa-chevron-left"></i></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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

    <?php else: ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-users"></i> إدارة المستخدمين</h3>
            <div>
                <a href="users.php?add=1" class="btn btn-primary me-2">
                    <i class="fas fa-user-plus"></i> إضافة مستخدم
                </a>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-file-import"></i> استيراد
                </button>
                <a href="?export=excel" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> تصدير الكل
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-shield"></i> المسؤولون وموظفو التقييمات</span>
                <span class="badge bg-light text-dark"><?= count($admin_evaluators) ?></span>
            </div>
            <div class="card-body">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="fas fa-filter"></i> تصفية
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-3">
                                <label>الدور</label>
                                <select name="admin_role" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="admin" <?= ($admin_filters['role'] == 'admin') ? 'selected' : '' ?>>مسؤول</option>
                                    <option value="evaluator" <?= ($admin_filters['role'] == 'evaluator') ? 'selected' : '' ?>>موظف تقييمات</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>الحالة</label>
                                <select name="admin_status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="active" <?= ($admin_filters['status'] == 'active') ? 'selected' : '' ?>>نشط</option>
                                    <option value="inactive" <?= ($admin_filters['status'] == 'inactive') ? 'selected' : '' ?>>معطل</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>الفرز</label>
                                <select name="admin_sort" class="form-control">
                                    <option value="name_asc" <?= ($admin_filters['sort'] == 'name_asc') ? 'selected' : '' ?>>الاسم (أ-ي)</option>
                                    <option value="name_desc" <?= ($admin_filters['sort'] == 'name_desc' ? 'selected' : '') ?>>الاسم (ي-أ)</option>
                                    <option value="date_desc" <?= ($admin_filters['sort'] == 'date_desc' ? 'selected' : '') ?>>الأحدث أولًا</option>
                                    <option value="date_asc" <?= ($admin_filters['sort'] == 'date_asc' ? 'selected' : '') ?>>الأقدم أولًا</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">تطبيق</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($admin_evaluators)): ?>
                    <div class="text-center text-muted">لا توجد نتائج مطابقة.</div>
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
                                <?php foreach ($admin_evaluators as $u): ?>
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
 <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning me-1"><i class="fas fa-edit"></i></a>
                                        
                                        <a href="history.php?employee_id=<?= $u['id'] ?>" class="btn btn-sm btn-info text-white me-1" title="سجل التقييمات">
                                            <i class="fas fa-history"></i>
                                        </a>

                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
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
                <span><i class="fas fa-users"></i> المستخدمون العاديون</span>
                <span class="badge bg-light text-dark">عدد النتائج: <?= $total_regular_users ?></span>
            </div>
            <div class="card-body">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="fas fa-filter"></i> تصفية وبحث
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-3">
                                <label>بحث (اسم/بريد)</label>
                                <input type="text" name="q" class="form-control" placeholder="اكتب للبحث..." value="<?= htmlspecialchars($regular_filters['q']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label>الدور</label>
                                <select name="regular_role" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="manager" <?= ($regular_filters['role'] == 'manager') ? 'selected' : '' ?>>مدير إدارة</option>
                                    <option value="supervisor" <?= ($regular_filters['role'] == 'supervisor') ? 'selected' : '' ?>>رئيس مباشر</option>
                                    <option value="employee" <?= ($regular_filters['role'] == 'employee') ? 'selected' : '' ?>>موظف</option>
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
                            <div class="col-md-2">
                                <label>العدد في الصفحة</label>
                                <select name="limit" class="form-control">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">بحث وتطبيق</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($regular_users)): ?>
                    <div class="text-center text-muted">لا توجد نتائج مطابقة.</div>
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
                                    <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                                    <td><small>
                                        <?= match($u['role']) {
                                            'manager' => 'مدير إدارة',
                                            'supervisor' => 'رئيس مباشر',
                                            'employee' => 'موظف',
                                            default => $u['role']
                                        } ?>
                                        </small>
                                    </td>
                                    <td><small><?= $u['dept_name'] ?? '—' ?></small></td>
                                    <td><?= $u['manager_name'] ?? '—' ?></td>
                                    <td><small><?= $u['supervisor_name'] ?? '—' ?></small></td>
                                    <td>
                                        <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $u['status'] === 'active' ? 'نشط' : 'معطل' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning me-1"><i class="fas fa-edit"></i></a>
                                        
                                        <a href="history.php?employee_id=<?= $u['id'] ?>" class="btn btn-sm btn-info text-white me-1" title="سجل التقييمات">
                                            <i class="fas fa-history"></i>
                                        </a>

                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center mt-3">
                        <small class="text-muted">عرض الصفحة <?= $page ?> من <?= $total_pages ?></small>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&q=<?= urlencode($regular_filters['q']) ?>&regular_role=<?= $regular_filters['role'] ?>&regular_department=<?= $regular_filters['department'] ?>">السابق</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&q=<?= urlencode($regular_filters['q']) ?>&regular_role=<?= $regular_filters['role'] ?>&regular_department=<?= $regular_filters['department'] ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&q=<?= urlencode($regular_filters['q']) ?>&regular_role=<?= $regular_filters['role'] ?>&regular_department=<?= $regular_filters['department'] ?>">التالي</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?> </main>

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>

<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="importModalLabel">استيراد موظفين</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info">
                <small>
                    يرجى رفع ملف Excel (.xlsx) يحتوي على الأعمدة التالية بالترتيب:<br>
                    <strong>الاسم | البريد | الدور | الوظيفة | الإدارة | بريد المدير | بريد الرئيس المباشر</strong>
                </small>
            </div>
            <div class="mb-3">
                <label for="excel_file" class="form-label">اختر الملف</label>
                <input class="form-control" type="file" id="excel_file" name="excel_file" accept=".xlsx, .xls" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button type="submit" name="import_users" class="btn btn-primary">استيراد</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

// وظيفة checkInternet() يتم استدعاؤها من search.js
</script>

</body>
</html>