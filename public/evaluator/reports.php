<?php
// === جمل use لمكتبة Excel ===
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

// 1. استدعاء Autoloader
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    die('<div style="padding:20px; direction:rtl; font-family:tahoma;">خطأ: ملف <code>vendor/autoload.php</code> غير موجود.</div>');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز الخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

// --- إعداد الفلاتر ---
$cycles = $pdo->query("SELECT * FROM evaluation_cycles ORDER BY year DESC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar")->fetchAll();

$filter_cycle = $_GET['cycle'] ?? '';
$filter_dept = $_GET['dept'] ?? '';
$filter_status = $_GET['status'] ?? '';

// بناء استعلام SQL ديناميكي
// (تعديل) تم إزالة شرط "u.role != 'admin'" للسماح بظهور تقييمات المسؤولين إن وجدت
$sql_base = "
    FROM employee_evaluations e
    JOIN users u ON e.employee_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    JOIN evaluation_cycles c ON e.cycle_id = c.id
    JOIN users ev ON e.evaluator_id = ev.id
    WHERE 1=1 
";
$params = [];

// تطبيق الفلاتر
if ($filter_cycle) {
    $sql_base .= " AND e.cycle_id = ?";
    $params[] = $filter_cycle;
}
if ($filter_dept) {
    $sql_base .= " AND u.department_id = ?";
    $params[] = $filter_dept;
}
if ($filter_status) {
    $sql_base .= " AND e.status = ?";
    $params[] = $filter_status;
}

// --- معالجة التصدير إلى Excel ---
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (ob_get_length()) ob_clean();

    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        die('مكتبة Excel غير موجودة.');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);

    // العناوين
    $headers = ['الموظف', 'البريد الإلكتروني', 'الدور', 'الإدارة', 'دورة التقييم', 'المُقيّم', 'الدرجة', 'الحالة', 'تاريخ التحديث'];
    $sheet->fromArray($headers, NULL, 'A1');

    // جلب البيانات للتصدير
    $sql_export = "SELECT u.name, u.email, u.role, d.name_ar as dept, c.year, ev.name as evaluator, e.total_score, e.status, e.updated_at " . $sql_base . " ORDER BY e.updated_at DESC";
    $stmt = $pdo->prepare($sql_export);
    $stmt->execute($params);
    
    $row = 2;
    while ($r = $stmt->fetch()) {
        $status_text = match($r['status']) {
            'approved' => 'موافق عليه',
            'rejected' => 'مرفوض',
            'submitted' => 'بانتظار الاعتماد',
            'draft' => 'مسودة',
            default => $r['status']
        };
        
        $role_text = match($r['role']) {
            'admin' => 'مسؤول',
            'manager' => 'مدير إدارة',
            'supervisor' => 'رئيس مباشر',
            'evaluator' => 'موظف تقييمات',
            default => 'موظف'
        };

        $data = [
            $r['name'],
            $r['email'],
            $role_text,
            $r['dept'] ?? '—',
            $r['year'],
            $r['evaluator'],
            $r['total_score'] ?? '—',
            $status_text,
            $r['updated_at']
        ];
        $sheet->fromArray($data, NULL, 'A' . $row);
        $row++;
    }
    
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="evaluation_report_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// --- جلب البيانات للعرض ---
$sql_view = "
    SELECT 
        u.name AS employee_name, u.email, u.role,
        d.name_ar AS dept, 
        e.total_score, e.status, 
        c.year, c.id as cycle_id,
        ev.name AS evaluator_name, e.evaluator_role,
        u.id as employee_id
    " . $sql_base . "
    ORDER BY e.updated_at DESC
";
$stmt = $pdo->prepare($sql_view);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// --- حسابات الملخص ---
$total_reports = count($reports);
$avg_score = 0;
$scores = array_filter(array_column($reports, 'total_score'), function($v) { return $v !== null; });

if (count($scores) > 0) {
    $avg_score = round(array_sum($scores) / count($scores), 1);
}

$role_map = [
    'admin' => 'مسؤول',
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
    <title>التقارير - موظف التقييمات</title>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-chart-line"></i> تقارير التقييمات</h3>
        
        <a href="?export=excel&cycle=<?= htmlspecialchars($filter_cycle) ?>&dept=<?= htmlspecialchars($filter_dept) ?>&status=<?= htmlspecialchars($filter_status) ?>" class="btn btn-success">
            <i class="fas fa-file-excel"></i> تصدير (Excel)
        </a>
    </div>
    <hr>
    
    <div class="card mb-4 border-primary">
        <div class="card-header bg-light">
            <i class="fas fa-filter text-primary"></i> تصفية النتائج
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">دورة التقييم</label>
                    <select name="cycle" class="form-select">
                        <option value="">-- كل السنوات --</option>
                        <?php foreach ($cycles as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filter_cycle == $c['id'] ? 'selected' : '' ?>><?= $c['year'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الإدارة</label>
                    <select name="dept" class="form-select">
                        <option value="">-- كل الإدارات --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= $d['name_ar'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">-- الكل --</option>
                        <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>موافق عليه</option>
                        <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                        <option value="submitted" <?= $filter_status == 'submitted' ? 'selected' : '' ?>>بانتظار الاعتماد</option>
						<option value="draft" <?= $filter_status == 'draft' ? 'selected' : '' ?>>مسودة</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> عرض</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($total_reports > 0): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">عدد التقارير</h6>
                    <h3><?= $total_reports ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">متوسط الدرجات</h6>
                    <h3 class="<?= $avg_score >= 75 ? 'text-success' : ($avg_score >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $avg_score ?>%</h3>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($reports)): ?>
                <div class="alert alert-warning text-center m-0">لا توجد نتائج مطابقة.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>الاسم</th>
                            <th>الدور</th> <th>البريد</th>
                            <th>المقيّم</th>
                            <th>الإدارة</th>
                            <th>الدرجة</th>
                            <th>الحالة</th>
                            <th>عرض / PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['employee_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= $role_map[$r['role']] ?? $r['role'] ?></span></td>
                            <td><small><?= htmlspecialchars($r['email']) ?></small></td>
                            <td><?= htmlspecialchars($r['evaluator_name']) ?></td>
                            <td><?= $r['dept'] ?? '—' ?></td>
                            <td>
                                <?php if ($r['total_score'] !== null): ?>
                                    <strong class="<?= $r['total_score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                        <?= $r['total_score'] ?>
                                    </strong>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $badge_color = match($r['status']) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'submitted' => 'warning',
                                    default => 'secondary'
                                };
                                $status_label = match($r['status']) {
                                    'approved' => 'موافق',
                                    'rejected' => 'مرفوض',
                                    'submitted' => 'انتظار',
                                    default => 'مسودة'
                                };
                                ?>
                                <span class="badge bg-<?= $badge_color ?>"><?= $status_label ?></span>
                            </td>
                            <td>
                                <?php 
                                // جلب التوكن لعرض الرابط
                                $token_stmt = $pdo->prepare("SELECT unique_token FROM employee_evaluation_links WHERE employee_id = ? AND cycle_id = ?");
                                $token_stmt->execute([$r['employee_id'], $r['cycle_id']]);
                                $token = $token_stmt->fetchColumn();
                                ?>
                                <?php if ($token): ?>
                                    <a href="../view-ev-report.php?token=<?= $token ?>" target="_blank" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-file-pdf"></i> عرض
                                    </a>
                                <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-spinner fa-spin"></i> يُقيم...
                                                </button>
                                <?php endif; ?>
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

<div id="internet-status"><span class="badge bg-success">متصل</span></div>

</body>
</html>