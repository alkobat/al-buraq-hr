<?php
/**
 * اختبار ميزات أمان البريد الإلكتروني
 * 
 * الاستخدام:
 * php test-email-security.php
 */

require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/SecurityManager.php';
require_once __DIR__ . '/app/core/EmailValidator.php';
require_once __DIR__ . '/app/core/RateLimiter.php';
require_once __DIR__ . '/app/core/EmailService.php';

echo "========================================\n";
echo "  اختبار أمان البريد الإلكتروني\n";
echo "========================================\n\n";

// Test 1: SecurityManager
echo "Test 1: تشفير وفك التشفير\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $original = 'my_secret_password@123';
    $encrypted = SecurityManager::encrypt($original);
    $decrypted = SecurityManager::decrypt($encrypted);
    
    if ($original === $decrypted) {
        echo "✓ التشفير يعمل بشكل صحيح\n";
        echo "  النص الأصلي: $original\n";
        echo "  الطول المشفر: " . strlen($encrypted) . " حرف\n";
    } else {
        echo "✗ فشل التشفير\n";
    }
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Email Hashing
echo "Test 2: تجزئة البريد الإلكتروني\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $email = 'user@example.com';
    $hash = SecurityManager::hashEmail($email);
    $hash2 = SecurityManager::hashEmail('USER@EXAMPLE.COM');
    
    if ($hash === $hash2) {
        echo "✓ التجزئة متسقة (case-insensitive)\n";
        echo "  البريد: $email\n";
        echo "  الـ Hash: " . substr($hash, 0, 16) . "...\n";
    } else {
        echo "✗ فشلت التجزئة\n";
    }
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Email Validation
echo "Test 3: التحقق من صحة البريد الإلكتروني\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$testEmails = [
    'valid@example.com' => true,
    'user+tag@example.co.uk' => true,
    'invalid..email@test.com' => false,
    'no-at-sign.com' => false,
    'spaces in@email.com' => false,
];

foreach ($testEmails as $email => $shouldBeValid) {
    $validation = EmailValidator::validate($email);
    $result = $validation['is_valid'] ? '✓' : '✗';
    $status = ($validation['is_valid'] === $shouldBeValid) ? 'صحيح' : 'خطأ';
    echo "$result $email - $status\n";
}

echo "\n";

// Test 4: Spam Detection
echo "Test 4: الكشف عن الـ Spam\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$testMessages = [
    [
        'subject' => 'تقييم أدائك - 2025',
        'body' => 'تم تقييم أدائك بنجاح. يرجى مراجعة النتائج.',
        'shouldBeSpam' => false,
    ],
    [
        'subject' => 'تحذير!!! تحقق من حسابك على الفور!!!',
        'body' => 'VERIFY YOUR ACCOUNT NOW أو سيتم إيقاف حسابك',
        'shouldBeSpam' => true,
    ],
    [
        'subject' => 'جائزة اليانصيب - ادعِ جائزتك',
        'body' => 'لقد فزت بـ 1,000,000$ في اليانصيب! اضغط على الرابط لادعاء جائزتك.',
        'shouldBeSpam' => true,
    ],
];

foreach ($testMessages as $msg) {
    $spam = EmailValidator::detectSpam($msg['subject'], $msg['body']);
    $result = $spam['is_suspicious'] ? '⚠️  مريب' : '✓ آمن';
    $status = ($spam['is_suspicious'] === $msg['shouldBeSpam']) ? 'صحيح' : 'خطأ';
    echo "$result - {$msg['subject']} - $status\n";
    if ($spam['is_suspicious']) {
        echo "   الأسباب: " . implode(', ', $spam['reasons']) . "\n";
    }
}

echo "\n";

// Test 5: Suspicious Links
echo "Test 5: الكشف عن الروابط المريبة\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$testLinks = [
    'اضغط هنا: https://example.com/safe/link' => false,
    'اضغط هنا: https://bit.ly/shortlink' => true,
    'اضغط هنا: https://192.168.1.1/admin' => true,
];

foreach ($testLinks as $content => $shouldHaveSuspicious) {
    $check = EmailValidator::findSuspiciousLinks($content);
    $result = $check['has_suspicious_links'] ? '⚠️  مريب' : '✓ آمن';
    $status = ($check['has_suspicious_links'] === $shouldHaveSuspicious) ? 'صحيح' : 'خطأ';
    echo "$result - $status\n";
}

echo "\n";

// Test 6: Rate Limiter
echo "Test 6: حد التصنيف\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $rateLimiter = new RateLimiter($pdo);
    
    $check = $rateLimiter->checkRateLimit('test@example.com', 'test_user');
    if ($check['allowed']) {
        echo "✓ الإرسال مسموح\n";
        
        // تسجيل محاولة
        $rateLimiter->logAttempt('test@example.com', true, 'test_user');
        echo "✓ تم تسجيل المحاولة\n";
        
        // الحصول على الإحصائيات
        $stats = $rateLimiter->getStats('test@example.com');
        echo "  الرسائل في الساعة: " . $stats['hourly_sent'] . "/" . $stats['hourly_limit'] . "\n";
        echo "  الرسائل اليومية للمستقبل: " . $stats['daily_to_recipient'] . "/" . $stats['daily_limit'] . "\n";
    } else {
        echo "✗ تجاوز حد التصنيف: " . $check['reason'] . "\n";
    }
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 7: Email Service Stats
echo "Test 7: إحصائيات البريد الإلكتروني\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $emailService = new EmailService($pdo);
    $stats = $emailService->getEmailStats();
    
    echo "إجمالي السجلات: " . $stats['total_logs'] . "\n";
    echo "المرسلة بنجاح: " . $stats['total_sent'] . "\n";
    echo "الفاشلة: " . $stats['total_failed'] . "\n";
    echo "تجاوزات حد التصنيف: " . $stats['rate_limit_violations'] . "\n";
    echo "الرسائل المريبة: " . $stats['spam_detected'] . "\n";
    
    if ($stats['total_logs'] > 0) {
        $successRate = ($stats['total_sent'] / $stats['total_logs']) * 100;
        echo "معدل النجاح: " . number_format($successRate, 2) . "%\n";
    }
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "✓ انتهى الاختبار\n";
echo "========================================\n";
?>
