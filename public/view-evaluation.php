<?php
// بدء الجلسة للـ CSRF protection
session_start();

// تأكد من أن ملف db.php يقوم بإنشاء اتصال PDO ويخزنه في المتغير $pdo
require_once '../app/core/db.php';

// توليد CSRF token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// 1. التحقق الأساسي من وجود التوكن
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die('رابط غير صالح');
}

$token = $_GET['token'];

// 2. جلب التقييم الأساسي
$stmt = $pdo->prepare("
    SELECT e.*, u.name as employee_name, c.year, c.id as cycle_id,
           u.job_title, d.name_ar as dept_name,
           l.expires_at,
           ev.name as evaluator_name, u.manager_id, u.id as employee_id 
    FROM employee_evaluation_links l
    JOIN users u ON l.employee_id = u.id
    JOIN evaluation_cycles c ON l.cycle_id = c.id
    JOIN employee_evaluations e ON e.employee_id = u.id AND e.cycle_id = c.id
    LEFT JOIN departments d ON u.department_id = d.id
    JOIN users ev ON e.evaluator_id = ev.id
    WHERE l.unique_token = ?
");
$stmt->execute([$token]);
$eval = $stmt->fetch();

if (!$eval) {
    die('لا يوجد تقييم مرتبط بهذا الرابط.');
}

if ($eval['expires_at'] && strtotime($eval['expires_at']) < time()) {
    die('انتهت صلاحية رابط التقييم.');
}


// 3. معالجة طلبات POST
if ($_POST && isset($_POST['action'])) {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        die('خطأ أمني: طلب غير صالح (CSRF token mismatch)');
    }
    
    $action = $_POST['action'];

    if ($action === 'approve') {
        $update_stmt = $pdo->prepare("UPDATE employee_evaluations SET status = 'approved', accepted_at = NOW() WHERE id = ?");
        $update_stmt->execute([$eval['id']]);
        
        $notification_title = "تمت الموافقة على تقييمك";
        $notification_message = "تمت الموافقة على تقييمك من قبل {$eval['evaluator_name']} في دورة {$eval['year']}.";
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')")->execute([$eval['employee_id'], $notification_title, $notification_message]);
        
        if ($eval['manager_id']) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')")->execute([$eval['manager_id'], "تمت الموافقة على تقييم موظف", "وافق {$eval['employee_name']} على التقييم."]);
        }
        
        $stmt_evaluators = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt_evaluators->execute(['evaluator']);
        $evaluators = $stmt_evaluators->fetchAll(PDO::FETCH_COLUMN);
        foreach ($evaluators as $ev_id) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')")->execute([$ev_id, "تمت الموافقة على تقييم موظف", "وافق {$eval['employee_name']} على التقييم."]);
        }

    } elseif ($action === 'reject') {
        $update_stmt = $pdo->prepare("UPDATE employee_evaluations SET status = 'rejected' WHERE id = ?");
        $update_stmt->execute([$eval['id']]);
        
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'danger')")->execute([$eval['employee_id'], "تم رفض تقييمك", "رفضت التقييم."]);
        
        if ($eval['manager_id']) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'warning')")->execute([$eval['manager_id'], "تم رفض تقييم موظف", "رفض {$eval['employee_name']} التقييم."]);
        }
        $stmt_evaluators_reject = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt_evaluators_reject->execute(['evaluator']);
        $evaluators = $stmt_evaluators_reject->fetchAll(PDO::FETCH_COLUMN);
        foreach ($evaluators as $ev_id) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'warning')")->execute([$ev_id, "تم رفض تقييم موظف", "رفض {$eval['employee_name']} التقييم."]);
        }
    }

    // إعادة توليد CSRF token بعد معالجة الطلب
    unset($_SESSION['csrf_token']);
    
    header("Location: view-evaluation.php?token=" . urlencode($token));
    exit;
}

if ($_POST) {
    $stmt->execute([$token]);
    $eval = $stmt->fetch();
}

// جلب التقييمات الفرعية
$supervisor_eval_stmt = $pdo->prepare("SELECT e.*, ev.name as evaluator_name FROM employee_evaluations e JOIN users ev ON e.evaluator_id = ev.id WHERE e.employee_id = ? AND e.cycle_id = ? AND e.evaluator_role = 'supervisor'");
$supervisor_eval_stmt->execute([$eval['employee_id'], $eval['cycle_id']]);
$supervisor_eval = $supervisor_eval_stmt->fetch();

$manager_eval_stmt = $pdo->prepare("SELECT e.*, ev.name as evaluator_name FROM employee_evaluations e JOIN users ev ON e.evaluator_id = ev.id WHERE e.employee_id = ? AND e.cycle_id = ? AND e.evaluator_role = 'manager'");
$manager_eval_stmt->execute([$eval['employee_id'], $eval['cycle_id']]);
$manager_eval = $manager_eval_stmt->fetch();

$fields = $pdo->prepare("SELECT * FROM evaluation_fields WHERE cycle_id = ? ORDER BY order_index");
$fields->execute([$eval['cycle_id']]);
$fields = $fields->fetchAll();

function getEvaluationDetails($pdo, $evaluation_id) {
    $responses = $pdo->prepare("SELECT r.score, f.title_ar FROM evaluation_responses r JOIN evaluation_fields f ON r.field_id = f.id WHERE r.evaluation_id = ?");
    $responses->execute([$evaluation_id]);
    return $responses->fetchAll(PDO::FETCH_ASSOC);
}

$supervisor_scores = $supervisor_eval ? getEvaluationDetails($pdo, $supervisor_eval['id']) : [];
$manager_scores = $manager_eval ? getEvaluationDetails($pdo, $manager_eval['id']) : [];

function getCustomTextResponses($pdo, $evaluation_id, $cycle_id) {
    $text_fields = $pdo->prepare("SELECT tf.title_ar, tr.response_text FROM evaluation_custom_text_fields tf LEFT JOIN evaluation_custom_text_responses tr ON tf.id = tr.field_id AND tr.evaluation_id = ? WHERE tf.cycle_id = ? ORDER BY tf.order_index");
    $text_fields->execute([$evaluation_id, $cycle_id]);
    return $text_fields->fetchAll();
}

$supervisor_text = $supervisor_eval ? getCustomTextResponses($pdo, $supervisor_eval['id'], $eval['cycle_id']) : [];
$manager_text = $manager_eval ? getCustomTextResponses($pdo, $manager_eval['id'], $eval['cycle_id']) : [];

$approved_score = $manager_eval['total_score'] ?? '—';
$is_approved_score_available = isset($manager_eval['total_score']);

$company_name = $system_settings['company_name'] ?? 'شركة البراق للنقل الجوي';
$logo_path = $system_settings['logo_path'] ?? 'logo.png';
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقييم الأداء - <?= htmlspecialchars($eval['employee_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* === تخصيص الطباعة (A4) === */
        @media print {
            @page {
                size: A4;
                margin: 10mm 15mm; /* هوامش: أعلى/أسفل 10مم، يمين/يسار 15مم */
            }
            body {
                background-color: white !important;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                -webkit-print-color-adjust: exact !important; /* لطباعة الألوان الخلفية */
                print-color-adjust: exact !important;
            }
            .container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            .no-print { display: none !important; }
            
            /* إخفاء ظلال وحواف البطاقات لتظهر نظيفة */
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                break-inside: avoid; /* يمنع انقسام البطاقة بين صفحتين */
                margin-bottom: 20px !important;
            }
            .card-header {
                background-color: #f8f9fa !important;
                border-bottom: 1px solid #ddd !important;
                color: black !important;
                font-weight: bold;
            }
            
            /* تنسيق الجدول */
            .table { margin-bottom: 0 !important; }
            .table-bordered th, .table-bordered td {
                border: 1px solid #000 !important;
                padding: 6px 10px !important;
            }
            .table-light, .bg-light { background-color: #f0f0f0 !important; }
            .table-dark { background-color: #333 !important; color: white !important; }
            
            /* الترويسة */
            .print-header {
                display: flex !important;
                justify-content: space-between;
                align-items: center;
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .print-header img { max-height: 80px; }
            
            /* قسم التوقيع */
            .signature-section {
                display: flex !important;
                justify-content: space-between;
                margin-top: 50px;
                break-inside: avoid;
            }
            .signature-box {
                width: 30%;
                text-align: center;
            }
            .signature-line {
                border-top: 1px solid #000;
                margin-top: 40px;
                width: 80%;
                margin-left: auto;
                margin-right: auto;
            }

            a { text-decoration: none !important; color: black !important; }
        }
        
        .print-header, .signature-section { display: none; }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="print-header">
        <div class="text-end">
            <h4><?= htmlspecialchars($company_name) ?></h4>
            <h6 class="text-muted">إدارة الموارد البشرية</h6>
        </div>
        <div class="text-center">
            <h3 class="fw-bold">نموذج تقييم الأداء السنوي</h3>
            <h5>لسنة: <?= $eval['year'] ?></h5>
        </div>
        <div class="text-start">
            <img src="../storage/uploads/<?= htmlspecialchars($logo_path) ?>" alt="Logo">
        </div>
    </div>

    <div class="text-center mb-4 no-print">
        <h3>تقييم الأداء السنوي</h3>
        <p><strong>الموظف:</strong> <?= htmlspecialchars($eval['employee_name']) ?></p>
        
        <div class="mt-3">
        
            <a href="generate_pdf.php?token=<?= htmlspecialchars($token) ?>" class="btn btn-danger">
    <i class="fas fa-file-pdf"></i> تحميل التقرير (PDF)
</a>
            
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header no-print">بيانات الموظف</div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <tr>
                    <td width="20%" class="bg-light fw-bold">اسم الموظف</td>
                    <td width="30%"><?= htmlspecialchars($eval['employee_name']) ?></td>
                    <td width="20%" class="bg-light fw-bold">الرقم الوظيفي</td>
                    <td><?= htmlspecialchars($eval['employee_id']) ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">المسمى الوظيفي</td>
                    <td><?= htmlspecialchars($eval['job_title'] ?? '—') ?></td>
                    <td class="bg-light fw-bold">الإدارة / القسم</td>
                    <td><?= htmlspecialchars($eval['dept_name'] ?? '—') ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">تاريخ التقييم</td>
                    <td><?= date('Y-m-d', strtotime($eval['created_at'])) ?></td>
                    <td class="bg-light fw-bold">تاريخ الاعتماد</td>
                    <td><?= $eval['accepted_at'] ? date('Y-m-d', strtotime($eval['accepted_at'])) : '—' ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if ($eval['status'] == 'rejected'): ?>
    <div class="alert alert-danger text-center no-print">
        <i class="fas fa-exclamation-circle"></i> تم رفض هذا التقييم.
    </div>
    <?php endif; ?>

    <div class="card mb-4 border-primary">
        <div class="card-body text-center pt-2 pb-2">
            <h5 class="text-primary fw-bold mb-1">النتيجة النهائية المعتمدة</h5>
            <?php if ($is_approved_score_available): ?>
                <h2 class="fw-bold text-success m-0"><?= $approved_score ?> %</h2>
            <?php else: ?>
                <h2 class="fw-bold text-secondary m-0">--</h2>
                <small class="text-muted">لم يتم اعتماد الدرجة بعد</small>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white no-print">
            <span><i class="fas fa-list me-2"></i> تفاصيل التقييم</span>
        </div>
        <div class="card-header d-none d-print-block">
            <span>تفاصيل درجات التقييم</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width: 40%; vertical-align: middle;">معيار التقييم</th>
                            <th style="vertical-align: middle;">تقييم الرئيس المباشر</th>
                            <th style="vertical-align: middle;">تقييم مدير الإدارة<br><small>(المعتمد)</small></th>
                            <th style="vertical-align: middle;">الدرجة القصوى</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?= htmlspecialchars($field['title_ar']) ?></td>
                            <td class="text-center">
                                <?php
                                $score_sup = '—';
                                foreach ($supervisor_scores as $d) {
                                    if ($d['title_ar'] === $field['title_ar']) { $score_sup = $d['score']; break; }
                                }
                                echo $score_sup;
                                ?>
                            </td>
                            <td class="text-center fw-bold bg-light">
                                <?php
                                $score_man = '—';
                                foreach ($manager_scores as $d) {
                                    if ($d['title_ar'] === $field['title_ar']) { $score_man = $d['score']; break; }
                                }
                                echo $score_man;
                                ?>
                            </td>
                            <td class="text-center text-muted">
                                <?= $field['max_score'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-dark text-white">
                            <td><strong>المجموع الكلي</strong></td>
                            <td class="text-center"><strong><?= $supervisor_eval ? $supervisor_eval['total_score'] : '—' ?></strong></td>
                            <td class="text-center"><strong><?= $manager_eval ? $manager_eval['total_score'] : '—' ?></strong></td>
                            <td class="text-center"><strong>100</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($supervisor_text || $manager_text): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">ملاحظات وتوصيات</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h6 class="text-decoration-underline">ملاحظات الرئيس المباشر:</h6>
                    <?php if ($supervisor_text): ?>
                        <?php foreach ($supervisor_text as $tr): ?>
                            <?php if (!empty($tr['response_text'])): ?>
                            <div class="mb-2">
                                <strong><?= htmlspecialchars($tr['title_ar']) ?>:</strong>
                                <p class="mb-1 text-muted"><?= nl2br(htmlspecialchars($tr['response_text'])) ?></p>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted small">لا توجد ملاحظات.</span>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3 border-start">
                    <h6 class="text-decoration-underline">ملاحظات مدير الإدارة:</h6>
                    <?php if ($manager_text): ?>
                        <?php foreach ($manager_text as $tr): ?>
                            <?php if (!empty($tr['response_text'])): ?>
                            <div class="mb-2">
                                <strong><?= htmlspecialchars($tr['title_ar']) ?>:</strong>
                                <p class="mb-1 text-dark"><?= nl2br(htmlspecialchars($tr['response_text'])) ?></p>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted small">لا توجد ملاحظات.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <p><strong>توقيع الموظف</strong></p>
            <p class="small text-muted">اطلعت على التقييم</p>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <p><strong>توقيع الرئيس المباشر</strong></p>
             <p class="small"><?= htmlspecialchars($supervisor_eval['evaluator_name'] ?? '') ?></p>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <p><strong>اعتماد مدير الإدارة</strong></p>
            <p class="small"><?= htmlspecialchars($manager_eval['evaluator_name'] ?? '') ?></p>
            <div class="signature-line"></div>
        </div>
    </div>

    <?php if ($eval['status'] == 'submitted'): ?>
    <div class="text-center no-print mt-4 mb-5">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success btn-lg mx-2"><i class="fas fa-check"></i> موافقة واعتماد</button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn btn-danger btn-lg mx-2"><i class="fas fa-times"></i> رفض وإعادة</button>
        </form>
    </div>
    <?php elseif ($eval['status'] == 'approved'): ?>
    <div class="alert alert-success text-center no-print mt-4"><strong>تمت الموافقة على هذا التقييم واعتماده رسمياً.</strong></div>
    <?php elseif ($eval['status'] == 'rejected'): ?>
    <div class="alert alert-danger text-center no-print mt-4"><strong>تم رفض هذا التقييم.</strong></div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>