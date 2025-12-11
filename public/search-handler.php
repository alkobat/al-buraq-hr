<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    http_response_code(403);
    exit;
}
require_once '../app/core/db.php';

header('Content-Type: application/json; charset=utf-8');

// (مُعدَّل) استخدام $_GET بدلاً من $_POST لمطابقة طلب AJAX في search.js
if (!empty($_GET['q'])) { 
    $q = $_GET['q'];
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, d.name_ar as dept_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE (u.name LIKE ? OR u.email LIKE ?)
        ORDER BY u.name
        LIMIT 10
    ");
    $stmt->execute(["%$q%", "%$q%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $roleMap = [
        'admin' => 'مسؤول',
        'manager' => 'مدير إدارة',
        'supervisor' => 'رئيس مباشر',
        'evaluator' => 'موظف تقييمات',
        'employee' => 'موظف'
    ];
    
    $output = [];
    foreach ($results as $r) {
        $base_path = '';
        $user_role = $_SESSION['role'];
        
        // تحديد المسار الصحيح للتعديل بناءً على دور المستخدم الذي يبحث
        if ($user_role === 'admin') {
            $base_path = '../admin/users.php';
        } elseif ($user_role === 'evaluator') {
            $base_path = '../evaluator/users.php';
        } else {
            // الأدوار الأخرى (مدير/رئيس مباشر) لا يُفترض أن تعدّل المستخدمين، لكن لتمكين ميزة البحث الشامل، سنوجههم إلى صفحة تعديل المسؤول
            $base_path = '../admin/users.php'; 
        }
        
        // (مُعدَّل) إنشاء رابط التعديل
        $edit_url = $base_path . '?edit=' . $r['id'];

        $output[] = [
            'id' => (int)$r['id'],
            'name' => htmlspecialchars($r['name']),
            'email' => htmlspecialchars($r['email']),
            'role_ar' => $roleMap[$r['role']] ?? $r['role'],
            'role_key' => $r['role'],
            'dept' => $r['dept_name'] ?? null,
            'url' => $edit_url // (جديد) إضافة رابط التعديل المباشر
        ];
    }
    
    // (مُعدَّل) تضمين النتائج في مفتاح 'results' لمطابقة الشيفرة في search.js
    echo json_encode(['results' => $output]); 
} else {
    echo json_encode(['results' => []]);
}
?>