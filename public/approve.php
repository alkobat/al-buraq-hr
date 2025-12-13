<?php
// بدء الجلسة للـ CSRF protection
session_start();

require_once '../app/core/db.php';
require_once '../app/core/EvaluationCalculator.php';

if (!isset($_GET['token']) || empty($_GET['token'])) {
    die('رابط غير صالح');
}

// توليد CSRF token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

$token = $_GET['token'];
$stmt = $pdo->prepare("
    SELECT e.*, u.name as employee_name, c.year, c.id as cycle_id
    FROM employee_evaluation_links l
    JOIN users u ON l.employee_id = u.id
    JOIN evaluation_cycles c ON l.cycle_id = c.id
    JOIN employee_evaluations e ON e.employee_id = u.id AND e.cycle_id = c.id
    WHERE l.unique_token = ?
");
$stmt->execute([$token]);
$eval = $stmt->fetch();

if (!$eval) {
    die('لا يوجد تقييم مرتبط بهذا الرابط.');
}

// جلب تفاصيل التقييم
$responses = $pdo->prepare("
    SELECT r.score, f.title_ar, f.max_score 
    FROM evaluation_responses r
    JOIN evaluation_fields f ON r.field_id = f.id
    WHERE r.evaluation_id = ?
    ORDER BY f.order_index
");
$responses->execute([$eval['id']]);
$details = $responses->fetchAll();

// جلب نقاط القوة والضعف (إن وجدت) - إصلاح SQL Injection
$stmt_strengths = $pdo->prepare("SELECT description FROM strengths_weaknesses WHERE evaluation_id = ? AND type = 'strength'");
$stmt_strengths->execute([$eval['id']]);
$strengths = $stmt_strengths->fetchAll();

$stmt_weaknesses = $pdo->prepare("SELECT description FROM strengths_weaknesses WHERE evaluation_id = ? AND type = 'weakness'");
$stmt_weaknesses->execute([$eval['id']]);
$weaknesses = $stmt_weaknesses->fetchAll();

// جلب المجالات النصية الجديدة
$text_fields = $pdo->prepare("
    SELECT tf.title_ar, tr.response_text
    FROM evaluation_custom_text_fields tf
    LEFT JOIN evaluation_custom_text_responses tr ON tf.id = tr.field_id
    WHERE tf.cycle_id = ? AND tr.evaluation_id = ?
    ORDER BY tf.order_index
");
$text_fields->execute([$eval['cycle_id'], $eval['id']]);
$text_responses = $text_fields->fetchAll();

// === معالجة الموافقة أو الرفض ===
if ($_POST && isset($_POST['action'])) {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        die('خطأ أمني: طلب غير صالح (CSRF token mismatch)');
    }
    
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // لا حاجة لتغيير الحالة، فهي بالفعل "submitted"
        // يمكن تركها كما هي
        
        // === إرسال إشعار للموظف ===
        $notification_title = "تمت الموافقة على تقييمك";
        $notification_message = "تمت الموافقة على تقييمك من قبل {$eval['evaluator_name']} في دورة {$eval['year']}.";
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'success')
        ")->execute([$eval['employee_id'], $notification_title, $notification_message]);
        
        // === إرسال إشعار لمدير الإدارة (إن وجد) ===
        if ($eval['manager_id']) {
            $notification_title_manager = "تمت الموافقة على تقييم موظف تحت إدارتك";
            $notification_message_manager = "تمت الموافقة على تقييم {$eval['employee_name']} من قبل {$eval['evaluator_name']}.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'info')
            ")->execute([$eval['manager_id'], $notification_title_manager, $notification_message_manager]);
        }
        
        // === إرسال إشعار لمسؤول التقييمات ===
        $stmt_evaluators = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt_evaluators->execute(['evaluator']);
        $evaluators = $stmt_evaluators->fetchAll(PDO::FETCH_COLUMN);
        foreach ($evaluators as $ev_id) {
            $notification_title_eval = "تمت الموافقة على تقييم موظف";
            $notification_message_eval = "تمت الموافقة على تقييم {$eval['employee_name']} من قبل {$eval['evaluator_name']}.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'info')
            ")->execute([$ev_id, $notification_title_eval, $notification_message_eval]);
        }
        
        // إعادة توليد CSRF token بعد معالجة الطلب
        unset($_SESSION['csrf_token']);
        
        header("Location: approve.php?token=$token&action=approved");
        exit;
    } elseif ($action === 'reject') {
        // إعادة التقييم إلى حالة مسودة
        $pdo->prepare("UPDATE employee_evaluations SET status = 'draft' WHERE id = ?")->execute([$eval['id']]);
        
        // === إرسال إشعار للموظف ===
        $notification_title = "تم رفض تقييمك";
        $notification_message = "تم رفض تقييمك من قبل {$eval['evaluator_name']} في دورة {$eval['year']}. يمكنك تعديله وإعادة إرساله.";
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'danger')
        ")->execute([$eval['employee_id'], $notification_title, $notification_message]);
        
        // === إرسال إشعار لمدير الإدارة (إن وجد) ===
        if ($eval['manager_id']) {
            $notification_title_manager = "تم رفض تقييم موظف تحت إدارتك";
            $notification_message_manager = "تم رفض تقييم {$eval['employee_name']} من قبل {$eval['evaluator_name']}.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'warning')
            ")->execute([$eval['manager_id'], $notification_title_manager, $notification_message_manager]);
        }
        
        // === إرسال إشعار لمسؤول التقييمات ===
        $stmt_evaluators_reject = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt_evaluators_reject->execute(['evaluator']);
        $evaluators = $stmt_evaluators_reject->fetchAll(PDO::FETCH_COLUMN);
        foreach ($evaluators as $ev_id) {
            $notification_title_eval = "تم رفض تقييم موظف";
            $notification_message_eval = "تم رفض تقييم {$eval['employee_name']} من قبل {$eval['evaluator_name']}.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'warning')
            ")->execute([$ev_id, $notification_title_eval, $notification_message_eval]);
        }
        
        // إعادة توليد CSRF token بعد معالجة الطلب
        unset($_SESSION['csrf_token']);
        
        header("Location: approve.php?token=$token&action=rejected");
        exit;
    }
}

// جلب تقييمات المدير والمشرف وحساب التقييم النهائي
$calculator = new EvaluationCalculator($pdo);
$employee_id = $pdo->query("SELECT employee_id FROM employee_evaluation_links WHERE unique_token = " . $pdo->quote($token))->fetchColumn();
$scores = $calculator->getEmployeeScores($employee_id, $eval['cycle_id']);
$final_score = $scores['final_score'];
$manager_score = $scores['manager_score'];
$supervisor_score = $scores['supervisor_score'];
$evaluation_method = $scores['method'];
$method_name = $calculator->getMethodName($evaluation_method);

// === عرض رسالة نجاح أو خطأ ===
$success_msg = '';
$error_msg = '';
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'approved') {
        $success_msg = 'تمت الموافقة على التقييم.';
    } elseif ($_GET['action'] === 'rejected') {
        $success_msg = 'تم رفض التقييم.';
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>موافقة على التقييم - <?= htmlspecialchars($eval['employee_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container mt-4">
    <div class="text-center mb-4">
        <h3>موافقة على تقييم الأداء</h3>
        <p><strong>الموظف:</strong> <?= htmlspecialchars($eval['employee_name']) ?></p>
        <p><strong>السنة:</strong> <?= $eval['year'] ?></p>
    </div>

    <!-- رسالة نجاح أو خطأ -->
    <?php if ($success_msg): ?>
    <div class="alert alert-<?= $_GET['action'] === 'approved' ? 'success' : 'danger' ?> text-center">
        <i class="fas fa-<?= $_GET['action'] === 'approved' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
        <?= htmlspecialchars($success_msg) ?>
    </div>
    <?php endif; ?>

    <!-- رسالة عند رفض التقييم -->
    <?php if ($eval['status'] == 'rejected'): ?>
    <div class="alert alert-danger text-center">
        <i class="fas fa-exclamation-circle"></i> تم رفض هذا التقييم.
        <br>
        يمكنك تعديله من لوحة التحكم الخاصة بالمسؤول أو الرئيس المباشر.
    </div>
    <?php endif; ?>

    <div class="card mb-4 border-primary">
        <div class="card-body text-center">
            <h5 class="text-primary fw-bold mb-3">النتيجة النهائية</h5>
            
            <div class="row mb-3">
                <div class="col-md-6 mb-2">
                    <div class="p-2 border rounded">
                        <small class="text-muted d-block">تقييم المشرف المباشر</small>
                        <h4 class="fw-bold text-info m-0"><?= $supervisor_score !== null ? $supervisor_score . '%' : '—' ?></h4>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="p-2 border rounded">
                        <small class="text-muted d-block">تقييم مدير الإدارة</small>
                        <h4 class="fw-bold text-primary m-0"><?= $manager_score !== null ? $manager_score . '%' : '—' ?></h4>
                    </div>
                </div>
            </div>
            
            <div class="border-top pt-3">
                <?php if ($final_score !== null): ?>
                    <h2 class="fw-bold text-success m-0"><?= $final_score ?> %</h2>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle"></i> 
                        طريقة الحساب: <strong><?= htmlspecialchars($method_name) ?></strong>
                    </small>
                <?php else: ?>
                    <?php if ($evaluation_method === 'average'): ?>
                        <h2 class="fw-bold text-warning m-0">غير مكتمل</h2>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-exclamation-triangle"></i>
                             في انتظار تقييم الرئيس المباشر
                        </small>
                    <?php else: ?>
                        <h2 class="fw-bold text-secondary m-0">--</h2>
                        <small class="text-muted">لم يتم اعتماد الدرجة بعد</small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">النتائج التفصيلية</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead><tr><th>المجال</th><th>الدرجة</th><th>من أصل</th></tr></thead>
                <tbody>
                    <?php foreach ($details as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['title_ar']) ?></td>
                        <td><?= $d['score'] ?></td>
                        <td><?= $d['max_score'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-success">
                        <td colspan="2"><strong>المجموع الكلي</strong></td>
                        <td><strong><?= $eval['total_score'] ?>/100</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- عرض نقاط القوة والضعف -->
    <?php if ($strengths): ?>
    <div class="card mb-3">
        <div class="card-header bg-success text-white">نقاط القوة</div>
        <div class="card-body">
            <ul>
                <?php foreach ($strengths as $s): ?>
                <li><?= htmlspecialchars($s['description']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($weaknesses): ?>
    <div class="card mb-3">
        <div class="card-header bg-warning text-dark">نقاط الضعف</div>
        <div class="card-body">
            <ul>
                <?php foreach ($weaknesses as $w): ?>
                <li><?= htmlspecialchars($w['description']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- عرض المجالات النصية -->
    <?php if ($text_responses): ?>
        <h5 class="mt-4">ملاحظات إضافية</h5>
        <?php foreach ($text_responses as $tr): ?>
            <?php if (!empty($tr['response_text'])): ?>
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <?= htmlspecialchars($tr['title_ar']) ?>
                </div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($tr['response_text'])) ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- زر موافقة أو رفض -->
    <?php if ($eval['status'] == 'submitted'): ?>
    <div class="text-center">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success mx-2">
                <i class="fas fa-check-circle"></i> أوفق
            </button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn btn-danger mx-2">
                <i class="fas fa-times-circle"></i> أرفض
            </button>
        </form>
    </div>
    <?php elseif ($eval['status'] == 'approved'): ?>
    <div class="alert alert-success text-center">تمت الموافقة على التقييم.</div>
    <?php elseif ($eval['status'] == 'rejected'): ?>
    <div class="alert alert-danger text-center">تم رفض التقييم.</div>
    <?php else: ?>
    <div class="alert alert-info text-center">تمت الموافقة أو الرفض مسبقًا.</div>
    <?php endif; ?>
</div>
</body>
</html>