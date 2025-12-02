<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    http_response_code(403);
    exit;
}
require_once '../app/core/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!empty($_POST['q'])) {
    $q = $_POST['q'];
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
        $output[] = [
            'id' => (int)$r['id'],
            'name' => htmlspecialchars($r['name']),
            'email' => htmlspecialchars($r['email']),
            'role_ar' => $roleMap[$r['role']] ?? $r['role'],
			# 'role_key' => $r['role'],
            'role_key' => $_SESSION['role'],
            'dept' => $r['dept_name'] ?? null
        ];
    }
    
    echo json_encode($output);
} else {
    echo json_encode([]);
}
?>