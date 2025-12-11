<?php
// === جمل use لمكتبات التصدير ===
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\ExportService;

session_start();

// 1. استدعاء Autoloader
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    die('<div style="padding:20px; direction:rtl; font-family:tahoma;">خطأ: ملف <code>vendor/autoload.php</code> غير موجود.</div>');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز الخروج للشريط الجانبي
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';
require_once '../../app/core/ExportService.php';

// --- إعداد الفلاتر ---
$cycles = $pdo->query("SELECT * FROM evaluation_cycles ORDER BY year DESC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar")->fetchAll();

$filter_cycle = $_GET['cycle'] ?? '';
$filter_dept = $_GET['dept'] ?? '';
$filter_status = $_GET['status'] ?? '';

// بناء استعلام SQL ديناميكي (الجزء المشترك)
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
    // إذا تم اختيار حالة محددة
    $sql_base .= " AND e.status = ?";
    $params[] = $filter_status;
} else {
    // الافتراضي: عرض كل الحالات (المعتمدة، المرفوضة، والانتظار)
    // لا نضيف شرطاً هنا ليعرض الكل، أو يمكنك تحديد حالات معينة إذا رغبت
    // $sql_base .= " AND e.status IN ('approved', 'rejected', 'submitted')"; 
}

// --- معالجة التصدير إلى صيغ متعددة ---
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    // إنشاء خدمة التصدير وتعيين الفلاتر
    $exportService = new ExportService();
    $exportService->setFilters($filter_cycle, $filter_dept, $filter_status);
    
    // تنفيذ التصدير حسب النوع
    try {
        switch ($export_type) {
            case 'excel':
                $exportService->exportExcel();
                break;
            case 'pdf':
                $exportService->exportPdf();
                break;
            case 'word':
                $exportService->exportWord();
                break;
            default:
                die('صيغة التصدير غير معروفة');
        }
    } catch (Exception $e) {
        die('خطأ أثناء التصدير: ' . htmlspecialchars($e->getMessage()));
    }
}

// --- جلب البيانات للعرض ---
$sql_view = "
    SELECT u.name AS employee_name, employee_id, cycle_id, u.email, d.name_ar AS dept, e.total_score, e.status, c.year, ev.name AS evaluator_name, e.evaluator_role
    " . $sql_base . "
    ORDER BY e.updated_at DESC
";
$stmt = $pdo->prepare($sql_view);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// --- حسابات الملخص ---
$total_reports = count($reports);
$avg_score = 0;
$max_score = 0;
$min_score = 0;
$scores = array_filter(array_column($reports, 'total_score'), function($v) { return $v !== null; });

if (count($scores) > 0) {
    $avg_score = round(array_sum($scores) / count($scores), 1);
    $max_score = max($scores);
    $min_score = min($scores);
}

$role_map = [
    'manager' => 'مدير إدارة',
    'supervisor' => 'رئيس مباشر'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>التقارير المتقدمة</title>
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
        <h3><i class="fas fa-chart-line"></i> التقارير والإحصائيات</h3>
        
        <div class="btn-group" role="group">
            <a href="?export=excel&cycle=<?= htmlspecialchars($filter_cycle) ?>&dept=<?= htmlspecialchars($filter_dept) ?>&status=<?= htmlspecialchars($filter_status) ?>" class="btn btn-success" title="تصدير إلى Excel">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="?export=pdf&cycle=<?= htmlspecialchars($filter_cycle) ?>&dept=<?= htmlspecialchars($filter_dept) ?>&status=<?= htmlspecialchars($filter_status) ?>" class="btn btn-danger" title="تصدير إلى PDF">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="?export=word&cycle=<?= htmlspecialchars($filter_cycle) ?>&dept=<?= htmlspecialchars($filter_dept) ?>&status=<?= htmlspecialchars($filter_status) ?>" class="btn btn-primary" title="تصدير إلى Word">
                <i class="fas fa-file-word"></i> Word
            </a>
        </div>
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
                        <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>موافق عليه (Approved)</option>
                        <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>مرفوض (Rejected)</option>
                        <option value="submitted" <?= $filter_status == 'submitted' ? 'selected' : '' ?>>بانتظار الاعتماد (Submitted)</option>
                        <option value="draft" <?= $filter_status == 'draft' ? 'selected' : '' ?>>مسودة (draft)</option>
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
        <div class="col-md-3">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">عدد التقارير</h6>
                    <h3><?= $total_reports ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">متوسط الدرجات</h6>
                    <h3 class="<?= $avg_score >= 75 ? 'text-success' : ($avg_score >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $avg_score ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">أعلى درجة</h6>
                    <h3 class="text-success"><?= $max_score ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">أقل درجة</h6>
                    <h3 class="text-danger"><?= $min_score ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($reports)): ?>
                <div class="alert alert-warning text-center m-0">لا توجد نتائج مطابقة للفلاتر المختارة.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>الاسم</th>
                            <th>المقيّم</th>
                            <th>دوره</th>
                            <th>الإدارة</th>
                            <th>السنة</th>
                            <th>الدرجة</th>
                            <th>الحالة</th>
                            <th>عرض / PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['employee_name']) ?></td>
                                    <td><small>
                                        <?= htmlspecialchars($r['evaluator_name']) ?>
                                        (<?= $r['evaluator_role'] === 'manager' ? 'مدير إدارة' : 'رئيس مباشر' ?>)
                                   </small> </td>
                            <td><span class="badge bg-secondary"><?= $role_map[$r['evaluator_role']] ?? $r['evaluator_role'] ?></span></td>
                            <td><small><?= $r['dept'] ?? '—' ?></small></td>
                            <td><strong><?= $r['year'] ?></strong></td>
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