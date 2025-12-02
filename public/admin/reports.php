<?php
// === جمل use يجب أن تكون في الأعلى ===
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

// تصدير Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="employee_evaluations.xlsx"');

    // تحقق من وجود المكتبة
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        die('<div style="padding:20px; font-family:tahoma; direction:rtl;">خطأ: مكتبة PhpSpreadsheet غير مثبتة.<br>يرجى تشغيل: <code>composer require phpoffice/phpspreadsheet</code></div>');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'الاسم');
    $sheet->setCellValue('B1', 'البريد');
    $sheet->setCellValue('C1', 'الإدارة');
    $sheet->setCellValue('D1', 'الدرجة');
    $sheet->setCellValue('E1', 'الحالة');

    $stmt = $pdo->query("
        SELECT u.name, u.email, d.name_ar, e.total_score, e.status
        FROM employee_evaluations e
        JOIN users u ON e.employee_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE e.status IN ('approved', 'rejected')
    ");
    $row = 2;
    while ($r = $stmt->fetch()) {
        $sheet->setCellValue('A' . $row, $r['name']);
        $sheet->setCellValue('B' . $row, $r['email']);
        $sheet->setCellValue('C' . $row, $r['name_ar'] ?? '—');
        $sheet->setCellValue('D' . $row, $r['total_score'] ?? '—');
        $sheet->setCellValue('E' . $row, $r['status'] === 'approved' ? 'موافق' : 'مرفوض');
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$reports = $pdo->query("
    SELECT u.name, u.email, d.name_ar as dept, e.total_score, e.status, c.year
    FROM employee_evaluations e
    JOIN users u ON e.employee_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    JOIN evaluation_cycles c ON e.cycle_id = c.id
    WHERE e.status IN ('approved', 'rejected')
    ORDER BY e.updated_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>التقارير</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>المسؤول</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="users.php"><i class="fas fa-users"></i> إدارة المستخدمين</a>
    <a href="departments.php"><i class="fas fa-building"></i> الإدارات</a>
    <a href="cycles.php"><i class="fas fa-calendar-alt"></i> دورات التقييم</a>
    <a href="evaluation-fields.php"><i class="fas fa-list"></i> مجالات التقييم</a>
    <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> التقارير</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h3>
        <a href="?export=excel" class="btn btn-success">
            <i class="fas fa-file-excel"></i> تصدير Excel
        </a>
    </div>
    <hr>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>البريد</th>
                            <th>الإدارة</th>
                            <th>السنة</th>
                            <th>الدرجة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><?= $r['dept'] ?? '—' ?></td>
                            <td><?= $r['year'] ?></td>
                            <td><?= $r['total_score'] ?? '—' ?></td>
                            <td>
                                <span class="badge bg-<?= $r['status'] === 'approved' ? 'success' : 'danger' ?>">
                                    <?= $r['status'] === 'approved' ? 'موافق' : 'مرفوض' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>

<script>
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