<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// ==========================================
// (إصلاح) استدعاء ملف التحميل التلقائي للمكتبات (Composer)
// ضروري لتحميل PHPMailer وباقي الاعتماديات
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else {
    die('<div style="padding:20px; direction:rtl;">خطأ: ملف المكتبات غير موجود (vendor/autoload.php). تأكد من تشغيل: <strong>composer install</strong></div>');
}
// ==========================================

require_once '../../app/core/db.php';
require_once '../../app/core/Logger.php';

// CSRF
if (empty($_SESSION['csrf_token'])) { try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {} }
$csrf_token = $_SESSION['csrf_token'];
if (empty($_SESSION['logout_csrf_token'])) { try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {} }
$logout_csrf_token = $_SESSION['logout_csrf_token'];

$supervisor_id = $_SESSION['user_id'];
$employee_id = $_GET['employee'] ?? null;

// ==========================================
// الوضع 1: القائمة (Pagination & Search)
// ==========================================
if (!$employee_id) {
    $current_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();
    $cycle_id = $current_cycle ? $current_cycle['id'] : null;

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $q = trim($_GET['q'] ?? '');
    $offset = ($page - 1) * $limit;

    $employees = []; $total_rows = 0; $total_pages = 0;

    if ($cycle_id) {
        $sql_base = "
            FROM users u
            LEFT JOIN employee_evaluations e ON u.id = e.employee_id AND e.cycle_id = ? AND e.evaluator_role = 'supervisor'
            WHERE u.supervisor_id = ? AND u.status = 'active'
        ";
        $params = [$cycle_id, $supervisor_id];

        if ($q) {
            $sql_base .= " AND (u.name LIKE ? OR u.job_title LIKE ?)";
            $params[] = "%$q%"; $params[] = "%$q%";
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
        $count_stmt->execute($params);
        $total_rows = $count_stmt->fetchColumn();
        $total_pages = ceil($total_rows / $limit);

        $sql = "SELECT u.*, e.status as eval_status " . $sql_base . " ORDER BY u.name ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();
    }
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقييم الموظفين (مشرف)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">
    <?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>
    
    <main class="admin-main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-users"></i> قائمة الموظفين (إشراف)</h3>
            <span class="badge bg-primary">العدد: <?= $total_rows ?></span>
        </div>
        
        <?php if (!$current_cycle): ?>
            <div class="alert alert-warning">لا توجد دورة نشطة.</div>
        <?php else: ?>
            <div class="card mb-3 bg-light border-0">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2">
                        <div class="col-md-8">
                            <input type="text" name="q" class="form-control" placeholder="بحث..." value="<?= htmlspecialchars($q) ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="limit" class="form-select" onchange="this.form.submit()">
                                <option value="10" <?= $limit==10?'selected':'' ?>>10</option>
                                <option value="20" <?= $limit==20?'selected':'' ?>>20</option>
                                <option value="50" <?= $limit==50?'selected':'' ?>>50</option>
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-primary w-100">بحث</button></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>الموظف</th><th>الوظيفة</th><th>الحالة</th><th>الإجراء</th></tr></thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><?= htmlspecialchars($emp['name']) ?></td>
                                <td><?= htmlspecialchars($emp['job_title']) ?></td>
                                <td>
                                    <?php 
                                    if (!$emp['eval_status']) echo '<span class="badge bg-secondary">لم يبدأ</span>';
                                    elseif ($emp['eval_status']=='draft') echo '<span class="badge bg-warning text-dark">مسودة</span>';
                                    else echo '<span class="badge bg-success">تم الإرسال</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="?employee=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">
                                        <?= ($emp['eval_status']=='approved' || $emp['eval_status']=='submitted') ? '<i class="fas fa-eye"></i> عرض' : '<i class="fas fa-edit"></i> تقييم' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white">
                    <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for($i=1; $i<=$total_pages; $i++): ?>
                            <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&q=<?= urlencode($q) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                    </ul></nav>
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
    exit;
}

// ==========================================
// الوضع 2: نموذج التقييم (مع التالي/السابق)
// ==========================================

// 1. تحديد السابق والتالي
$stmt_prev = $pdo->prepare("SELECT id FROM users WHERE supervisor_id = ? AND status='active' AND name < (SELECT name FROM users WHERE id=?) ORDER BY name DESC LIMIT 1");
$stmt_prev->execute([$supervisor_id, $employee_id]);
$prev_emp_id = $stmt_prev->fetchColumn();

$stmt_next = $pdo->prepare("SELECT id FROM users WHERE supervisor_id = ? AND status='active' AND name > (SELECT name FROM users WHERE id=?) ORDER BY name ASC LIMIT 1");
$stmt_next->execute([$supervisor_id, $employee_id]);
$next_emp_id = $stmt_next->fetchColumn();

// جلب بيانات الموظف للتحقق
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND supervisor_id = ?");
$stmt->execute([$employee_id, $supervisor_id]);
$employee = $stmt->fetch();
if (!$employee) die('خطأ: لا تملك صلاحية.');

// جلب الدورة والمجالات (كما هو) ...
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();
if (!$active_cycle) die('لا توجد دورة نشطة.');

$fields = $pdo->prepare("SELECT * FROM evaluation_fields WHERE cycle_id = ? ORDER BY order_index");
$fields->execute([$active_cycle['id']]);
$fields = $fields->fetchAll();

$custom_text_fields = $pdo->prepare("SELECT * FROM evaluation_custom_text_fields WHERE cycle_id = ? ORDER BY order_index");
$custom_text_fields->execute([$active_cycle['id']]);
$custom_text_fields = $custom_text_fields->fetchAll();

$evaluation = $pdo->prepare("SELECT * FROM employee_evaluations WHERE employee_id = ? AND evaluator_id = ? AND cycle_id = ? AND evaluator_role = 'supervisor'");
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

$is_submitted = $evaluation && $evaluation['status'] !== 'draft';

// معالجة الحفظ
if ($_POST) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني.";
    } else {
        $total_score = 0;
        $pdo->beginTransaction();
                // (جديد) تهيئة المسجل
        $logger = new Logger($pdo);
        try {
            if (!$evaluation) {
                $pdo->prepare("INSERT INTO employee_evaluations (employee_id, cycle_id, evaluator_id, evaluator_role, status) VALUES (?, ?, ?, 'supervisor', 'draft')")->execute([$employee_id, $active_cycle['id'], $supervisor_id]);
                $evaluation_id = $pdo->lastInsertId();
            } else {
                $evaluation_id = $evaluation['id'];
                if ($evaluation['status'] !== 'draft') {
                    $pdo->prepare("UPDATE employee_evaluations SET status = 'draft' WHERE id = ?")->execute([$evaluation_id]);
                }
            }

            foreach ($fields as $field) {
                $score = min((int)($_POST['score_' . $field['id']] ?? 0), $field['max_score']);
                $total_score += $score;
                $pdo->prepare("INSERT INTO evaluation_responses (evaluation_id, field_id, score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE score = ?")->execute([$evaluation_id, $field['id'], $score, $score]);
            }

            foreach ($custom_text_fields as $ctf) {
                $text = trim($_POST['custom_text_' . $ctf['id']] ?? '');
                if ($text !== '') {
                    $pdo->prepare("INSERT INTO evaluation_custom_text_responses (evaluation_id, field_id, response_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE response_text = ?")->execute([$evaluation_id, $ctf['id'], $text, $text]);
                } else {
                    $pdo->prepare("DELETE FROM evaluation_custom_text_responses WHERE evaluation_id = ? AND field_id = ?")->execute([$evaluation_id, $ctf['id']]);
                }
            }

            $pdo->prepare("UPDATE employee_evaluations SET total_score = ? WHERE id = ?")->execute([$total_score, $evaluation_id]);
 // (جديد) تسجيل النشاط (إرسال)
                $logger->log('evaluation', "قام بإرسال تقييم أولي (مشرف) للموظف: {$employee['name']}");
            if (isset($_POST['submit'])) {
                $pdo->prepare("UPDATE employee_evaluations SET status = 'submitted' WHERE id = ?")->execute([$evaluation_id]);
               
                $pdo->commit();

                // (جديد) إرسال بريد إلكتروني مشروط حسب طريقة الاحتساب والإعدادات
                try {
                   require_once '../../app/core/EmailService.php';
                   $emailService = new EmailService($pdo);
                   $emailService->handleEvaluationSubmitted($employee_id, $active_cycle['id'], 'supervisor', $supervisor_id);
                } catch (Exception $mailEx) {
                   error_log('Conditional evaluation email failed (supervisor): ' . $mailEx->getMessage());
                }

                header("Location: evaluate.php?msg=submitted");
            } else {
                         // (جديد) تسجيل النشاط (حفظ مسودة)
                $logger->log('evaluation', "قام بحفظ مسودة تقييم (مشرف) للموظف: {$employee['name']}");

                $pdo->commit();
                header("Location: evaluate.php?employee=$employee_id&msg=saved");
            }
            exit;
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "خطأ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقييم: <?= htmlspecialchars($employee['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>تقييم: <?= htmlspecialchars($employee['name']) ?></h3>
        
        <div class="btn-group" dir="ltr">
            <?php if ($next_emp_id): ?><a href="?employee=<?= $next_emp_id ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a><?php else: ?><button class="btn btn-sm btn-outline-secondary disabled"><i class="fas fa-chevron-right"></i></button><?php endif; ?>
            
            <a href="evaluate.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list"></i></a>
            
            <?php if ($prev_emp_id): ?><a href="?employee=<?= $prev_emp_id ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a><?php else: ?><button class="btn btn-sm btn-outline-secondary disabled"><i class="fas fa-chevron-left"></i></button><?php endif; ?>
        </div>
    </div>
    <hr>

    <?php if (isset($_GET['msg'])): ?><div class="alert alert-success">تم الحفظ بنجاح.</div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($is_submitted): ?>
                <div class="alert alert-info">تم إرسال التقييم.</div>
                <div class="table-responsive"><table class="table table-bordered"><?php foreach ($fields as $f): ?><tr><td><?= htmlspecialchars($f['title_ar']) ?></td><td><?= $responses[$f['id']] ?? 0 ?></td></tr><?php endforeach; ?></table></div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <h5 class="text-primary mb-3">التقييم الرقمي</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light"><tr><th>المعيار</th><th width="150">الدرجة</th></tr></thead>
                            <tbody>
                                <?php foreach ($fields as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['title_ar']) ?> <span class="text-muted small">(Max: <?= $f['max_score'] ?>)</span></td>
                                    <td><input type="number" name="score_<?= $f['id'] ?>" class="form-control" min="0" max="<?= $f['max_score'] ?>" value="<?= $responses[$f['id']] ?? 0 ?>" required></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($custom_text_fields): ?>
                    <h5 class="text-primary mb-3">ملاحظات</h5>
                    <?php foreach ($custom_text_fields as $ctf): ?>
                        <div class="mb-3"><label><?= htmlspecialchars($ctf['title_ar']) ?></label><textarea name="custom_text_<?= $ctf['id'] ?>" class="form-control" rows="3"><?= htmlspecialchars($custom_responses[$ctf['id']] ?? '') ?></textarea></div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <button type="submit" name="save" class="btn btn-warning"><i class="fas fa-save"></i> مسودة</button>
                        <button type="submit" name="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> إرسال</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="../assets/js/search.js"></script>
</body>
</html>