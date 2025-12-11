<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
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
require_once '../../vendor/autoload.php'; // تأكد من مسار autoload

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$manager_id = $_SESSION['user_id'];
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();

$reports = [];
if ($active_cycle) {
    $reports = $pdo->prepare("
        SELECT 
            u.name as employee_name,
            u.email,
            d.name_ar as dept_name,
            e.total_score,
            e.status as evaluation_status,
            e.updated_at
        FROM employee_evaluations e
        JOIN users u ON e.employee_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.manager_id = ? 
        AND e.cycle_id = ?
        AND e.evaluator_role IN ('supervisor', 'manager')
        ORDER BY u.name, e.evaluator_role
    ");
    $reports->execute([$manager_id, $active_cycle['id']]);
    $reports = $reports->fetchAll();
}

// إنشاء جدول Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('تقرير التقييمات');

// رؤوس الجدول
$headers = [
    'الموظف', 'البريد', 'الإدارة', 'الدرجة', 'الحالة', 'تاريخ التحديث'
];
$sheet->fromArray($headers, NULL, 'A1');

// بيانات التقارير
$row = 2;
foreach ($reports as $r) {
    $status = $r['evaluation_status'];
    $status_text = '';
    if ($status === 'draft') {
        $status_text = 'مسودة';
    } elseif ($status === 'submitted') {
        $status_text = 'بانتظار';
    } elseif ($status === 'approved') {
        $status_text = 'موافق';
    } elseif ($status === 'rejected') {
        $status_text = 'مرفوض';
    }

    $data = [
        $r['employee_name'],
        $r['email'],
        $r['dept_name'] ?? '—',
        $r['total_score'] ?? '—',
        $status_text,
        $r['updated_at'] ? date('Y-m-d H:i', strtotime($r['updated_at'])) : '—'
    ];
    $sheet->fromArray($data, NULL, 'A' . $row);
    $row++;
}

// تنسيق الجدول
$styleArray = [
    'font' => [
        'bold' => true,
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];

$sheet->getStyle('A1:F1')->applyFromArray($styleArray);

// تنزيل الملف
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="تقرير_التقييمات_' . $active_cycle['year'] . '.xlsx"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;