<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// Logout CSRF Token
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

// جلب قائمة الإدارات
$departments = $pdo->query("SELECT id, name_ar FROM departments WHERE status = 'active' ORDER BY name_ar")->fetchAll();

// جلب قائمة دورات التقييم
$cycles = $pdo->query("SELECT id, year FROM evaluation_cycles ORDER BY year DESC")->fetchAll();

// تحديد الدورة النشطة كافتراضية
$current_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' ORDER BY year DESC LIMIT 1")->fetch();
$current_cycle_id = $current_cycle ? $current_cycle['id'] : null;
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحليل الأداء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/analytics-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <script src="../assets/js/analytics-charts.js"></script>
</head>
<body class="admin-dashboard">

<?php 
$current_page = 'analytics-dashboard.php';
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><i class="fas fa-chart-line"></i> لوحة تحليل الأداء</h3>
            <span class="text-muted">
                <?php if ($current_cycle): ?>
                    الدورة الحالية: <strong><?= htmlspecialchars($current_cycle['year']) ?></strong>
                <?php else: ?>
                    لا توجد دورة نشطة
                <?php endif; ?>
            </span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt"></i> تحديث البيانات
            </button>
            <span class="badge bg-success p-2 px-3" id="lastUpdate">
                <i class="fas fa-clock"></i> <?= date('H:i') ?>
            </span>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter"></i> فلاتر التحليل</h5>
        </div>
        <div class="card-body">
            <form id="analyticsFilters" method="GET" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="row g-3">
                    <!-- Evaluation Cycle Filter -->
                    <div class="col-md-3">
                        <label class="form-label">دورة التقييم</label>
                        <select name="cycle_id" class="form-select" id="cycleFilter">
                            <option value="">جميع الدورات</option>
                            <?php foreach ($cycles as $cycle): ?>
                                <option value="<?= $cycle['id'] ?>" <?= ($current_cycle_id == $cycle['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cycle['year']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div class="col-md-3">
                        <label class="form-label">الإدارات</label>
                        <select name="department_ids[]" class="form-select" id="departmentFilter" multiple>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>">
                                    <?= htmlspecialchars($dept['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">اضغط Ctrl لاختيار عدة إدارات</small>
                    </div>

                    <!-- Evaluator Role Filter -->
                    <div class="col-md-2">
                        <label class="form-label">دور المقيم</label>
                        <select name="evaluator_role" class="form-select" id="evaluatorRoleFilter">
                            <option value="">جميع الأدوار</option>
                            <option value="manager">مدير</option>
                            <option value="supervisor">مشرف</option>
                        </select>
                    </div>

                    <!-- Date Range Filters -->
                    <div class="col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" id="dateFromFilter">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" id="dateToFilter">
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <!-- Score Range Filters -->
                    <div class="col-md-3">
                        <label class="form-label">الحد الأدنى للنقاط</label>
                        <input type="number" name="min_score" class="form-control" id="minScoreFilter" 
                               min="0" max="100" step="0.1" placeholder="0">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">الحد الأقصى للنقاط</label>
                        <input type="number" name="max_score" class="form-control" id="maxScoreFilter" 
                               min="0" max="100" step="0.1" placeholder="100">
                    </div>

                    <!-- Filter Actions -->
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> تطبيق الفلاتر
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> مسح الفلاتر
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="exportAnalytics()">
                            <i class="fas fa-download"></i> تصدير البيانات
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading States -->
    <div id="loadingState" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جارٍ التحميل...</span>
        </div>
        <p class="mt-3 text-muted">جارٍ تحميل بيانات التحليل...</p>
    </div>

    <!-- KPI Cards Section -->
    <div id="kpiSection" class="row mb-4" style="display: none;">
        <div class="col-md-2">
            <div class="kpi-card bg-primary text-white">
                <div class="kpi-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value" id="totalEvaluations">-</div>
                    <div class="kpi-label">إجمالي التقييمات</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-card bg-success text-white">
                <div class="kpi-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value" id="companyAverage">-</div>
                    <div class="kpi-label">متوسط الشركة</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-card bg-info text-white">
                <div class="kpi-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value" id="completionRate">-</div>
                    <div class="kpi-label">معدل الإنجاز</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-card bg-warning text-white">
                <div class="kpi-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value" id="activeDepartments">-</div>
                    <div class="kpi-label">الإدارات الفعالة</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-card bg-danger text-white">
                <div class="kpi-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value" id="alertsCount">-</div>
                    <div class="kpi-label">التنبيهات</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-card bg-secondary text-white">
                <div class="kpi-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="kpi-content">
                    <div class="kpi-value" id="totalEmployees">-</div>
                    <div class="kpi-label">إجمالي الموظفين</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div id="chartsSection" style="display: none;">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-doughnut"></i> توزيع حالات التقييم</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 250px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> اتجاهات الأداء</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 250px;">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> مقارنة الإدارات</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="toggleDeptMetric">
                            <label class="form-check-label" for="toggleDeptMetric" style="font-size: 0.9rem;">معدل الإنجاز</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-project-diagram"></i> تحليل الجدارات</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="radarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-th"></i> مصفوفة الأداء</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 400px;">
                            <canvas id="heatmapChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Performance Table -->
    <div class="row mb-4" id="departmentsSection" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-building"></i> أداء الإدارات</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>الترتيب</th>
                                    <th>الإدارة</th>
                                    <th>عدد التقييمات</th>
                                    <th>المتوسط</th>
                                    <th>المعدل المعتمد</th>
                                    <th>معدل الإنجاز</th>
                                    <th>حالة الأداء</th>
                                </tr>
                            </thead>
                            <tbody id="departmentsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">جارٍ تحميل البيانات...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Performance Comparison -->
    <div class="row" id="employeesSection" style="display: none;">
        <!-- Top Performers -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-trophy"></i> أفضل الموظفين أداءً</h5>
                </div>
                <div class="card-body">
                    <div id="topPerformersList">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin"></i> جارٍ التحميل...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Performers -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle"></i> الموظفين الذين يحتاجون تطوير</h5>
                </div>
                <div class="card-body">
                    <div id="bottomPerformersList">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin"></i> جارٍ التحميل...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Badges Section -->
    <div class="row mt-4" id="badgesSection" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-medal"></i> إنجازات سريعة</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="performance-badge">
                                <div class="badge-icon">
                                    <i class="fas fa-arrow-trend-up"></i>
                                </div>
                                <div class="badge-content">
                                    <div class="badge-title">أعلى نمو</div>
                                    <div class="badge-value" id="highestGrowth">-</div>
                                    <div class="badge-description">أكبر تحسن في النقاط</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="performance-badge">
                                <div class="badge-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="badge-content">
                                    <div class="badge-title">المتميزون</div>
                                    <div class="badge-value" id="excellentPerformers">-</div>
                                    <div class="badge-description">موظفون فوق 90%</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="performance-badge">
                                <div class="badge-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="badge-content">
                                    <div class="badge-title">يتطلب تطوير</div>
                                    <div class="badge-value" id="needsImprovement">-</div>
                                    <div class="badge-description">موظفون تحت 70%</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="performance-badge">
                                <div class="badge-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="badge-content">
                                    <div class="badge-title">الأكثر نشاطاً</div>
                                    <div class="badge-value" id="mostActive">-</div>
                                    <div class="badge-description">آخر تحديثات</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-5" style="display: none;">
        <div class="mb-4">
            <i class="fas fa-chart-bar fa-4x text-muted"></i>
        </div>
        <h4 class="text-muted mb-3">لا توجد بيانات للعرض</h4>
        <p class="text-muted">قم بتطبيق الفلاتر أو انتظر حتى يتم إضافة بيانات تقييم جديدة</p>
        <button type="button" class="btn btn-primary" onclick="refreshDashboard()">
            <i class="fas fa-sync-alt"></i> تحديث البيانات
        </button>
    </div>

</main>

<!-- Chart Scripts -->
<script>
// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    loadAnalyticsData();
});

// Setup event listeners
function setupEventListeners() {
    // Form submission
    const filtersForm = document.getElementById('analyticsFilters');
    filtersForm.addEventListener('submit', function(e) {
        e.preventDefault();
        loadAnalyticsData();
    });

    // Multi-select optimization
    const departmentFilter = document.getElementById('departmentFilter');
    if (departmentFilter && departmentFilter.multiple) {
        departmentFilter.addEventListener('change', function() {
            // Limit selection to reasonable number
            if (this.selectedOptions.length > 10) {
                alert('يمكنك اختيار 10 إدارات كحد أقصى');
                this.selectedOptions[this.selectedOptions.length - 1].selected = false;
            }
        });
    }

    // Department Chart Metric Toggle
    const toggleDeptMetric = document.getElementById('toggleDeptMetric');
    if (toggleDeptMetric) {
        toggleDeptMetric.addEventListener('change', function() {
            if (window.AnalyticsCharts) {
                window.AnalyticsCharts.toggleDepartmentMetric(this.checked);
            }
        });
    }
}

// Load analytics data via AJAX
function loadAnalyticsData() {
    const loadingState = document.getElementById('loadingState');
    const kpiSection = document.getElementById('kpiSection');
    const chartsSection = document.getElementById('chartsSection');
    const departmentsSection = document.getElementById('departmentsSection');
    const employeesSection = document.getElementById('employeesSection');
    const badgesSection = document.getElementById('badgesSection');
    const emptyState = document.getElementById('emptyState');

    // Show loading state
    loadingState.style.display = 'block';
    kpiSection.style.display = 'none';
    chartsSection.style.display = 'none';
    departmentsSection.style.display = 'none';
    employeesSection.style.display = 'none';
    badgesSection.style.display = 'none';
    emptyState.style.display = 'none';

    // Collect filter values
    const filters = collectFilters();
    
    // Make AJAX request
    fetch('api/analytics-data.php?types=comprehensive&' + new URLSearchParams(filters), {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        // Hide loading state
        loadingState.style.display = 'none';

        if (data.success && data.data) {
            // Update dashboard sections
            updateKPICards(data.data.global_stats || {});
            
            // Update all charts using the new module
            if (window.AnalyticsCharts) {
                window.AnalyticsCharts.update(data.data);
            }
            
            updateDepartmentsTable(data.data.departments || []);
            updateTopBottomPerformers(data.data.top_performers || [], data.data.bottom_performers || []);
            updatePerformanceBadges(data.data);

            // Show sections
            kpiSection.style.display = 'flex';
            chartsSection.style.display = 'block';
            departmentsSection.style.display = 'block';
            employeesSection.style.display = 'block';
            badgesSection.style.display = 'block';

            // Update last refresh time
            document.getElementById('lastUpdate').innerHTML = 
                '<i class="fas fa-clock"></i> ' + new Date().toLocaleTimeString('ar-SA');
        } else {
            // Show empty state
            emptyState.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading analytics data:', error);
        loadingState.style.display = 'none';
        emptyState.style.display = 'block';
    });
}

// Collect form filters
function collectFilters() {
    const form = document.getElementById('analyticsFilters');
    const formData = new FormData(form);
    const filters = {};

    for (let [key, value] of formData.entries()) {
        if (value.trim() !== '') {
            if (key === 'department_ids[]') {
                if (!filters.department_ids) {
                    filters.department_ids = [];
                }
                filters.department_ids.push(value);
            } else {
                filters[key] = value;
            }
        }
    }

    return filters;
}

// Update KPI Cards
function updateKPICards(globalStats) {
    document.getElementById('totalEvaluations').textContent = globalStats.total_evaluations || '0';
    document.getElementById('companyAverage').textContent = (globalStats.overall_average || 0) + '%';
    document.getElementById('completionRate').textContent = (globalStats.completion_rate || 0) + '%';
    document.getElementById('activeDepartments').textContent = globalStats.active_departments || '0';
    document.getElementById('alertsCount').textContent = globalStats.alerts_count || '0';
    document.getElementById('totalEmployees').textContent = globalStats.total_employees || '0';
}


// Update Departments Table
function updateDepartmentsTable(departmentsData) {
    const tbody = document.getElementById('departmentsTableBody');
    
    if (departmentsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">لا توجد بيانات</td></tr>';
        return;
    }

    tbody.innerHTML = departmentsData.map(dept => {
        const avgScore = parseFloat(dept.avg_score || 0);
        const completionRate = parseFloat(dept.completion_rate || 0);
        
        let performanceClass = 'badge-success';
        let performanceText = 'ممتاز';
        
        if (avgScore < 70) {
            performanceClass = 'badge-danger';
            performanceText = 'يحتاج تطوير';
        } else if (avgScore < 85) {
            performanceClass = 'badge-warning';
            performanceText = 'جيد';
        }

        return `
            <tr>
                <td><span class="badge bg-primary">${dept.ranking || '-'}</span></td>
                <td><strong>${dept.name_ar || '-'}</strong></td>
                <td>${dept.evaluation_count || 0}</td>
                <td>${avgScore.toFixed(1)}%</td>
                <td>${dept.approved_count || 0}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: ${completionRate}%">${completionRate.toFixed(1)}%</div>
                    </div>
                </td>
                <td><span class="badge ${performanceClass}">${performanceText}</span></td>
            </tr>
        `;
    }).join('');
}

// Update Top/Bottom Performers
function updateTopBottomPerformers(topPerformers, bottomPerformers) {
    // Update top performers
    const topContainer = document.getElementById('topPerformersList');
    if (topPerformers.length === 0) {
        topContainer.innerHTML = '<div class="text-center text-muted py-4">لا توجد بيانات</div>';
    } else {
        topContainer.innerHTML = topPerformers.map((employee, index) => `
            <div class="employee-item top-performer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="employee-name">${employee.employee_name || 'غير محدد'}</div>
                        <div class="employee-department">${employee.department || ''}</div>
                        <div class="employee-manager">${employee.manager_name || ''}</div>
                    </div>
                    <div class="employee-score">
                        <span class="score-badge badge-success">${(employee.total_score || 0).toFixed(1)}%</span>
                    </div>
                </div>
                ${employee.score_delta ? `<div class="score-improvement text-success"><i class="fas fa-arrow-up"></i> +${employee.score_delta.toFixed(1)}</div>` : ''}
            </div>
        `).join('');
    }

    // Update bottom performers
    const bottomContainer = document.getElementById('bottomPerformersList');
    if (bottomPerformers.length === 0) {
        bottomContainer.innerHTML = '<div class="text-center text-muted py-4">لا توجد بيانات</div>';
    } else {
        bottomContainer.innerHTML = bottomPerformers.map((employee, index) => `
            <div class="employee-item bottom-performer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="employee-name">${employee.employee_name || 'غير محدد'}</div>
                        <div class="employee-department">${employee.department || ''}</div>
                        <div class="employee-manager">${employee.manager_name || ''}</div>
                    </div>
                    <div class="employee-score">
                        <span class="score-badge badge-danger">${(employee.total_score || 0).toFixed(1)}%</span>
                    </div>
                </div>
                ${employee.score_delta ? `<div class="score-improvement text-danger"><i class="fas fa-arrow-down"></i> ${employee.score_delta.toFixed(1)}</div>` : ''}
            </div>
        `).join('');
    }
}

// Update Performance Badges
function updatePerformanceBadges(data) {
    // This would calculate the badge values based on the analytics data
    document.getElementById('highestGrowth').textContent = 'أحمد محمد';
    document.getElementById('excellentPerformers').textContent = (data.global_stats?.excellent_count || 0);
    document.getElementById('needsImprovement').textContent = (data.global_stats?.needs_improvement_count || 0);
    document.getElementById('mostActive').textContent = 'سارة أحمد';
}

// Clear all filters
function clearFilters() {
    const form = document.getElementById('analyticsFilters');
    form.reset();
    loadAnalyticsData();
}

// Refresh dashboard data
function refreshDashboard() {
    loadAnalyticsData();
}

// Export analytics data
function exportAnalytics() {
    const filters = collectFilters();
    const params = new URLSearchParams(filters);
    window.open('api/analytics-data.php?types=comprehensive&export=1&' + params);
}
</script>

<script src="../assets/js/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>