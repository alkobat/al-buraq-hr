<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../../app/core/db.php';
require_once '../../app/core/Mailer.php';

if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

$current_page = basename(__FILE__);
$msg = null;
$msg_type = null;
$test_result = null;

// Get current settings
$settings = $pdo->query("SELECT `key`, `value` FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Test SMTP connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])) {
    $test_email = trim($_POST['test_email'] ?? '');

    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'يرجى إدخال بريد إلكتروني صحيح';
        $msg_type = 'danger';
    } else {
        try {
            $mailer = new Mailer($pdo);
            $subject = 'اختبار الاتصال - ' . date('Y-m-d H:i:s');
            $body = '<div style="font-family:Tahoma, Arial; direction: rtl; text-align: right;">' .
                '<p>السلام عليكم ورحمة الله وبركاته،</p>' .
                '<p>هذه رسالة اختبار للتحقق من صحة إعدادات البريد الإلكتروني.</p>' .
                '<p><strong>تم الإرسال في:</strong> ' . htmlspecialchars(date('Y-m-d H:i:s')) . '</p>' .
                '<p>إذا وصلتك هذه الرسالة، فإن الإعدادات صحيحة ✓</p>' .
                '<p>شكراً لك</p>' .
                '</div>';

            $sent = $mailer->sendCustomEmail($test_email, 'Test User', $subject, $body);

            if ($sent) {
                $msg = 'تم إرسال رسالة الاختبار بنجاح! يرجى التحقق من بريدك الإلكتروني.';
                $msg_type = 'success';
                $test_result = [
                    'success' => true,
                    'email' => $test_email,
                    'time' => date('Y-m-d H:i:s'),
                ];
            } else {
                $msg = 'فشل إرسال رسالة الاختبار. يرجى مراجعة الإعدادات.';
                $msg_type = 'danger';
                $test_result = ['success' => false];
            }
        } catch (Exception $e) {
            $msg = 'خطأ: ' . $e->getMessage();
            $msg_type = 'danger';
            $test_result = ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار اتصال البريد الإلكتروني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/email-dashboard.css">
</head>
<body class="admin-dashboard">
<?php require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    <div class="page-header mb-4">
        <h3><i class="fas fa-flask"></i> اختبار اتصال البريد الإلكتروني</h3>
        <p class="text-muted">التحقق من صحة إعدادات SMTP</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible fade show">
            <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Test Form -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-envelope"></i> إرسال رسالة اختبار
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني للاختبار</label>
                            <input type="email" name="test_email" class="form-control" placeholder="example@domain.com" required>
                            <small class="text-muted">سيتم إرسال رسالة اختبار إلى هذا البريد</small>
                        </div>

                        <button type="submit" name="test_smtp" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> إرسال رسالة اختبار
                        </button>
                    </form>
                </div>
            </div>

            <!-- Test Result -->
            <?php if ($test_result): ?>
                <div class="card">
                    <div class="card-header bg-<?= $test_result['success'] ? 'success' : 'danger' ?> text-white">
                        <i class="fas fa-<?= $test_result['success'] ? 'check-circle' : 'times-circle' ?>"></i>
                        نتيجة الاختبار
                    </div>
                    <div class="card-body">
                        <?php if ($test_result['success']): ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check"></i> <strong>تم الاتصال بنجاح!</strong>
                                <ul class="mt-2 mb-0">
                                    <li>البريد المرسل إليه: <?= htmlspecialchars($test_result['email']) ?></li>
                                    <li>الوقت: <?= htmlspecialchars($test_result['time']) ?></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-times"></i> <strong>فشل الاختبار</strong>
                                <?php if (!empty($test_result['error'])): ?>
                                    <p class="mt-2">الخطأ: <?= htmlspecialchars($test_result['error']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Settings Info -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-server"></i> إعدادات SMTP الحالية
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td><strong>الخادم (Host):</strong></td>
                                <td><?= htmlspecialchars($settings['smtp_host'] ?? 'غير محدد') ?></td>
                            </tr>
                            <tr>
                                <td><strong>المنفذ (Port):</strong></td>
                                <td><?= htmlspecialchars($settings['smtp_port'] ?? 'غير محدد') ?></td>
                            </tr>
                            <tr>
                                <td><strong>اسم المستخدم:</strong></td>
                                <td><?= htmlspecialchars($settings['smtp_user'] ?? 'غير محدد') ?></td>
                            </tr>
                            <tr>
                                <td><strong>كلمة المرور:</strong></td>
                                <td>
                                    <?php if (!empty($settings['smtp_pass'])): ?>
                                        <span class="text-muted">••••••••</span>
                                    <?php else: ?>
                                        <span class="text-danger">لم تُعيّن</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>التشفير (Secure):</strong></td>
                                <td><?= htmlspecialchars($settings['smtp_secure'] ?? 'غير محدد') ?></td>
                            </tr>
                            <tr>
                                <td><strong>البريد المُرسِل:</strong></td>
                                <td><?= htmlspecialchars($settings['smtp_from_email'] ?? 'غير محدد') ?></td>
                            </tr>
                            <tr>
                                <td><strong>اسم المُرسِل:</strong></td>
                                <td><?= htmlspecialchars($settings['smtp_from_name'] ?? 'غير محدد') ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i>
                        <strong>تلميح:</strong> لتعديل هذه الإعدادات، يرجى الذهاب إلى
                        <a href="email_settings.php" class="alert-link">إعدادات البريد الإلكتروني</a>.
                    </div>
                </div>
            </div>

            <!-- Configuration Checklist -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-list-check"></i> قائمة التحقق
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" <?= !empty($settings['smtp_host']) ? 'checked' : '' ?> disabled>
                        <label class="form-check-label">
                            خادم SMTP محدد
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" <?= !empty($settings['smtp_port']) ? 'checked' : '' ?> disabled>
                        <label class="form-check-label">
                            المنفذ محدد
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" <?= !empty($settings['smtp_user']) ? 'checked' : '' ?> disabled>
                        <label class="form-check-label">
                            اسم المستخدم محدد
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" <?= !empty($settings['smtp_pass']) ? 'checked' : '' ?> disabled>
                        <label class="form-check-label">
                            كلمة المرور محددة
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" <?= !empty($settings['smtp_from_email']) ? 'checked' : '' ?> disabled>
                        <label class="form-check-label">
                            بريد المُرسِل محدد
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" <?= !empty($settings['smtp_from_name']) ? 'checked' : '' ?> disabled>
                        <label class="form-check-label">
                            اسم المُرسِل محدد
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documentation -->
    <div class="card mt-4">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-book"></i> معلومات مفيدة
        </div>
        <div class="card-body">
            <h6>خطوات استكشاف الأخطاء:</h6>
            <ul>
                <li>تأكد من أن بيانات خادم SMTP صحيحة</li>
                <li>تحقق من كلمة المرور والمنفذ</li>
                <li>تأكد من أن حسابك لدى مزود البريد مفعّل</li>
                <li>تحقق من إعدادات جدار الحماية والنفاذ إلى المنفذ</li>
                <li>جرب استخدام مفاتيح التطبيق بدلاً من كلمة المرور العادية</li>
                <li>راجع سجلات الأخطاء في ملف <code>error.log</code></li>
            </ul>

            <h6 class="mt-3">معلومات خوادم شهيرة:</h6>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>المزود</th>
                        <th>الخادم</th>
                        <th>المنفذ</th>
                        <th>التشفير</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Gmail</td>
                        <td>smtp.gmail.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                    <tr>
                        <td>Office 365</td>
                        <td>smtp.office365.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                    <tr>
                        <td>Yahoo</td>
                        <td>smtp.mail.yahoo.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                    <tr>
                        <td>Outlook</td>
                        <td>smtp.live.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
