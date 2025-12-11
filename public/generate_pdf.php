<?php
// استدعاء المكتبات وقاعدة البيانات
require_once '../vendor/autoload.php';
require_once '../app/core/db.php';

// التحقق من التوكن
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die('رابط غير صالح');
}

$token = $_GET['token'];

// 1. جلب بيانات التقييم (نفس منطق view-evaluation.php)
$stmt = $pdo->prepare("
    SELECT e.*, u.name as employee_name, u.email, u.job_title, u.id as employee_id,
           c.year, c.id as cycle_id,
           d.name_ar as dept_name,
           ev.name as evaluator_name, e.evaluator_role
    FROM employee_evaluation_links l
    JOIN users u ON l.employee_id = u.id
    JOIN evaluation_cycles c ON l.cycle_id = c.id
    JOIN employee_evaluations e ON e.employee_id = u.id AND e.cycle_id = c.id
    LEFT JOIN departments d ON u.department_id = d.id
    JOIN users ev ON e.evaluator_id = ev.id
    WHERE l.unique_token = ? AND e.evaluator_role = 'manager'
");
$stmt->execute([$token]);
$eval = $stmt->fetch();

if (!$eval) die('التقييم غير موجود أو الرابط غير صالح.');

// جلب التقييمات التفصيلية
$supervisor_eval = $pdo->prepare("SELECT total_score FROM employee_evaluations WHERE employee_id=? AND cycle_id=? AND evaluator_role='supervisor'")->execute([$eval['employee_id'], $eval['cycle_id']]);
// (يمكنك جلب التفاصيل كاملة إذا أردت عرضها في الـ PDF)

// جلب تفاصيل الدرجات (المدير)
$responses = $pdo->prepare("
    SELECT r.score, f.title_ar, f.max_score 
    FROM evaluation_responses r 
    JOIN evaluation_fields f ON r.field_id = f.id 
    WHERE r.evaluation_id = ? 
    ORDER BY f.order_index
");
$responses->execute([$eval['id']]);
$details = $responses->fetchAll();

// جلب الملاحظات النصية
$text_responses = $pdo->prepare("
    SELECT tf.title_ar, tr.response_text 
    FROM evaluation_custom_text_fields tf 
    LEFT JOIN evaluation_custom_text_responses tr ON tf.id = tr.field_id AND tr.evaluation_id = ? 
    WHERE tf.cycle_id = ?
");
$text_responses->execute([$eval['id'], $eval['cycle_id']]);
$texts = $text_responses->fetchAll();

// إعدادات الشركة
$company_name = $system_settings['company_name'] ?? 'شركة البراق للنقل الجوي';
$logo_base64 = '';
$logo_path = '../storage/uploads/' . ($system_settings['logo_path'] ?? 'logo.png');
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}

// 2. تصميم محتوى PDF (HTML)
$html = '
<html>
<head>
    <style>
        body { font-family: sans-serif; direction: rtl; text-align: right; }
        .header { width: 100%; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
        .header td { vertical-align: middle; }
        .title { font-size: 20px; font-weight: bold; text-align: center; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { border: 1px solid #ddd; padding: 8px; }
        .info-label { background-color: #f5f5f5; font-weight: bold; width: 25%; }
        .score-box { text-align: center; border: 2px solid #0d6efd; padding: 15px; margin: 20px 0; background-color: #f8f9fa; }
        .score-val { font-size: 24px; font-weight: bold; color: #0d6efd; }
        .table-scores { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table-scores th { background-color: #333; color: white; padding: 10px; border: 1px solid #333; }
        .table-scores td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td width="30%">
            <strong>' . htmlspecialchars($company_name) . '</strong><br>
            إدارة الموارد البشرية
        </td>
        <td width="40%" class="title">تقرير تقييم الأداء السنوي<br><small>لسنة ' . $eval['year'] . '</small></td>
        <td width="30%" class="text-left">
            ' . ($logo_base64 ? '<img src="' . $logo_base64 . '" width="100">' : '') . '
        </td>
    </tr>
</table>

<h3>أولاً: بيانات الموظف</h3>
<table class="info-table">
    <tr>
        <td class="info-label">اسم الموظف</td>
        <td>' . htmlspecialchars($eval['employee_name']) . '</td>
        <td class="info-label">الرقم الوظيفي</td>
        <td>' . $eval['employee_id'] . '</td>
    </tr>
    <tr>
        <td class="info-label">الوظيفة</td>
        <td>' . htmlspecialchars($eval['job_title'] ?? '-') . '</td>
        <td class="info-label">الإدارة</td>
        <td>' . htmlspecialchars($eval['dept_name'] ?? '-') . '</td>
    </tr>
    <tr>
        <td class="info-label">تاريخ التقييم</td>
        <td>' . date('Y-m-d', strtotime($eval['created_at'])) . '</td>
        <td class="info-label">المُقيّم (المدير)</td>
        <td>' . htmlspecialchars($eval['evaluator_name']) . '</td>
    </tr>
</table>

<div class="score-box">
    النتيجة النهائية المعتمدة
    <br>
    <span class="score-val">' . ($eval['total_score'] ?? '0') . '%</span>
</div>

<h3>ثانياً: تفاصيل التقييم</h3>
<table class="table-scores">
    <thead>
        <tr>
            <th width="60%">معيار التقييم</th>
            <th width="20%">الدرجة المستحقة</th>
            <th width="20%">الدرجة القصوى</th>
        </tr>
    </thead>
    <tbody>';
    
    foreach ($details as $d) {
        $html .= '
        <tr>
            <td class="text-right">' . htmlspecialchars($d['title_ar']) . '</td>
            <td>' . $d['score'] . '</td>
            <td>' . $d['max_score'] . '</td>
        </tr>';
    }

$html .= '
        <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td class="text-right">المجموع</td>
            <td>' . ($eval['total_score'] ?? '0') . '</td>
            <td>100</td>
        </tr>
    </tbody>
</table>';

if (!empty($texts)) {
    $html .= '<h3>ثالثاً: الملاحظات والتوصيات</h3>';
    foreach ($texts as $t) {
        if (!empty($t['response_text'])) {
            $html .= '<div style="margin-bottom: 10px; border:1px solid #ddd; padding:10px;">
                <strong>' . htmlspecialchars($t['title_ar']) . ':</strong><br>
                ' . nl2br(htmlspecialchars($t['response_text'])) . '
            </div>';
        }
    }
}

$html .= '
<br><br>
<table width="100%" style="margin-top: 50px;">
    <tr>
        <td width="33%" align="center">توقيع الموظف<br><br>...................</td>
        <td width="33%" align="center">مدير الإدارة<br><br>...................</td>
        <td width="33%" align="center">اعتماد الموارد البشرية<br><br>...................</td>
    </tr>
</table>

</body>
</html>';

// 3. توليد PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8', 
    'format' => 'A4',
    'autoScriptToLang' => true, // مهم للعربية
    'autoLangToFont' => true    // مهم للعربية
]);

$mpdf->SetDirectionality('rtl');
$mpdf->WriteHTML($html);

// تحديد اسم الملف
$filename = 'Evaluation_' . $eval['employee_id'] . '_' . $eval['year'] . '.pdf';

// إذا كان الطلب للإرسال بالبريد (داخلي)، نرجع المحتوى كنص
if (isset($return_as_string) && $return_as_string === true) {
    return $mpdf->Output('', 'S');
}

// وإلا، نقوم بتنزيل الملف
$mpdf->Output($filename, 'D');
exit;
?>