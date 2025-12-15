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
$today_stats = $stats->getTodayStats();
$alerts = $stats->getAlerts();
$last_emails = $stats->getLastEmails(5);
$daily_stats = $stats->getDailyStats(30);
$success_failure_rate = $stats->getSuccessFailureRate(30);

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة مراقبة البريد الإلكتروني</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/email-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-dashboard">
<?php require_once '_sidebar_nav.php'; ?>

<main class="admin-main-content">
    <div class="page-header mb-4">
        <h3><i class="fas fa-envelope"></i> لوحة مراقبة البريد الإلكتروني</h3>
        <p class="text-muted">مراقبة وإدارة جميع رسائل البريد الإلكتروني</p>
    </div>

    <!-- Alerts Section -->
    <?php if (!empty($alerts)): ?>
        <div class="alerts-section mb-4">
            <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= htmlspecialchars($alert['type']) ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong><?= htmlspecialchars($alert['title']) ?></strong>
                    <?= htmlspecialchars($alert['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card">
                <div class="card-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="card-body">
                    <small class="text-muted d-block">الرسائل المُرسلة اليوم</small>
                    <h3 class="mb-0"><?= htmlspecialchars($today_stats['sent']) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card">
                <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="card-body">
                    <small class="text-muted d-block">الرسائل الفاشلة اليوم</small>
                    <h3 class="mb-0 text-danger"><?= htmlspecialchars($today_stats['failed']) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card">
                <div class="card-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="card-body">
                    <small class="text-muted d-block">معدل النجاح اليوم</small>
                    <h3 class="mb-0 text-success"><?= htmlspecialchars($today_stats['rate']) ?>%</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card">
                <div class="card-icon"><i class="fas fa-inbox"></i></div>
                <div class="card-body">
                    <small class="text-muted d-block">إجمالي الرسائل اليوم</small>
                    <h3 class="mb-0"><?= htmlspecialchars($today_stats['total']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-chart-line"></i> الرسائل المُرسلة يومياً (آخر 30 يوم)
                </div>
                <div class="card-body">
                    <canvas id="dailyChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-pie-chart"></i> معدل النجاح والفشل
                </div>
                <div class="card-body">
                    <canvas id="rateChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Last Emails Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-history"></i> آخر الرسائل المُرسلة
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>المستقبل</th>
                                    <th>الموضوع</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                    <th>الوقت</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($last_emails)): ?>
                                    <?php foreach ($last_emails as $email): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($email['recipient_email'] ?? 'غير محدد') ?></td>
                                            <td><?= htmlspecialchars(substr($email['subject'], 0, 50)) ?></td>
                                            <td><?= htmlspecialchars($email['email_type'] ?? '-') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $email['status'] === 'success' ? 'success' : 'danger' ?>">
                                                    <?= $email['status'] === 'success' ? 'نجح' : 'فشل' ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($email['created_at']))) ?></td>
                                            <td>
                                                <a href="email-logs.php?id=<?= htmlspecialchars($email['id']) ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> عرض
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">لا توجد رسائل</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-tools"></i> إجراءات سريعة
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <a href="email-logs.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> عرض جميع الرسائل
                        </a>
                        <a href="email-logs.php?status=failure" class="btn btn-outline-danger">
                            <i class="fas fa-exclamation-circle"></i> الرسائل الفاشلة
                        </a>
                        <a href="email_settings.php" class="btn btn-outline-warning">
                            <i class="fas fa-cog"></i> الإعدادات
                        </a>
                        <a href="email-test.php" class="btn btn-outline-info">
                            <i class="fas fa-flask"></i> اختبار الاتصال
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/email-dashboard.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Chart Data
    const dailyLabels = <?= json_encode(array_column($daily_stats, 'date')) ?>;
    const dailySent = <?= json_encode(array_column($daily_stats, 'sent')) ?>;
    const dailyFailed = <?= json_encode(array_column($daily_stats, 'failed')) ?>;

    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [
                {
                    label: 'الرسائل الناجحة',
                    data: dailySent,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.3,
                    borderWidth: 2,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#28a745',
                },
                {
                    label: 'الرسائل الفاشلة',
                    data: dailyFailed,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.3,
                    borderWidth: 2,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#dc3545',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: {
                        font: { size: 12 },
                        usePointStyle: true,
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });

    // Success/Failure Rate Chart
    const successCount = <?= $success_failure_rate['success'] ?? 0 ?>;
    const failureCount = <?= $success_failure_rate['failure'] ?? 0 ?>;

    const rateCtx = document.getElementById('rateChart').getContext('2d');
    new Chart(rateCtx, {
        type: 'doughnut',
        data: {
            labels: ['ناجحة', 'فاشلة'],
            datasets: [{
                data: [successCount, failureCount],
                backgroundColor: ['#28a745', '#dc3545'],
                borderColor: ['#fff', '#fff'],
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 12 },
                        usePointStyle: true,
                        padding: 15
                    }
                }
            }
        }
    });
});
</script>

</body>
</html>
