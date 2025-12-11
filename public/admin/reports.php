<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\ExportService;

session_start();

// 1. Autoloader
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    die('<div style="padding:20px; direction:rtl; font-family:tahoma;">خطأ: ملف <code>vendor/autoload.php</code> غير موجود.</div>');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$csrf_token = $_SESSION['csrf_token'];

// Logout Token (keeping existing logic)
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';
require_once '../../app/core/ExportService.php';

// --- Initialize Options ---
$cycles = $pdo->query("SELECT * FROM evaluation_cycles ORDER BY year DESC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar")->fetchAll();

// --- Capture Filters ---
// Use $_REQUEST to support both GET (View) and POST (Export)
$request_source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$filters = [
    'cycle' => $request_source['cycle'] ?? '',
    'dept' => isset($request_source['dept']) ? (is_array($request_source['dept']) ? $request_source['dept'] : [$request_source['dept']]) : [],
    'status' => isset($request_source['status']) ? (is_array($request_source['status']) ? $request_source['status'] : [$request_source['status']]) : [],
    'role' => $request_source['role'] ?? '',
    'min_score' => $request_source['min_score'] ?? '',
    'max_score' => $request_source['max_score'] ?? '',
    'start_date' => $request_source['start_date'] ?? '',
    'end_date' => $request_source['end_date'] ?? '',
    'sort' => $request_source['sort'] ?? 'updated_at_desc'
];

// Clean empty values
$filters['dept'] = array_filter($filters['dept']);
$filters['status'] = array_filter($filters['status']);

// --- AJAX Count Handler ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'count') {
    $service = new ExportService();
    $service->setFilters($filters);
    $stats = $service->getSummaryStats();
    header('Content-Type: application/json');
    echo json_encode(['count' => $stats['total_reports']]);
    exit;
}

// --- Export Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('خطأ في التحقق من الأمان (CSRF Token mismatch)');
    }

    $export_type = $_POST['export'];
    $columns = $_POST['columns'] ?? [];
    $sections = $_POST['sections'] ?? [];
    
    $exportService = new ExportService();
    $exportService->setFilters($filters);
    $exportService->setOptions($columns, $sections);
    
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

// --- View Data ---
$exportService = new ExportService();
$exportService->setFilters($filters);
$reports = $exportService->getFilteredData();
$stats = $exportService->getSummaryStats();

$total_reports = $stats['total_reports'];
$avg_score = $stats['avg_score'] ? round($stats['avg_score'], 1) : 0;
$max_score = $stats['max_score'] ?? 0;
$min_score = $stats['min_score'] ?? 0;

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
    <style>
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0d6efd;
        }
        .form-label { font-weight: bold; font-size: 0.9rem; }
        .export-option-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .export-option-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .export-option-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .export-icon { font-size: 2rem; margin-bottom: 10px; }
        .fa-file-excel { color: #198754; }
        .fa-file-pdf { color: #dc3545; }
        .fa-file-word { color: #0d6efd; }
    </style>
</head>
<body class="admin-dashboard">

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-chart-line"></i> التقارير والإحصائيات</h3>
        
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
            <i class="fas fa-file-export"></i> تصدير التقارير
        </button>
    </div>
    <hr>
    
    <!-- Advanced Filters Accordion -->
    <div class="accordion mb-4" id="filterAccordion">
        <div class="accordion-item border-primary">
            <h2 class="accordion-header">
                <button class="accordion-button <?= empty($_GET) ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFilters">
                    <i class="fas fa-filter text-primary me-2"></i> تصفية متقدمة
                </button>
            </h2>
            <div id="collapseFilters" class="accordion-collapse collapse <?= !empty($_GET) ? 'show' : '' ?>" data-bs-parent="#filterAccordion">
                <div class="accordion-body bg-light">
                    <form method="GET" id="filterForm">
                        <div class="row g-3">
                            <!-- Row 1 -->
                            <div class="col-md-3">
                                <label class="form-label">دورة التقييم</label>
                                <select name="cycle" class="form-select">
                                    <option value="">-- كل السنوات --</option>
                                    <?php foreach ($cycles as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filters['cycle'] == $c['id'] ? 'selected' : '' ?>><?= $c['year'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">دور المُقيّم</label>
                                <select name="role" class="form-select">
                                    <option value="">-- الكل --</option>
                                    <option value="manager" <?= $filters['role'] == 'manager' ? 'selected' : '' ?>>مدير إدارة</option>
                                    <option value="supervisor" <?= $filters['role'] == 'supervisor' ? 'selected' : '' ?>>رئيس مباشر</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تاريخ التحديث (من)</label>
                                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filters['start_date']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تاريخ التحديث (إلى)</label>
                                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filters['end_date']) ?>">
                            </div>

                            <!-- Row 2 -->
                            <div class="col-md-4">
                                <label class="form-label">الإدارة (اختيار متعدد)</label>
                                <select name="dept[]" class="form-select" multiple size="3">
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= in_array($d['id'], $filters['dept']) ? 'selected' : '' ?>><?= $d['name_ar'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">اضغط Ctrl لتحديد أكثر من إدارة</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">نطاق الدرجات</label>
                                <div class="input-group">
                                    <input type="number" name="min_score" id="min_score" class="form-control" placeholder="من" min="0" max="100" value="<?= htmlspecialchars($filters['min_score']) ?>">
                                    <span class="input-group-text">-</span>
                                    <input type="number" name="max_score" id="max_score" class="form-control" placeholder="إلى" min="0" max="100" value="<?= htmlspecialchars($filters['max_score']) ?>">
                                </div>
                                <div id="score_error" class="text-danger small mt-1 d-none">الحد الأدنى يجب أن يكون أصغر من الحد الأعلى</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ترتيب حسب</label>
                                <select name="sort" class="form-select">
                                    <option value="updated_at_desc" <?= $filters['sort'] == 'updated_at_desc' ? 'selected' : '' ?>>تاريخ التحديث (الأحدث أولاً)</option>
                                    <option value="updated_at_asc" <?= $filters['sort'] == 'updated_at_asc' ? 'selected' : '' ?>>تاريخ التحديث (الأقدم أولاً)</option>
                                    <option value="score_desc" <?= $filters['sort'] == 'score_desc' ? 'selected' : '' ?>>الدرجة (الأعلى أولاً)</option>
                                    <option value="score_asc" <?= $filters['sort'] == 'score_asc' ? 'selected' : '' ?>>الدرجة (الأقل أولاً)</option>
                                    <option value="dept" <?= $filters['sort'] == 'dept' ? 'selected' : '' ?>>الإدارة</option>
                                </select>
                            </div>

                            <!-- Row 3: Status Checkboxes -->
                            <div class="col-12">
                                <label class="form-label d-block mb-2">الحالة</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="status[]" value="approved" id="status_approved" <?= in_array('approved', $filters['status']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-success" for="status_approved">موافق عليه</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="status[]" value="rejected" id="status_rejected" <?= in_array('rejected', $filters['status']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-danger" for="status_rejected">مرفوض</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="status[]" value="submitted" id="status_submitted" <?= in_array('submitted', $filters['status']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-warning" for="status_submitted">بانتظار الاعتماد</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="status[]" value="draft" id="status_draft" <?= in_array('draft', $filters['status']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-secondary" for="status_draft">مسودة</label>
                                </div>
                            </div>

                            <div class="col-12 text-end mt-3">
                                <a href="reports.php" class="btn btn-secondary me-2">إعادة تعيين</a>
                                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-search"></i> عرض النتائج</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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
                            <th>عرض</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><small>
                                <?= htmlspecialchars($r['evaluator']) ?>
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

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تصدير التقارير</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" action="reports.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <!-- Hidden Filters (will be populated by JS) -->
                    <div id="hiddenFilters"></div>
                    
                    <h6 class="mb-3">اختر الصيغة:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <div class="export-option-card selected" onclick="selectExportType('excel')">
                                <i class="fas fa-file-excel export-icon"></i>
                                <div>Excel</div>
                                <input type="radio" name="export" value="excel" checked class="d-none">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="export-option-card" onclick="selectExportType('pdf')">
                                <i class="fas fa-file-pdf export-icon"></i>
                                <div>PDF</div>
                                <input type="radio" name="export" value="pdf" class="d-none">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="export-option-card" onclick="selectExportType('word')">
                                <i class="fas fa-file-word export-icon"></i>
                                <div>Word</div>
                                <input type="radio" name="export" value="word" class="d-none">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">الأقسام المطلوبة:</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="summary" checked id="sec_summary">
                                <label class="form-check-label" for="sec_summary">صفحة الملخص والإحصائيات</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="details" checked id="sec_details">
                                <label class="form-check-label" for="sec_details">جدول تفاصيل الموظفين</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">الأعمدة (للجدول):</h6>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="name" checked id="col_name">
                                        <label class="form-check-label" for="col_name">الاسم</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="email" checked id="col_email">
                                        <label class="form-check-label" for="col_email">البريد</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="dept" checked id="col_dept">
                                        <label class="form-check-label" for="col_dept">الإدارة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="year" checked id="col_year">
                                        <label class="form-check-label" for="col_year">السنة</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="evaluator" checked id="col_evaluator">
                                        <label class="form-check-label" for="col_evaluator">المُقيّم</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="score" checked id="col_score">
                                        <label class="form-check-label" for="col_score">الدرجة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="status" checked id="col_status">
                                        <label class="form-check-label" for="col_status">الحالة</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="updated_at" checked id="col_updated">
                                        <label class="form-check-label" for="col_updated">تاريخ التحديث</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle"></i> سيتم تصدير <strong><span id="exportCount">0</span></strong> سجل بناءً على الفلاتر الحالية.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="btnConfirmExport" onclick="submitExport()">تصدير</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function selectExportType(type) {
        document.querySelectorAll('.export-option-card').forEach(el => el.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
        document.querySelector(`input[name="export"][value="${type}"]`).checked = true;
    }

    // Populate hidden inputs in export form based on main filter form
    function populateExportFilters() {
        const filterForm = document.getElementById('filterForm');
        const hiddenContainer = document.getElementById('hiddenFilters');
        hiddenContainer.innerHTML = '';
        
        const formData = new FormData(filterForm);
        
        // Convert FormData to query string for AJAX count
        const params = new URLSearchParams(formData);
        
        // Add hidden inputs
        for (const [key, value] of formData.entries()) {
            if (value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                hiddenContainer.appendChild(input);
            }
        }
        
        // Update count via AJAX
        params.append('ajax', 'count');
        fetch('reports.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                const countSpan = document.getElementById('exportCount');
                const btn = document.getElementById('btnConfirmExport');
                
                countSpan.innerText = data.count;
                
                if (data.count == 0) {
                    btn.disabled = true;
                    btn.innerText = 'لا توجد بيانات';
                } else {
                    btn.disabled = false;
                    btn.innerText = 'تصدير';
                }
            })
            .catch(error => console.error('Error fetching count:', error));
    }

    // Modal Event Listener
    const exportModal = document.getElementById('exportModal');
    exportModal.addEventListener('show.bs.modal', function () {
        populateExportFilters();
    });

    function submitExport() {
        document.getElementById('exportForm').submit();
        const modal = bootstrap.Modal.getInstance(exportModal);
        modal.hide();
    }

    // Score Range Validation
    const minScoreInput = document.getElementById('min_score');
    const maxScoreInput = document.getElementById('max_score');
    const scoreError = document.getElementById('score_error');
    const filterForm = document.getElementById('filterForm');

    function validateScore() {
        const min = parseInt(minScoreInput.value);
        const max = parseInt(maxScoreInput.value);
        
        if (!isNaN(min) && !isNaN(max) && min > max) {
            scoreError.classList.remove('d-none');
            return false;
        } else {
            scoreError.classList.add('d-none');
            return true;
        }
    }

    minScoreInput.addEventListener('input', validateScore);
    maxScoreInput.addEventListener('input', validateScore);

    filterForm.addEventListener('submit', function(e) {
        if (!validateScore()) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>
