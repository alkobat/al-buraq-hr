<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}
require_once '../../app/core/db.php';

$supervisor_id = $_SESSION['user_id'];
$employee_id = $_GET['employee'] ?? null;

if (!$employee_id) {
    header('Location: dashboard.php');
    exit;
}

// التحقق من أن الموظف تابع له
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND supervisor_id = ? AND role IN ('employee', 'evaluator')");
$stmt->execute([$employee_id, $supervisor_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die('ليس لديك صلاحية تقييم هذا الموظف.');
}

// جلب دورة التقييم النشطة
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();
if (!$active_cycle) {
    die('لا توجد دورة تقييم نشطة.');
}

// جلب المجالات الرقمية
$fields = $pdo->prepare("SELECT * FROM evaluation_fields WHERE cycle_id = ? ORDER BY order_index");
$fields->execute([$active_cycle['id']]);
$fields = $fields->fetchAll();

// جلب الحقول النصية المخصصة
$custom_text_fields = $pdo->prepare("SELECT * FROM evaluation_custom_text_fields WHERE cycle_id = ? ORDER BY order_index");
$custom_text_fields->execute([$active_cycle['id']]);
$custom_text_fields = $custom_text_fields->fetchAll();

// جلب التقييم الحالي (إن وجد)
$evaluation = $pdo->prepare("
    SELECT * FROM employee_evaluations 
    WHERE employee_id = ? AND evaluator_id = ? AND cycle_id = ? AND evaluator_role = 'supervisor'
");
$evaluation->execute([$employee_id, $supervisor_id, $active_cycle['id']]);
$evaluation = $evaluation->fetch();

$responses = [];
$custom_responses = [];
if ($evaluation) {
    $resp = $pdo->prepare("SELECT field_id, score FROM evaluation_responses WHERE evaluation_id = ?");
    $resp->execute([$evaluation['id']]);
    $responses = $resp->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $custom_resp = $pdo->prepare("SELECT field_id, response_text FROM evaluation_custom_text_responses WHERE evaluation_id = ?");
    $custom_resp->execute([$evaluation['id']]);
    $custom_responses = $custom_resp->fetchAll(PDO::FETCH_KEY_PAIR);
}

// إذا كان التقييم مُرسلًا (status != draft)، نعرضه فقط
$is_submitted = $evaluation && $evaluation['status'] !== 'draft';
$is_rejected = $evaluation && $evaluation['status'] === 'rejected';

// === معالجة الحفظ ===
if ($_POST) {
    $total_score = 0;
    $pdo->beginTransaction();
    try {
        if (!$evaluation) {
            $pdo->prepare("
                INSERT INTO employee_evaluations (employee_id, cycle_id, evaluator_id, evaluator_role, status)
                VALUES (?, ?, ?, 'supervisor', 'draft')
            ")->execute([$employee_id, $active_cycle['id'], $supervisor_id]);
            $evaluation_id = $pdo->lastInsertId();
        } else {
            $evaluation_id = $evaluation['id'];
            if ($evaluation['status'] !== 'draft') {
                $pdo->prepare("UPDATE employee_evaluations SET status = 'draft' WHERE id = ?")->execute([$evaluation_id]);
            }
        }

        // === حفظ المجالات الرقمية ===
        foreach ($fields as $field) {
            $score = (int)($_POST['score_' . $field['id']] ?? 0);
            $score = max(0, min($score, $field['max_score']));
            $total_score += $score;

            $pdo->prepare("
                INSERT INTO evaluation_responses (evaluation_id, field_id, score)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE score = ?
            ")->execute([$evaluation_id, $field['id'], $score, $score]);
        }

        // === حفظ الحقول النصية المخصصة ===
        foreach ($custom_text_fields as $ctf) {
            $response_text = trim($_POST['custom_text_' . $ctf['id']] ?? '');
            if ($response_text !== '') {
                $pdo->prepare("
                    INSERT INTO evaluation_custom_text_responses (evaluation_id, field_id, response_text)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE response_text = ?
                ")->execute([$evaluation_id, $ctf['id'], $response_text, $response_text]);
            } else {
                if ($ctf['is_required']) {
                    throw new Exception("الحقل '{$ctf['title_ar']}' مطلوب.");
                }
                $pdo->prepare("DELETE FROM evaluation_custom_text_responses WHERE evaluation_id = ? AND field_id = ?")
                    ->execute([$evaluation_id, $ctf['id']]);
            }
        }

        // === تحديث المجموع ===
        $pdo->prepare("UPDATE employee_evaluations SET total_score = ? WHERE id = ?")->execute([$total_score, $evaluation_id]);

        // === إنهاء التقييم أو حفظ مسودة ===
        if (isset($_POST['submit'])) {
            $pdo->prepare("UPDATE employee_evaluations SET status = 'submitted' WHERE id = ?")->execute([$evaluation_id]);
            
            // === إنشاء رابط التقييم تلقائيًّا ===
            $token_stmt = $pdo->prepare("SELECT unique_token FROM employee_evaluation_links WHERE employee_id = ? AND cycle_id = ?");
            $token_stmt->execute([$employee_id, $active_cycle['id']]);
            $existing_token = $token_stmt->fetchColumn();
            
            if (!$existing_token) {
                $new_token = bin2hex(random_bytes(16)); // UUID بسيط
                $pdo->prepare("
                    INSERT INTO employee_evaluation_links (employee_id, cycle_id, unique_token) 
                    VALUES (?, ?, ?)
                ")->execute([$employee_id, $active_cycle['id'], $new_token]);
            }
            
            $pdo->commit();
            header("Location: dashboard.php?msg=submitted");
        } else {
            $pdo->commit();
            header("Location: evaluate.php?employee=$employee_id&msg=saved");
        }
        exit;
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقييم موظف</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<nav class="admin-sidebar">
    <h5>الرئيس المباشر</h5>
    <a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
    <a href="evaluate.php" class="active"><i class="fas fa-star"></i> تقييم الموظفين</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> الإحصائيات</a>
    <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
</nav>

<main class="admin-main-content">
    <h3><i class="fas fa-star"></i> تقييم موظف</h3>
    <hr>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">تم <?= $_GET['msg'] === 'submitted' ? 'إرسال التقييم' : 'حفظ المسودة' ?> بنجاح.</div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-<?= $user_to_edit ? 'warning' : 'primary' ?> text-white">
            <h5>تقييم: <?= htmlspecialchars($employee['name']) ?> (دورة <?= $active_cycle['year'] ?>)</h5>
        </div>
        <div class="card-body">
            <?php if ($is_submitted && !$is_rejected): ?>
                <!-- عرض التقييم فقط -->
                <div class="mb-4">
                    <h5><i class="fas fa-eye"></i> نتائج التقييم</h5>
                    <hr>
                </div>

                <!-- المجالات الرقمية -->
                <h5>مجالات التقييم الرقمية</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>مجال التقييم</th>
                                <th>الدرجة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?= htmlspecialchars($field['title_ar']) ?></td>
                                <td><?= $responses[$field['id']] ?? '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-success">
                                <td colspan="1"><strong>المجموع الكلي</strong></td>
                                <td><strong><?= $evaluation['total_score'] ?? '—' ?>/100</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- الحقول النصية المخصصة -->
                <?php if (!empty($custom_text_fields)): ?>
                <h5 class="mt-4">حقول نصية إضافية</h5>
                <div class="row">
                    <?php foreach ($custom_text_fields as $ctf): ?>
                    <div class="col-md-12 mb-3">
                        <label><?= htmlspecialchars($ctf['title_ar']) ?> 
                            <?php if ($ctf['is_required']): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <div class="form-control" style="min-height: 100px; background: #f8f9fa; border: 1px solid #ced4da;">
                            <?= nl2br(htmlspecialchars($custom_responses[$ctf['id']] ?? '—')) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- مجموع الدرجات -->
                <div class="alert alert-info mt-3">
                    <strong>المجموع:</strong> <?= $evaluation['total_score'] ?? '—' ?>/100
                </div>

                <!-- زر عرض فقط -->
                <div class="mt-3">
                    <a href="dashboard.php" class="btn btn-secondary">عودة إلى لوحة التحكم</a>
                </div>

            <?php elseif ($is_rejected): ?>
                <!-- عرض التقييم مع إمكانية التعديل (بعد الرفض) -->
                <div class="mb-4">
                    <h5><i class="fas fa-exclamation-circle text-danger"></i> تم رفض التقييم</h5>
                    <p class="text-danger">يمكنك تعديله وإعادة إرساله.</p>
                    <hr>
                </div>

                <!-- نموذج التقييم (مع إمكانية التعديل) -->
                <form method="POST">
                    <!-- المجالات الرقمية -->
                    <h5>مجالات التقييم الرقمية</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>مجال التقييم</th>
                                    <th>الدرجة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><?= htmlspecialchars($field['title_ar']) ?></td>
                                    <td>
                                        <input type="number" 
                                               name="score_<?= $field['id'] ?>" 
                                               class="form-control" 
                                               min="0" 
                                               max="<?= $field['max_score'] ?>" 
                                               value="<?= $responses[$field['id']] ?? 0 ?>" 
                                               required
                                               title="الدرجة القصوى: <?= $field['max_score'] ?>">
                                        <small class="text-muted">من <?= $field['max_score'] ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- الحقول النصية المخصصة -->
                    <?php if (!empty($custom_text_fields)): ?>
                    <h5 class="mt-4">حقول نصية إضافية</h5>
                    <div class="row">
                        <?php foreach ($custom_text_fields as $ctf): ?>
                        <div class="col-md-12 mb-3">
                            <label><?= htmlspecialchars($ctf['title_ar']) ?> 
                                <?php if ($ctf['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <textarea name="custom_text_<?= $ctf['id'] ?>" 
                                      class="form-control" 
                                      rows="3"
                                      <?= $ctf['is_required'] ? 'required' : '' ?>
                                      placeholder="اكتب إجابتك..."><?= htmlspecialchars($custom_responses[$ctf['id']] ?? '') ?></textarea>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <button type="submit" name="save" class="btn btn-warning">
                            <i class="fas fa-save"></i> حفظ مسودة
                        </button>
                        <button type="submit" name="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> إنهاء التقييم
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- نموذج التقييم (قبل الإرسال) -->
                <form method="POST">
                    <!-- المجالات الرقمية -->
                    <h5>مجالات التقييم الرقمية</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>مجال التقييم</th>
                                    <th>الدرجة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><?= htmlspecialchars($field['title_ar']) ?></td>
                                    <td>
                                        <input type="number" 
                                               name="score_<?= $field['id'] ?>" 
                                               class="form-control" 
                                               min="0" 
                                               max="<?= $field['max_score'] ?>" 
                                               value="<?= $responses[$field['id']] ?? 0 ?>" 
                                               required
                                               title="الدرجة القصوى: <?= $field['max_score'] ?>">
                                        <small class="text-muted">من <?= $field['max_score'] ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- الحقول النصية المخصصة -->
                    <?php if (!empty($custom_text_fields)): ?>
                    <h5 class="mt-4">حقول نصية إضافية</h5>
                    <div class="row">
                        <?php foreach ($custom_text_fields as $ctf): ?>
                        <div class="col-md-12 mb-3">
                            <label><?= htmlspecialchars($ctf['title_ar']) ?> 
                                <?php if ($ctf['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <textarea name="custom_text_<?= $ctf['id'] ?>" 
                                      class="form-control" 
                                      rows="3"
                                      <?= $ctf['is_required'] ? 'required' : '' ?>
                                      placeholder="اكتب إجابتك..."><?= htmlspecialchars($custom_responses[$ctf['id']] ?? '') ?></textarea>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <button type="submit" name="save" class="btn btn-warning">
                            <i class="fas fa-save"></i> حفظ مسودة
                        </button>
                        <button type="submit" name="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> إنهاء التقييم
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            <?php endif; ?>
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