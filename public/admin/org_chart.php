<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز الخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';

// جلب جميع المستخدمين النشطين
// نحتاج: المعرف، الاسم، الوظيفة، والدور، ومعرف المدير
$stmt = $pdo->query("
    SELECT u.id, u.name, u.role, u.job_title, u.manager_id, d.name_ar as dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.status = 'active'
");
$users = $stmt->fetchAll();

// تحويل البيانات لتنسيق Google Charts
// التنسيق المطلوب: [ID, Tooltip, ManagerID]
$chartData = [];

foreach ($users as $user) {
    $id = (string)$user['id'];
    
    // تنسيق الصندوق (الاسم + الوظيفة + الصورة/الأيقونة)
    $roleColor = match($user['role']) {
        'admin' => 'red',
        'manager' => '#0d6efd', // أزرق
        'supervisor' => '#198754', // أخضر
        'evaluator' => '#ffc107', // أصفر
        default => '#6c757d' // رمادي
    };
    
    $nodeContent = '
        <div style="font-family: \'Segoe UI\'; padding: 5px;">
            <div style="font-weight:bold; font-size: 1.1em; color:'.$roleColor.'">' . addslashes($user['name']) . '</div>
            <div style="font-size: 0.85em; color: #555;">' . ($user['job_title'] ? addslashes($user['job_title']) : 'بدون مسمى') . '</div>
            <div style="font-size: 0.75em; color: #999; margin-top:3px;">' . ($user['dept_name'] ?? '') . '</div>
        </div>
    ';

    $parentId = $user['manager_id'] ? (string)$user['manager_id'] : '';
    
    // ملاحظة: Google Charts يتطلب أن يكون المدير موجوداً في القائمة
    // إذا كان المدير غير موجود (مثلاً محذوف أو غير نشط)، نجعله فارغاً لكي لا يختفي الموظف
    $managerExists = false;
    if ($parentId) {
        foreach ($users as $u) {
            if ((string)$u['id'] === $parentId) {
                $managerExists = true;
                break;
            }
        }
    }
    if (!$managerExists) {
        $parentId = ''; // يظهر في المستوى الأعلى (Orphan)
    }

    // [ID, Content, Tooltip]
    // نستخدم ID كـ (string) لتجنب مشاكل التفسير
    $chartData[] = [
        ['v' => $id, 'f' => $nodeContent],
        $parentId,
        $user['job_title'] ?? ''
    ];
}

// تحويل المصفوفة لـ JSON
$jsonData = json_encode($chartData);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الهيكل التنظيمي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        /* تحسين مظهر الشجرة */
        .google-visualization-orgchart-node {
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;
            background: #fff !important;
            padding: 0 !important;
        }
        /* الخطوط الواصلة */
        .google-visualization-orgchart-lineleft, 
        .google-visualization-orgchart-lineright, 
        .google-visualization-orgchart-linebottom {
            border-color: #adb5bd !important;
        }
        /* العقدة المحددة */
        .google-visualization-orgchart-nodesel {
            border: 2px solid #0d6efd !important;
            background: #e8f4ff !important;
        }
    </style>
</head>
<body class="admin-dashboard">

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-sitemap"></i> الهيكل التنظيمي</h3>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print"></i> طباعة</button>
    </div>
    <hr>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> يتم بناء الهيكل بناءً على حقل <strong>"مدير الإدارة"</strong> في ملف كل موظف.
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4 text-center">
            <div id="chart_div" style="width: 100%; overflow-x: auto;">
                <div class="py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i> جاري تحميل الهيكل...</div>
            </div>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>

<script type="text/javascript">
    google.charts.load('current', {packages:["orgchart"]});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Name');
        data.addColumn('string', 'Manager');
        data.addColumn('string', 'ToolTip');

        // البيانات من PHP
        var chartData = <?= $jsonData ?>;
        
        data.addRows(chartData);

        // إنشاء الرسم
        var chart = new google.visualization.OrgChart(document.getElementById('chart_div'));
        
        // رسم الشجرة
        chart.draw(data, {
            'allowHtml': true,
            'allowCollapse': true, // السماح بالطي عند النقر المزدوج
            'nodeClass': 'org-node',
            'selectedNodeClass': 'org-selected'
        });
    }
</script>

<script src="../assets/js/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>