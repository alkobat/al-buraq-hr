<?php
// تحقق من الصلاحيات (كما في dashboard.php)

$action = $_GET['action'] ?? '';
if ($action === 'delete' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['id']]);
    header('Location: users.php?msg=deleted');
    exit;
}

if ($_POST && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $dept = $_POST['department_id'] ?: null;
    $manager = $_POST['manager_id'] ?: null;
    $supervisor = $_POST['supervisor_id'] ?: null;
    $job = $_POST['job_title'];
    
    // توليد كلمة مرور عشوائية إذا لم تُدخل
    $password = $_POST['password'] ?: bin2hex(random_bytes(4));
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, department_id, manager_id, supervisor_id, job_title, force_password_change)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$name, $email, $hashed, $role, $dept, $manager, $supervisor, $job]);
    
    // إرسال بريد (سيُنفّذ لاحقًا)
    header('Location: users.php?msg=added');
    exit;
}

// جلب المستخدمين
$users = $pdo->query("
    SELECT u.*, d.name_ar as dept_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!-- واجهة HTML مشابهة مع جدول + نموذج إضافة -->
<!-- (سأدمجها في الملف المضغوط كاملاً) -->