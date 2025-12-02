<?php
// تأكد من أن ملف db.php يقوم بإنشاء اتصال PDO ويخزنه في المتغير $pdo
require_once '../app/core/db.php';

// 1. التحقق الأساسي من وجود التوكن
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die('رابط غير صالح');
}

$token = $_GET['token'];

// 2. جلب التقييم الأساسي (تقييم الموظف) والبيانات المرتبطة
$stmt = $pdo->prepare("
    SELECT e.*, u.name as employee_name, c.year, c.id as cycle_id,
           u.job_title, d.name_ar as dept_name
    FROM employee_evaluation_links l
    JOIN users u ON l.employee_id = u.id
    JOIN evaluation_cycles c ON l.cycle_id = c.id
    JOIN employee_evaluations e ON e.employee_id = u.id AND e.cycle_id = c.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE l.unique_token = ?
");
$stmt->execute([$token]);
$eval = $stmt->fetch();

// التحقق من وجود التقييم قبل أي معالجة
if (!$eval) {
    die('لا يوجد تقييم مرتبط بهذا الرابط.');
}

// 3. معالجة طلبات POST للموافقة أو الرفض
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        // تحديث حالة التقييم إلى 'approved'
        $update_stmt = $pdo->prepare("UPDATE employee_evaluations SET status = 'approved', accepted_at = NOW() WHERE id = ?");
        $update_stmt->execute([$eval['id']]);
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
        $evaluators = $pdo->query("SELECT id FROM users WHERE role = 'evaluator'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($evaluators as $ev_id) {
            $notification_title_eval = "تمت الموافقة على تقييم موظف";
            $notification_message_eval = "تمت الموافقة على تقييم {$eval['employee_name']} من قبل {$eval['evaluator_name']}.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'info')
            ")->execute([$ev_id, $notification_title_eval, $notification_message_eval]);
        }

    } elseif ($action === 'reject') {
        // تحديث حالة التقييم إلى 'rejected'
        $update_stmt = $pdo->prepare("UPDATE employee_evaluations SET status = 'rejected' WHERE id = ?");
        $update_stmt->execute([$eval['id']]);
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
        $evaluators = $pdo->query("SELECT id FROM users WHERE role = 'evaluator'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($evaluators as $ev_id) {
            $notification_title_eval = "تم رفض تقييم موظف";
            $notification_message_eval = "تم رفض تقييم {$eval['employee_name']} من قبل {$eval['evaluator_name']}.";
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'warning')
            ")->execute([$ev_id, $notification_title_eval, $notification_message_eval]);
        }
    }

    // إعادة تحميل الصفحة بعد المعالجة لتحديث الحالة
    header("Location: view-evaluation.php?token=" . urlencode($token));
    exit;
}

// 4. إعادة جلب التقييم الأساسي بعد معالجة الـ POST للتأكد من الحالة الجديدة
if ($_POST) {
    $stmt->execute([$token]);
    $eval = $stmt->fetch();
}


// جلب تقييم الرئيس المباشر
$supervisor_eval_stmt = $pdo->prepare("
    SELECT e.*, ev.name as evaluator_name
    FROM employee_evaluations e
    JOIN users ev ON e.evaluator_id = ev.id
    WHERE e.employee_id = ? AND e.cycle_id = ? AND e.evaluator_role = 'supervisor'
");
$supervisor_eval_stmt->execute([$eval['employee_id'], $eval['cycle_id']]);
$supervisor_eval = $supervisor_eval_stmt->fetch();

// جلب تقييم مدير الإدارة
$manager_eval_stmt = $pdo->prepare("
    SELECT e.*, ev.name as evaluator_name
    FROM employee_evaluations e
    JOIN users ev ON e.evaluator_id = ev.id
    WHERE e.employee_id = ? AND e.cycle_id = ? AND e.evaluator_role = 'manager'
");
$manager_eval_stmt->execute([$eval['employee_id'], $eval['cycle_id']]);
$manager_eval = $manager_eval_stmt->fetch();

// جلب المجالات الرقمية (مرتبة حسب الترتيب)
$fields = $pdo->prepare("SELECT * FROM evaluation_fields WHERE cycle_id = ? ORDER BY order_index");
$fields->execute([$eval['cycle_id']]);
$fields = $fields->fetchAll();

// جلب التفاصيل الرقمية لكل تقييم
function getEvaluationDetails($pdo, $evaluation_id) {
    $responses = $pdo->prepare("
        SELECT r.score, f.title_ar, f.max_score
        FROM evaluation_responses r
        JOIN evaluation_fields f ON r.field_id = f.id
        WHERE r.evaluation_id = ?
        ORDER BY f.order_index
    ");
    $responses->execute([$evaluation_id]);
    return $responses->fetchAll(PDO::FETCH_ASSOC);
}

// دالة جلب نقاط القوة والضعف
function getStrengthsWeaknesses($pdo, $evaluation_id) {
    $stmt_s = $pdo->prepare("SELECT description FROM strengths_weaknesses WHERE evaluation_id = ? AND type = 'strength'");
    $stmt_s->execute([$evaluation_id]);
    $strengths = $stmt_s->fetchAll();

    $stmt_w = $pdo->prepare("SELECT description FROM strengths_weaknesses WHERE evaluation_id = ? AND type = 'weakness'");
    $stmt_w->execute([$evaluation_id]);
    $weaknesses = $stmt_w->fetchAll();

    return ['strengths' => $strengths, 'weaknesses' => $weaknesses];
}

$supervisor_scores = $supervisor_eval ? getEvaluationDetails($pdo, $supervisor_eval['id']) : [];
$manager_scores = $manager_eval ? getEvaluationDetails($pdo, $manager_eval['id']) : [];

$supervisor_sw = $supervisor_eval ? getStrengthsWeaknesses($pdo, $supervisor_eval['id']) : ['strengths' => [], 'weaknesses' => []];
$manager_sw = $manager_eval ? getStrengthsWeaknesses($pdo, $manager_eval['id']) : ['strengths' => [], 'weaknesses' => []];

// جلب المجالات النصية
function getCustomTextResponses($pdo, $evaluation_id, $cycle_id) {
    $text_fields = $pdo->prepare("
        SELECT tf.title_ar, tr.response_text
        FROM evaluation_custom_text_fields tf
        LEFT JOIN evaluation_custom_text_responses tr ON tf.id = tr.field_id AND tr.evaluation_id = ?
        WHERE tf.cycle_id = ?
        ORDER BY tf.order_index
    ");
    $text_fields->execute([$evaluation_id, $cycle_id]);
    return $text_fields->fetchAll();
}

$supervisor_text = $supervisor_eval ? getCustomTextResponses($pdo, $supervisor_eval['id'], $eval['cycle_id']) : [];
$manager_text = $manager_eval ? getCustomTextResponses($pdo, $manager_eval['id'], $eval['cycle_id']) : [];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقييم الأداء - <?= htmlspecialchars($eval['employee_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container mt-4">
    <div class="text-center mb-4">
        <h3>تقييم الأداء السنوي</h3>
        <p><strong>الموظف:</strong> <?= htmlspecialchars($eval['employee_name']) ?></p>

        <!-- إضافة الوظيفة والإدارة -->
        <div class="d-flex justify-content-center align-items-center gap-3 mt-2">
            <?php if ($eval['job_title']): ?>
                <span class="badge bg-primary"><?= htmlspecialchars($eval['job_title']) ?></span>
            <?php endif; ?>

            <?php if ($eval['dept_name']): ?>
                <span class="badge bg-secondary"><?= htmlspecialchars($eval['dept_name']) ?></span>
            <?php endif; ?>
        </div>

        <p class="mt-2"><strong>السنة:</strong> <?= $eval['year'] ?></p>
    </div>

    <?php if ($eval['status'] == 'rejected'): ?>
    <div class="alert alert-danger text-center">
        <i class="fas fa-exclamation-circle"></i> تم رفض هذا التقييم.
        <br>
        يمكنك تعديله من لوحة التحكم الخاصة بالمسؤول أو الرئيس المباشر.
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-2"></i> تقييمك من قبل رئيسك المباشر ومدير إدارتك</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>المجال</th>
                            <?php if ($supervisor_eval): ?>
                            <th>رئيسك المباشر<br>(<?= htmlspecialchars($supervisor_eval['evaluator_name']) ?>)</th>
                            <?php endif; ?>
                            <th>مدير إدارتك<br>(<?= $manager_eval ? htmlspecialchars($manager_eval['evaluator_name']) : '—' ?>)</th>
                            <th>الدرجة الكاملة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?= htmlspecialchars($field['title_ar']) ?></td>
                            <?php if ($supervisor_eval): ?>
                            <td>
                                <?php
                                $score_sup = '—';
                                $max_sup = $field['max_score'];
                                foreach ($supervisor_scores as $d) {
                                    if ($d['title_ar'] === $field['title_ar']) {
                                        $score_sup = $d['score'];
                                        break;
                                    }
                                }
                                ?>
                                <?= $score_sup ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php
                                $score_man = '—';
                                $max_man = $field['max_score'];
                                foreach ($manager_scores as $d) {
                                    if ($d['title_ar'] === $field['title_ar']) {
                                        $score_man = $d['score'];
                                        break;
                                    }
                                }
                                ?>
                                <?= $score_man ?>
                            </td>
                            <td>
                                <?= $max_man ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-success">
                            <td><strong>المجموع الكلي</strong></td>
                            <?php if ($supervisor_eval): ?>
                            <td>
                                <strong><?= $supervisor_eval ? $supervisor_eval['total_score'] : '—' ?></strong>
                            </td>
                            <?php endif; ?>
                            <td>
                                <strong><?= $manager_eval ? $manager_eval['total_score'] : '—' ?></strong>
                            </td>
                            <td>
                                <strong>100</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($supervisor_text || $manager_text): ?>
            <div class="mt-4">
                <h5>ملاحظات إضافية</h5>
                <div class="row">
                    <?php if ($supervisor_eval): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                من رئيسك المباشر
                            </div>
                            <div class="card-body">
                                <?php if ($supervisor_text): ?>
                                    <?php foreach ($supervisor_text as $tr): ?>
                                        <?php if (!empty($tr['response_text'])): ?>
                                        <div class="mb-3">
                                            <strong><?= htmlspecialchars($tr['title_ar']) ?>:</strong>
                                            <div class="form-control" style="min-height: 100px; background: #f8f9fa; border: 1px solid #ced4da;">
                                                <?= nl2br(htmlspecialchars($tr['response_text'])) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">لا توجد ملاحظات.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="<?= $supervisor_eval ? 'col-md-6' : 'col-md-12' ?>">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                من مدير إدارتك
                            </div>
                            <div class="card-body">
                                <?php if ($manager_text): ?>
                                    <?php foreach ($manager_text as $tr): ?>
                                        <?php if (!empty($tr['response_text'])): ?>
                                        <div class="mb-3">
                                            <strong><?= htmlspecialchars($tr['title_ar']) ?>:</strong>
                                            <div class="form-control" style="min-height: 100px; background: #f8f9fa; border: 1px solid #ced4da;">
                                                <?= nl2br(htmlspecialchars($tr['response_text'])) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">لا توجد ملاحظات.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($eval['status'] == 'submitted'): ?>
    <div class="text-center">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success mx-2">أوافق</button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn btn-danger mx-2">أرفض</button>
        </form>
    </div>
    <?php elseif ($eval['status'] == 'approved'): ?>
    <div class="alert alert-success text-center">تم الموافقة على التقييم.</div>
    <?php elseif ($eval['status'] == 'rejected'): ?>
    <div class="alert alert-danger text-center">تم رفض التقييم.</div>
    <?php else: ?>
    <div class="alert alert-info text-center">تمت الموافقة أو الرفض مسبقًا.</div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>