<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit; }
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
// ==========================================
// (إصلاح) استدعاء ملف التحميل التلقائي للمكتبات
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    die('<div style="padding:20px; direction:rtl;">خطأ: ملف المكتبات غير موجود (vendor/autoload.php). تأكد من تثبيت المكتبات عبر Composer.</div>');
}
// ==========================================

require_once '../../app/core/db.php';
require_once '../../app/core/Mailer.php';

$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = $_POST['target'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    // إعداد المرسل
    $mailer = new Mailer($pdo);
    $count = 0;

    $sql = "SELECT id, name, email, job_title FROM users WHERE status='active' AND role != 'admin'";
    $params = [];

    // فلترة الجمهور
    if ($target === 'department') {
        $dept_id = $_POST['department_id'];
        $sql .= " AND department_id = ?";
        $params[] = $dept_id;
    } elseif ($target === 'completed_eval') {
        $cycle = $pdo->query("SELECT id FROM evaluation_cycles WHERE status='active' LIMIT 1")->fetch();
        if ($cycle) {
            $sql .= " AND id IN (SELECT employee_id FROM employee_evaluations WHERE cycle_id = ? AND status = 'approved')";
            $params[] = $cycle['id'];
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        // استبدال المتغيرات

// المصفوفة الأولى: الكلمات التي نريد استبدالها
$search  = ['{name}', '{job}', '{email}'];

// المصفوفة الثانية: القيم التي سنضعها مكانها
$replace = [$user['name'], $user['job_title'], $user['email']];

// تنفيذ الاستبدال
$personal_msg = str_replace($search, $replace, $message);


        if ($mailer->sendCustomEmail($user['email'], $user['name'], $subject, $personal_msg)) {
            $count++;
        }
    }
    
    if ($count > 0) {
        $msg = "تم إرسال البريد إلى $count مستخدم بنجاح.";
    } else {
        $error = "لم يتم إرسال أي بريد. قد لا يوجد مستخدمين مطابقين أو حدث خطأ في الاتصال.";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الإرسال الجماعي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">
<?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>
<main class="admin-main-content">
    <h3><i class="fas fa-mail-bulk"></i> الإرسال الجماعي</h3>
    <hr>
    <?php if ($msg) echo "<div class='alert alert-success'>$msg</div>"; ?>
    <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>الجمهور المستهدف</label>
                    <select name="target" class="form-select" onchange="toggleDept(this.value)">
                        <option value="all">جميع المستخدمين</option>
                        <option value="department">إدارة محددة</option>
                        <option value="completed_eval">من أتموا التقييم (المعتمدين)</option>
                    </select>
                </div>
                <div class="mb-3" id="dept_div" style="display:none;">
                    <label>اختر الإدارة</label>
                    <select name="department_id" class="form-select">
                        <?php foreach($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= $d['name_ar'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>عنوان الرسالة</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>نص الرسالة</label>
                    <textarea name="message" class="form-control" rows="6" required></textarea>
                    <small class="text-muted">
					يمكنك استخدام {name} لذكر اسم الموظف.
					يمكنك استخدام {job} لذكر الوظيفة.
					</small>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> إرسال</button>
            </form>
        </div>
    </div>
</main>
<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script>
function toggleDept(val) {
    document.getElementById('dept_div').style.display = (val === 'department') ? 'block' : 'none';
}
</script>
<script src="../assets/js/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>