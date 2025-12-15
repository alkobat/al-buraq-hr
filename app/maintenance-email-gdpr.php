<?php
/**
 * سكريبت صيانة البريد الإلكتروني (GDPR Compliance)
 * 
 * المهام:
 * - حذف سجلات البريد القديمة
 * - تنظيف سجلات حد التصنيف
 * - إعادة تشفير البيانات الحساسة
 * 
 * الاستخدام:
 * php app/maintenance-email-gdpr.php [cleanup|stats|all]
 * 
 * الأمثلة:
 * php app/maintenance-email-gdpr.php cleanup      - حذف السجلات القديمة
 * php app/maintenance-email-gdpr.php stats        - عرض الإحصائيات
 * php app/maintenance-email-gdpr.php all          - تشغيل جميع المهام
 */

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/EmailService.php';
require_once __DIR__ . '/core/RateLimiter.php';

$command = $argv[1] ?? 'all';

echo "========================================\n";
echo "  صيانة البريد الإلكتروني (GDPR)\n";
echo "========================================\n\n";

$emailService = new EmailService($pdo);
$rateLimiter = new RateLimiter($pdo);

// حذف السجلات القديمة
if ($command === 'cleanup' || $command === 'all') {
    echo "🔄 حذف سجلات البريد القديمة...\n";
    $retentionDays = 90;
    $deleted = $emailService->cleanupOldEmailLogs($retentionDays);
    echo "✓ تم حذف $deleted سجل قديم (أقدم من $retentionDays يوم)\n\n";

    echo "🔄 حذف سجلات حد التصنيف القديمة...\n";
    $rateLimiter->deleteOldLogs($retentionDays);
    echo "✓ تم حذف سجلات حد التصنيف القديمة\n\n";
}

// عرض الإحصائيات
if ($command === 'stats' || $command === 'all') {
    echo "📊 إحصائيات البريد الإلكتروني:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stats = $emailService->getEmailStats();
    
    echo "إجمالي السجلات:        " . $stats['total_logs'] . "\n";
    echo "الرسائل المرسلة:      " . $stats['total_sent'] . "\n";
    echo "الرسائل الفاشلة:      " . $stats['total_failed'] . "\n";
    echo "تجاوزات حد التصنيف:   " . $stats['rate_limit_violations'] . "\n";
    echo "رسائل مريبة مكتشفة:   " . $stats['spam_detected'] . "\n";
    
    if ($stats['total_logs'] > 0) {
        $successRate = ($stats['total_sent'] / $stats['total_logs']) * 100;
        echo "\nمعدل النجاح:          " . number_format($successRate, 2) . "%\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "✓ اكتملت مهام الصيانة\n";
echo "========================================\n";
?>
