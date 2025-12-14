<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit;
}

// (جديد) توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];
// =============================

// === CSRF: 1. إنشاء الرمز السري للجلسة ===
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];
// ==========================================

require_once '../../app/core/db.php';
require_once '../../app/core/Logger.php'; // (جديد) استدعاء كلاس التسجيل

$manager_id = $_SESSION['user_id'];
$employee_id = $_GET['employee'] ?? null;

// ==========================================
// الوضع 1: قائمة الموظفين (إذا لم يتم تحديد موظف) - بدلاً من التحويل للداشبورد
// ==========================================
if (!$employee_id) {
    $current_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();
    $cycle_id = $current_cycle ? $current_cycle['id'] : null;

    // إعدادات الصفحات والبحث
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $q = trim($_GET['q'] ?? '');
    $status_filter = $_GET['status'] ?? 'all';
    
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $employees = []; 
    $total_rows = 0; 
    $total_pages = 0;

    if ($cycle_id) {
        // الاستعلام الأساسي
        $sql_base = "
            FROM users u
            LEFT JOIN employee_evaluations e ON u.id = e.employee_id AND e.cycle_id = ? AND e.evaluator_role = 'manager'
            WHERE u.manager_id = ? AND u.status = 'active'
        ";
        $params = [$cycle_id, $manager_id];

        // فلتر البحث
        if ($q) {
            $sql_base .= " AND (u.name LIKE ? OR u.job_title LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        // فلتر الحالة
        if ($status_filter === 'pending') {
            $sql_base .= " AND (e.status IS NULL OR e.status IN ('draft', 'rejected'))";
        } elseif ($status_filter === 'submitted') {
            $sql_base .= " AND e.status = 'submitted'";
        } elseif ($status_filter === 'approved') {
            $sql_base .= " AND e.status = 'approved'";
        }

        // حساب العدد الكلي
        $count_stmt = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
        $count_stmt->execute($params);
        $total_rows = $count_stmt->fetchColumn();
        $total_pages = ceil($total_rows / $limit);

        // جلب البيانات مع الصفحات
        $sql = "SELECT u.*, e.status as eval_status, e.total_score " . $sql_base . " ORDER BY u.name ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();
    }
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>قائمة الموظفين للتقييم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">
    <?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>
    
    <main class="admin-main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-users"></i> تقييم الموظفين</h3>
            <span class="badge bg-primary">العدد: <?= $total_rows ?></span>
        </div>
        <hr>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
            <div class="alert alert-success">تم حفظ المسودة بنجاح.</div>
        <?php endif; ?>

        <?php if (!$current_cycle): ?>
            <div class="alert alert-warning">لا توجد دورة تقييم نشطة حالياً.</div>
        <?php else: ?>
            
            <div class="card mb-3 bg-light border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5">
                            <input type="text" name="q" class="form-control" placeholder="بحث بالاسم أو الوظيفة..." value="<?= htmlspecialchars($q) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>كل الحالات</option>
                                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>لم يكتمل (مسودة/جديد)</option>
                                <option value="submitted" <?= $status_filter == 'submitted' ? 'selected' : '' ?>>بانتظار الاعتماد</option>
                                <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>مكتمل (معتمد)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="limit" class="form-select" onchange="this.form.submit()">
                                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20 / صفحة</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 / صفحة</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 / صفحة</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">بحث</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>الموظف</th>
                                    <th>الوظيفة</th>
                                    <th>الحالة</th>
                                    <th class="text-center">الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">لا توجد نتائج مطابقة.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($emp['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($emp['email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($emp['job_title'] ?? '—') ?></td>
                                        <td>
                                            <?php 
                                            if (!$emp['eval_status']) echo '<span class="badge bg-secondary">لم يبدأ</span>';
                                            elseif ($emp['eval_status'] == 'draft') echo '<span class="badge bg-warning text-dark">مسودة</span>';
                                            elseif ($emp['eval_status'] == 'submitted') echo '<span class="badge bg-info text-dark">بانتظار الاعتماد</span>';
                                            elseif ($emp['eval_status'] == 'approved') echo '<span class="badge bg-success">معتمد (' . $emp['total_score'] . '%)</span>';
                                            elseif ($emp['eval_status'] == 'rejected') echo '<span class="badge bg-danger">مرفوض</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($emp['eval_status'] == 'approved' || $emp['eval_status'] == 'submitted'): ?>
                                                <a href="evaluate.php?employee=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> عرض
                                                </a>
                                            <?php else: ?>
                                                <a href="evaluate.php?employee=<?= $emp['id'] ?>" class="btn btn-sm btn-primary px-3">
                                                    <i class="fas fa-edit"></i> تقييم
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white d-flex justify-content-center">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>&status=<?= $status_filter ?>">السابق</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>&status=<?= $status_filter ?>">التالي</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <div id="internet-status"><span class="badge bg-success">متصل</span></div>
    <script src="../assets/js/search.js"></script>
</body>
</html>
<?php
    exit; // إنهاء السكربت هنا لعرض القائمة فقط
}

// ==========================================
// الوضع 2: نموذج التقييم (إذا تم تحديد موظف)
// ==========================================

// (جديد) تحديد الموظف السابق والتالي للتنقل السريع
$prev_emp_id = null;
$next_emp_id = null;

// السابق: الاسم أصغر من الحالي (ترتيب أبجدي)
$stmt_prev = $pdo->prepare("SELECT id FROM users WHERE manager_id = ? AND status='active' AND name < (SELECT name FROM users WHERE id=?) ORDER BY name DESC LIMIT 1");
$stmt_prev->execute([$manager_id, $employee_id]);
$prev_emp_id = $stmt_prev->fetchColumn();

// التالي: الاسم أكبر من الحالي
$stmt_next = $pdo->prepare("SELECT id FROM users WHERE manager_id = ? AND status='active' AND name > (SELECT name FROM users WHERE id=?) ORDER BY name ASC LIMIT 1");
$stmt_next->execute([$manager_id, $employee_id]);
$next_emp_id = $stmt_next->fetchColumn();

// التحقق من أن الموظف تابع لمدير الإدارة (يشمل الموظفين، موظفي التقييم، والمشرفين)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND manager_id = ? AND role IN ('employee', 'evaluator', 'supervisor')");
$stmt->execute([$employee_id, $manager_id]);
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
    WHERE employee_id = ? AND evaluator_id = ? AND cycle_id = ? AND evaluator_role = 'manager'
");
$evaluation->execute([$employee_id, $manager_id, $active_cycle['id']]);
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
    // === CSRF: 2. التحقق من الرمز قبل المعالجة ===
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: طلب غير صالح أو منتهي الصلاحية (CSRF).";
        $pdo->rollback();
        // إعادة توليد الرمز للسماح للمستخدم بالمحاولة مجدداً
        try { $csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
        $_SESSION['csrf_token'] = $csrf_token;
    } else {
        // إزالة الرمز القديم لمنع إعادة استخدامه (Replay Attack)
        unset($_SESSION['csrf_token']); 

        $total_score = 0;
        $pdo->beginTransaction();
        // (جديد) تهيئة المسجل
        $logger = new Logger($pdo);
        try {
            if (!$evaluation) {
                // إنشاء تقييم جديد
                $pdo->prepare("
                    INSERT INTO employee_evaluations (employee_id, cycle_id, evaluator_id, evaluator_role, status)
                    VALUES (?, ?, ?, 'manager', 'draft')
                ")->execute([$employee_id, $active_cycle['id'], $manager_id]);
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

                // (جديد) تسجيل النشاط (إرسال)
                $logger->log('evaluation', "قام بإرسال تقييم للموظف: {$employee['name']}");

                // === في حالة النجاح: توليد رمز CSRF جديد قبل إعادة التوجيه ===
                try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
                $_SESSION['csrf_token'] = $new_csrf_token;
                
                $pdo->commit();

                // (جديد) إرسال بريد إلكتروني مشروط حسب طريقة الاحتساب والإعدادات
                try {
                    require_once '../../app/core/EmailService.php';
                    $emailService = new EmailService($pdo);
                    $emailService->handleEvaluationSubmitted($employee_id, $active_cycle['id'], 'manager', $manager_id);
                } catch (Exception $mailEx) {
                    error_log('Conditional evaluation email failed (manager): ' . $mailEx->getMessage());
                }

                // العودة للقائمة مع رسالة
                header("Location: dashboard.php?msg=submitted");
            } else {
                 // === في حالة الحفظ كمسودة: توليد رمز CSRF جديد قبل إعادة التوجيه ===
                try { $new_csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
                $_SESSION['csrf_token'] = $new_csrf_token;
// (جديد) تسجيل النشاط (حفظ مسودة)
                $logger->log('evaluation', "قام بحفظ مسودة تقييم للموظف: {$employee['name']}");
                $pdo->commit();
                header("Location: evaluate.php?employee=$employee_id&msg=saved");
            }
            exit;
        } catch (Exception $e) {
            $pdo->rollback();
            // في حالة الخطأ: توليد رمز CSRF جديد للمحاولة اللاحقة
            try { $csrf_token = bin2hex(random_bytes(32)); } catch (Exception $e) {}
            $_SESSION['csrf_token'] = $csrf_token;
            $error = "حدث خطأ: " . $e->getMessage();
        }
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

<?php 
// (مُعدَّل) استدعاء شريط التنقل الموحد
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-star"></i> تقييم موظف: <?= htmlspecialchars($employee['name']) ?></h3>
        
        <div class="btn-group" dir="ltr">
            <?php if ($next_emp_id): ?>
                <a href="?employee=<?= $next_emp_id ?>" class="btn btn-sm btn-outline-secondary" title="الموظف التالي">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary disabled"><i class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
            
            <a href="evaluate.php" class="btn btn-sm btn-outline-secondary" title="عودة للقائمة">
                <i class="fas fa-list"></i>
            </a>

            <?php if ($prev_emp_id): ?>
                <a href="?employee=<?= $prev_emp_id ?>" class="btn btn-sm btn-outline-secondary" title="الموظف السابق">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary disabled"><i class="fas fa-chevron-left"></i></button>
            <?php endif; ?>
        </div>
    </div>
    <hr>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">تم <?= $_GET['msg'] === 'submitted' ? 'إرسال التقييم' : 'حفظ المسودة' ?> بنجاح.</div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">دورة تقييم <?= $active_cycle['year'] ?></h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6"><strong>الوظيفة:</strong> <?= htmlspecialchars($employee['job_title'] ?? '—') ?></div>
                <div class="col-md-6"><strong>البريد:</strong> <?= htmlspecialchars($employee['email']) ?></div>
            </div>

            <?php if ($is_submitted && !$is_rejected): ?>
                <div class="alert alert-info">
                    <i class="fas fa-check-circle"></i> تم إرسال التقييم. 
                    <strong>الدرجة النهائية: <?= $evaluation['total_score'] ?? '—' ?>%</strong>
                </div>

                <h5>مجالات التقييم الرقمية</h5>
                <div class="table-responsive mb-4">
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
                        </tbody>
                    </table>
                </div>

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

            <?php else: ?>
                
                <?php if ($is_rejected): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-circle"></i> تم رفض التقييم، يرجى تعديله وإعادة الإرسال.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <h5>مجالات التقييم الرقمية</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>مجال التقييم</th>
                                    <th width="150">الدرجة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><?= htmlspecialchars($field['title_ar']) ?> <span class="text-muted small">(الحد الأقصى: <?= $field['max_score'] ?>)</span></td>
                                    <td>
                                        <input type="number" 
                                               name="score_<?= $field['id'] ?>" 
                                               class="form-control" 
                                               min="0" 
                                               max="<?= $field['max_score'] ?>" 
                                               value="<?= $responses[$field['id']] ?? 0 ?>" 
                                               required>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

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

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" name="save" class="btn btn-warning">
                            <i class="fas fa-save"></i> حفظ مسودة
                        </button>
                        <button type="submit" name="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> إرسال نهائي
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="../assets/js/search.js"></script>
</body>
</html>