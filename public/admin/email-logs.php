<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../../app/core/db.php';
require_once '../../app/core/EmailStatistics.php';

if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

$stats = new EmailStatistics($pdo);

// Handle email details view
if (!empty($_GET['id'])) {
    $email = $stats->getEmailDetails((int)$_GET['id']);
    if ($email):
        ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الرسالة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">
<?php $current_page = 'email-logs.php'; require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    <div class="page-header mb-4">
        <a href="email-logs.php" class="btn btn-secondary btn-sm mb-3">
            <i class="fas fa-arrow-right"></i> العودة
        </a>
        <h3><i class="fas fa-envelope-open"></i> تفاصيل الرسالة</h3>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-info-circle"></i> معلومات الرسالة
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">المستقبل:</label>
                            <p><strong><?= htmlspecialchars($email['recipient_email'] ?? 'غير محدد') ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">الحالة:</label>
                            <p>
                                <span class="badge bg-<?= $email['status'] === 'success' ? 'success' : 'danger' ?>">
                                    <?= $email['status'] === 'success' ? 'نجح' : 'فشل' ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">نوع الرسالة:</label>
                            <p><strong><?= htmlspecialchars($email['email_type'] ?? 'نوع عام') ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">وقت الإرسال:</label>
                            <p><strong><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($email['created_at']))) ?></strong></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">الموضوع:</label>
                        <p><strong><?= htmlspecialchars($email['subject']) ?></strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">نص الرسالة:</label>
                        <div class="border p-3 bg-light rounded" style="max-height: 400px; overflow-y: auto;">
                            <?= $email['body'] ? htmlspecialchars($email['body']) : '<span class="text-muted">لا يوجد محتوى</span>' ?>
                        </div>
                    </div>

                    <?php if (!empty($email['error_message'])): ?>
                        <div class="mb-3">
                            <label class="text-muted small">رسالة الخطأ:</label>
                            <div class="alert alert-danger">
                                <strong><i class="fas fa-exclamation-circle"></i> خطأ:</strong>
                                <?= htmlspecialchars($email['error_message']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($email['metadata'])): ?>
                        <div class="mb-3">
                            <label class="text-muted small">بيانات إضافية:</label>
                            <pre class="bg-light p-3 rounded border"><code><?= htmlspecialchars($email['metadata']) ?></code></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-tools"></i> إجراءات
                </div>
                <div class="card-body">
                    <?php if ($email['status'] === 'failure' && !empty($email['recipient_email'])): ?>
                        <form method="POST" action="email-logs.php" class="mb-3">
                            <input type="hidden" name="retry_email_id" value="<?= htmlspecialchars($email['id']) ?>">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-redo"></i> إعادة محاولة الإرسال
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="email-logs.php?status=<?= htmlspecialchars($email['status']) ?>" class="btn btn-info w-100">
                        <i class="fas fa-filter"></i> عرض بنفس الحالة
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    else:
        header('Location: email-logs.php');
        exit;
    endif;
    exit;
}

// Handle retry email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['retry_email_id'])) {
    $email_id = (int)$_POST['retry_email_id'];
    $email = $stats->getEmailDetails($email_id);

    if ($email && !empty($email['recipient_email'])) {
        require_once '../../app/core/Mailer.php';
        $mailer = new Mailer($pdo);

        $sent = false;
        try {
            $sent = $mailer->sendCustomEmail(
                $email['recipient_email'],
                '',
                $email['subject'],
                $email['body']
            );
        } catch (Exception $e) {
            $sent = false;
        }

        // Update the log
        if ($sent) {
            $pdo->prepare("UPDATE email_logs SET status = 'success' WHERE id = ?")->execute([$email_id]);
            $msg = "تمت إعادة محاولة الإرسال بنجاح!";
        } else {
            $msg = "فشلت إعادة محاولة الإرسال.";
        }
    }
}

// Get filters and pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;

$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? '',
    'email_type' => $_GET['email_type'] ?? '',
    'to_email' => $_GET['to_email'] ?? '',
    'subject' => $_GET['subject'] ?? '',
];

$result = $stats->getEmailLogs($page, $limit, $filters);
$logs = $result['logs'];
$total_pages = $result['pages'];
$current_page = basename(__FILE__);

// Get email types for filter
$types_stmt = $pdo->query("SELECT DISTINCT email_type FROM email_logs WHERE email_type IS NOT NULL ORDER BY email_type");
$email_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل البريد الإلكتروني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/email-dashboard.css">
</head>
<body class="admin-dashboard">
<?php require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    <div class="page-header mb-4">
        <h3><i class="fas fa-list"></i> سجل البريد الإلكتروني</h3>
        <p class="text-muted">عرض وإدارة جميع الرسائل المُرسلة</p>
    </div>

    <?php if (isset($msg)): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="fas fa-filter"></i> الفلاتر والبحث
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">من التاريخ:</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">إلى التاريخ:</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">الحالة:</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="success" <?= $filters['status'] === 'success' ? 'selected' : '' ?>>ناجحة</option>
                        <option value="failure" <?= $filters['status'] === 'failure' ? 'selected' : '' ?>>فاشلة</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">النوع:</label>
                    <select name="email_type" class="form-select">
                        <option value="">الكل</option>
                        <?php foreach ($email_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $filters['email_type'] === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">البحث في المستقبل:</label>
                    <input type="text" name="to_email" class="form-control" placeholder="البريد الإلكتروني" value="<?= htmlspecialchars($filters['to_email']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">البحث في الموضوع:</label>
                    <input type="text" name="subject" class="form-control" placeholder="الموضوع" value="<?= htmlspecialchars($filters['subject']) ?>">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <a href="email-logs.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Logs Table -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <i class="fas fa-table"></i> سجل الرسائل
            <span class="badge bg-light text-dark float-end"><?= htmlspecialchars($result['total']) ?> رسالة</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المستقبل</th>
                            <th>الموضوع</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th>الوقت</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['id']) ?></td>
                                    <td><?= htmlspecialchars($log['recipient_email'] ?? 'غير محدد') ?></td>
                                    <td><?= htmlspecialchars(substr($log['subject'], 0, 40)) ?><?= strlen($log['subject']) > 40 ? '...' : '' ?></td>
                                    <td><?= htmlspecialchars($log['email_type'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>">
                                            <?= $log['status'] === 'success' ? 'نجح' : 'فشل' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at']))) ?></td>
                                    <td>
                                        <a href="email-logs.php?id=<?= htmlspecialchars($log['id']) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">لا توجد رسائل مطابقة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p == $result['current_page'] ? 'active' : '' ?>">
                                <a class="page-link" href="email-logs.php?page=<?= $p ?><?= !empty($filters['status']) ? '&status=' . htmlspecialchars($filters['status']) : '' ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
