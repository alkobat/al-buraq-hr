<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit; }
require_once '../../app/core/db.php';
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
// حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    $keys = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from_email', 'smtp_from_name', 'auto_send_user', 'auto_send_eval'];
    foreach ($keys as $key) {
        $val = $_POST[$key] ?? '';
        $pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = ?")->execute([$val, $key]);
    }
    $msg = "تم حفظ الإعدادات بنجاح.";
}

// حفظ القوالب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $id = $_POST['tpl_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $pdo->prepare("UPDATE email_templates SET subject = ?, body = ? WHERE id = ?")->execute([$subject, $body, $id]);
    $msg = "تم تحديث القالب.";
}

// جلب البيانات
$settings = $pdo->query("SELECT `key`, `value` FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$templates = $pdo->query("SELECT * FROM email_templates")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إعدادات البريد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">
<?php $current_page = basename(__FILE__); require_once '_sidebar_nav.php'; ?>
<main class="admin-main-content">
    <h3><i class="fas fa-envelope-open-text"></i> إعدادات البريد الإلكتروني</h3>
    <hr>
    <?php if (isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white"><i class="fas fa-server"></i> إعدادات الخادم (SMTP)</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-2">
                            <div class="col-md-8 mb-2"><label>Host</label><input type="text" name="smtp_host" class="form-control" value="<?= $settings['smtp_host'] ?>"></div>
                            <div class="col-md-4 mb-2"><label>Port</label><input type="text" name="smtp_port" class="form-control" value="<?= $settings['smtp_port'] ?>"></div>
                            <div class="col-md-6 mb-2"><label>User</label><input type="text" name="smtp_user" class="form-control" value="<?= $settings['smtp_user'] ?>"></div>
                            <div class="col-md-6 mb-2"><label>Password</label><input type="password" name="smtp_pass" class="form-control" value="<?= $settings['smtp_pass'] ?>"></div>
                            <div class="col-md-6 mb-2"><label>Secure</label><select name="smtp_secure" class="form-select"><option value="tls" <?= $settings['smtp_secure']=='tls'?'selected':''?>>TLS</option><option value="ssl" <?= $settings['smtp_secure']=='ssl'?'selected':''?>>SSL</option></select></div>
                            <div class="col-md-6 mb-2"><label>From Email</label><input type="text" name="smtp_from_email" class="form-control" value="<?= $settings['smtp_from_email'] ?>"></div>
                            <div class="col-md-12 mb-3"><label>Sender Name</label><input type="text" name="smtp_from_name" class="form-control" value="<?= $settings['smtp_from_name'] ?>"></div>
                            
                            <hr>
                            <h6>الأتمتة (Automation)</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="auto_send_user" value="1" <?= $settings['auto_send_user']=='1'?'checked':''?>>
                                <label class="form-check-label">إرسال تلقائي عند إضافة مستخدم جديد (مع كلمة السر)</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_send_eval" value="1" <?= $settings['auto_send_eval']=='1'?'checked':''?>>
                                <label class="form-check-label">إرسال تلقائي للموظف عند اكتمال التقييم من المدير</label>
                            </div>
                            
                            <button type="submit" name="save_smtp" class="btn btn-primary w-100">حفظ الإعدادات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-secondary text-white"><i class="fas fa-edit"></i> قوالب الرسائل</div>
                <div class="card-body">
                    <?php foreach($templates as $tpl): ?>
                        <form method="POST" class="mb-4 border-bottom pb-3">
                            <input type="hidden" name="tpl_id" value="<?= $tpl['id'] ?>">
                            <h6><?= match($tpl['type']) {'new_user'=>'قالب مستخدم جديد', 'evaluation_link'=>'قالب رابط التقييم', default=>'قالب عام'} ?></h6>
                            <div class="mb-2"><input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($tpl['subject']) ?>" placeholder="الموضوع"></div>
                            <div class="mb-2"><textarea name="body" class="form-control" rows="4"><?= htmlspecialchars($tpl['body']) ?></textarea></div>
                            <small class="text-muted d-block mb-2">المتغيرات: <?= $tpl['placeholders'] ?></small>
                            <button type="submit" name="save_template" class="btn btn-sm btn-success">تحديث القالب</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="../assets/js/search.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>