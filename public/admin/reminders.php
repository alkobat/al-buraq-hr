<?php
// إعدادات البيئة
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../app/core/db.php';
require_once '../../app/core/Mailer.php';
require_once '../../app/core/Logger.php';

// جلب مفتاح الأمان من الإعدادات
$cron_key_stmt = $pdo->query("SELECT value FROM system_settings WHERE `key` = 'cron_secret_key'");
$CRON_SECRET = $cron_key_stmt->fetchColumn() ?: '123456'; // قيمة افتراضية إذا لم يوجد

// === التحقق من الصلاحية ===
$is_cli = (php_sapi_name() === 'cli');
$is_cron_request = (isset($_GET['key']) && $_GET['key'] === $CRON_SECRET);
$is_admin_session = (session_status() === PHP_SESSION_NONE ? session_start() : true) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// إذا لم يكن أدمن ولا طلب آلي مصرح به -> طرد
if (!$is_cli && !$is_cron_request && !$is_admin_session) {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

// === المنطق الرئيسي ===
$messages = [];
$active_cycle = $pdo->query("SELECT id, year FROM evaluation_cycles WHERE status = 'active' LIMIT 1")->fetch();

if (!$active_cycle) {
    if ($is_cron_request) die("No active cycle.");
    $messages[] = ['type' => 'warning', 'text' => 'لا توجد دورة تقييم نشطة حالياً.'];
}

// دالة لحساب المتأخرين
function getPendingEvaluators($pdo, $cycle_id) {
    $list = [];
    
    // 1. المدراء (Managers)
    $managers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'manager' AND status = 'active'")->fetchAll();
    foreach ($managers as $mgr) {
        // عدد الموظفين التابعين له
        $total_emp = $pdo->prepare("SELECT COUNT(*) FROM users WHERE manager_id = ? AND status='active' AND role != 'admin'");
        $total_emp->execute([$mgr['id']]);
        $total = $total_emp->fetchColumn();

        if ($total > 0) {
            // عدد التقييمات المنجزة (submitted أو approved)
            $done = $pdo->prepare("SELECT COUNT(*) FROM employee_evaluations WHERE evaluator_id = ? AND cycle_id = ? AND evaluator_role = 'manager' AND status IN ('submitted', 'approved')");
            $done->execute([$mgr['id'], $cycle_id]);
            $completed = $done->fetchColumn();

            $pending = $total - $completed;
            if ($pending > 0) {
                $list[] = [
                    'id' => $mgr['id'],
                    'name' => $mgr['name'],
                    'email' => $mgr['email'],
                    'role' => 'مدير إدارة',
                    'pending' => $pending
                ];
            }
        }
    }

    // 2. الرؤساء المباشرين (Supervisors)
    $supervisors = $pdo->query("SELECT id, name, email FROM users WHERE role = 'supervisor' AND status = 'active'")->fetchAll();
    foreach ($supervisors as $sup) {
        $total_emp = $pdo->prepare("SELECT COUNT(*) FROM users WHERE supervisor_id = ? AND status='active'");
        $total_emp->execute([$sup['id']]);
        $total = $total_emp->fetchColumn();

        if ($total > 0) {
            $done = $pdo->prepare("SELECT COUNT(*) FROM employee_evaluations WHERE evaluator_id = ? AND cycle_id = ? AND evaluator_role = 'supervisor' AND status IN ('submitted', 'approved')");
            $done->execute([$sup['id'], $cycle_id]);
            $completed = $done->fetchColumn();

            $pending = $total - $completed;
            if ($pending > 0) {
                $list[] = [
                    'id' => $sup['id'],
                    'name' => $sup['name'],
                    'email' => $sup['email'],
                    'role' => 'رئيس مباشر',
                    'pending' => $pending
                ];
            }
        }
    }
    
    return $list;
}

$pending_list = $active_cycle ? getPendingEvaluators($pdo, $active_cycle['id']) : [];

// === معالجة الإرسال ===
if (($is_cron_request) || ($is_admin_session && isset($_POST['send_reminders']))) {
    
    // CSRF Check للأدمن فقط
    if ($is_admin_session && !$is_cron_request) {
        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $messages[] = ['type' => 'danger', 'text' => 'خطأ أمني (CSRF).'];
            goto render_page; // تخطي الإرسال
        }
    }

    $mailer = new Mailer($pdo);
    $logger = new Logger($pdo);
    $sent_count = 0;

    foreach ($pending_list as $user) {
        $sent = $mailer->sendEmail($user['email'], $user['name'], 'evaluation_reminder', [
            'name' => $user['name'],
            'count' => $user['pending'],
            'year' => $active_cycle['year']
        ]);
        
        if ($sent) $sent_count++;
    }

    $log_msg = "تم تشغيل نظام التذكير الآلي. تم إرسال $sent_count رسالة.";
    $logger->log('system', $log_msg, $is_admin_session ? $_SESSION['user_id'] : null);

    if ($is_cron_request) {
        echo "Success: $sent_count emails sent.";
        exit;
    } else {
        $messages[] = ['type' => 'success', 'text' => "تم إرسال $sent_count رسالة تذكير بنجاح."];
    }
}

render_page:
// إذا كان طلب Cron، لا نعرض HTML
if ($is_cron_request) exit;

// توليد رمز CSRF للعرض
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>نظام التذكير الآلي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-bullhorn"></i> التذكير بالتقييمات</h3>
        <?php if ($active_cycle && count($pending_list) > 0): ?>
        <form method="POST" onsubmit="return confirm('هل أنت متأكد من إرسال بريد تذكيري لجميع القائمة أدناه؟');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" name="send_reminders" class="btn btn-warning text-dark">
                <i class="fas fa-paper-plane"></i> إرسال تذكير للكل (<?= count($pending_list) ?>)
            </button>
        </form>
        <?php endif; ?>
    </div>
    <hr>

    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endforeach; ?>

    <?php if (!$active_cycle): ?>
        <div class="alert alert-secondary">لا توجد بيانات للعرض.</div>
    <?php else: ?>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <i class="fas fa-clock"></i> المتأخرون في إكمال التقييمات
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>الاسم</th>
                                <th>الدور</th>
                                <th>البريد الإلكتروني</th>
                                <th>تقييمات معلقة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_list)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-success"><i class="fas fa-check-circle"></i> ممتاز! جميع التقييمات مكتملة.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_list as $u): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($u['name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $u['role'] ?></span></td>
                                    <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="text-danger fw-bold"><?= $u['pending'] ?></td>
                                    <td><span class="badge bg-warning text-dark">متأخر</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="fas fa-robot"></i> التشغيل التلقائي (Cron Job)
            </div>
            <div class="card-body">
                <p>يمكنك ضبط الخادم لتشغيل هذا السكربت تلقائياً (مثلاً كل أسبوع) باستخدام الرابط التالي:</p>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/reminders.php?key=" . $CRON_SECRET ?>" readonly id="cronLink">
                    <button class="btn btn-outline-secondary" onclick="copyLink()">نسخ الرابط</button>
                </div>
                <small class="text-muted mt-2 d-block">
                    * تأكد من حماية مفتاح الأمان (<code><?= $CRON_SECRET ?></code>) ويمكنك تغييره من قاعدة البيانات.
                </small>
            </div>
        </div>

    <?php endif; ?>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script>
function copyLink() {
    var copyText = document.getElementById("cronLink");
    copyText.select();
    document.execCommand("copy");
    alert("تم نسخ رابط الأتمتة!");
}
</script>
<script src="../assets/js/search.js"></script>
</body>
</html>