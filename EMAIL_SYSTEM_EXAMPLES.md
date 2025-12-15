# 📧 أمثلة عملية لنظام البريد الإلكتروني

## نظرة عامة

هذا الملف يحتوي على أمثلة عملية لاستخدام نظام البريد الإلكتروني في سيناريوهات مختلفة.

---

## 1. الإعداد الأولي

### إعداد التشفير وإنشاء مفتاح

```bash
# في Terminal
cd /home/engine/project
php app/setup-encryption.php
```

**الناتج:**
```
===========================================
🔐 إعداد نظام التشفير للبريد الإلكتروني
===========================================

✅ تم توليد مفتاح تشفير عشوائي (256-bit)
✅ تم حفظ المفتاح في ملف .env

ENCRYPTION_KEY=a1b2c3d4e5f6...

⚠️ احفظ هذا المفتاح في مكان آمن!

🔒 هل تريد تشفير كلمة مرور SMTP الحالية؟ (y/n)
```

---

### إعداد SMTP

افتح: `public/admin/email_settings.php`

```php
// إعدادات Gmail
SMTP Host: smtp.gmail.com
SMTP Port: 465
SMTP Secure: SSL
Username: your-email@gmail.com
Password: your-app-password
From Email: your-email@gmail.com
From Name: نظام تقييم الأداء
```

---

## 2. إرسال بريد بسيط

### مثال 1: إرسال بريد عبر القالب

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/Mailer.php';

$mailer = new Mailer($pdo);

// البيانات
$toEmail = 'employee@example.com';
$toName = 'أحمد محمد';
$templateType = 'evaluation_notification';

$placeholders = [
    'name' => 'أحمد محمد',
    'cycle_year' => '2025',
    'score' => '85.5',
    'link' => 'https://example.com/view/token123'
];

// الإرسال
$result = $mailer->sendEmail($toEmail, $toName, $templateType, $placeholders);

if ($result) {
    echo "✅ تم الإرسال بنجاح!";
} else {
    echo "❌ فشل الإرسال - تحقق من error_log";
}
?>
```

---

### مثال 2: إرسال بريد مخصص

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/Mailer.php';

$mailer = new Mailer($pdo);

$toEmail = 'manager@example.com';
$toName = 'خالد أحمد';
$subject = 'تذكير: تقييم الموظف';
$body = <<<HTML
<div dir="rtl" style="font-family: Arial; padding: 20px;">
    <h2>مرحباً خالد،</h2>
    <p>هذا تذكير بأن لديك 5 موظفين لم يتم تقييمهم بعد.</p>
    <p>يرجى إكمال التقييمات قبل نهاية الشهر.</p>
    <a href="https://example.com/manager/evaluate" 
       style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        إكمال التقييمات
    </a>
</div>
HTML;

$result = $mailer->sendCustomEmail($toEmail, $toName, $subject, $body);

if ($result) {
    echo "✅ تم إرسال التذكير!";
}
?>
```

---

### مثال 3: إرسال بريد مع مرفق

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/Mailer.php';

$mailer = new Mailer($pdo);

$toEmail = 'hr@example.com';
$toName = 'قسم الموارد البشرية';
$subject = 'تقرير التقييمات الشهري';
$body = '<p>يرجى مراجعة تقرير التقييمات المرفق.</p>';

// المرفقات
$attachments = [
    [
        'path' => '/path/to/report.pdf',
        'name' => 'تقرير_التقييمات_ديسمبر_2024.pdf'
    ],
    [
        'string' => $csvData, // محتوى CSV
        'name' => 'data.csv'
    ]
];

$result = $mailer->sendCustomEmail($toEmail, $toName, $subject, $body, $attachments);
?>
```

---

## 3. استخدام EmailService

### مثال 1: معالجة تقييم جديد (manager_only)

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailService.php';
require_once 'app/core/EvaluationCalculator.php';

// تأكد من أن طريقة الحساب = manager_only
$pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = ?")
    ->execute(['manager_only', 'evaluation_method']);

// تفعيل الإرسال للـ manager_only
$pdo->prepare("UPDATE system_settings SET `value` = '1' WHERE `key` = ?")
    ->execute(['evaluation_email_manager_only_enabled']);

// تفعيل Master Toggle
$pdo->prepare("UPDATE system_settings SET `value` = '1' WHERE `key` = ?")
    ->execute(['auto_send_eval']);

$emailService = new EmailService($pdo);

// بيانات التقييم
$employeeId = 45;
$cycleId = 2025;
$evaluatorRole = 'manager';
$managerId = 10;

// معالجة الإرسال
$emailService->handleEvaluationSubmitted($employeeId, $cycleId, $evaluatorRole, $managerId);

// النتيجة: إرسال بريد فوري للموظف
?>
```

---

### مثال 2: معالجة تقييم (average_complete)

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailService.php';

// تأكد من أن طريقة الحساب = average_complete
$pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = ?")
    ->execute(['average_complete', 'evaluation_method']);

// تعيين الوضع: waiting_supervisor_plus_final
$pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = ?")
    ->execute(['waiting_supervisor_plus_final', 'evaluation_email_average_complete_mode']);

// تفعيل Master Toggle
$pdo->prepare("UPDATE system_settings SET `value` = '1' WHERE `key` = ?")
    ->execute(['auto_send_eval']);

$emailService = new EmailService($pdo);

// الموظف لديه مشرف
$employeeId = 30;
$cycleId = 2025;

// 1. المدير يقيّم
$emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 10);
// النتيجة: إرسال "في انتظار تقييم المشرف"

// 2. المشرف يقيّم
$emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 5);
// النتيجة: إرسال "تقييمك النهائي: 87.5"
?>
```

---

### مثال 3: إرسال مع تسجيل شامل

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailService.php';

$emailService = new EmailService($pdo);

$employeeId = 25;
$cycleId = 2025;
$toEmail = 'employee@example.com';
$toName = 'سارة أحمد';
$subject = 'تقييمك السنوي';
$body = '<p>عزيزتي سارة، تم إكمال تقييمك السنوي.</p>';
$emailType = 'evaluation_notification';

// الإرسال مع التسجيل
$result = $emailService->sendAndLog(
    $employeeId,
    $cycleId,
    $toEmail,
    $toName,
    $subject,
    $body,
    $emailType
);

if ($result) {
    echo "✅ تم الإرسال والتسجيل";
} else {
    echo "❌ فشل - تحقق من email_logs";
}

// التحقق من السجل
$log = $pdo->prepare("SELECT * FROM email_logs WHERE employee_id = ? AND cycle_id = ? ORDER BY id DESC LIMIT 1");
$log->execute([$employeeId, $cycleId]);
$lastLog = $log->fetch();

print_r($lastLog);
?>
```

---

## 4. التحقق والأمان

### مثال 1: التحقق من صحة البريد

```php
<?php
require_once 'app/core/EmailValidator.php';

$email = 'test@example.com';

$result = EmailValidator::validate($email);

if ($result['is_valid']) {
    echo "✅ البريد صحيح";
} else {
    echo "❌ البريد غير صحيح: " . $result['message'];
}

// مثال: بريد غير صحيح
$badEmail = 'not-an-email';
$result = EmailValidator::validate($badEmail);
// النتيجة: ['is_valid' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة']
?>
```

---

### مثال 2: كشف Spam

```php
<?php
require_once 'app/core/EmailValidator.php';

$subject = 'URGENT: Verify your account now!';
$body = 'Click here immediately to confirm your password: http://bit.ly/abc123';

$result = EmailValidator::detectSpam($subject, $body);

if ($result['is_suspicious']) {
    echo "⚠️ رسالة مريبة!\n";
    echo "الأسباب:\n";
    foreach ($result['reasons'] as $reason) {
        echo "- $reason\n";
    }
    echo "الدرجة: " . $result['spam_score'] . "/10\n";
} else {
    echo "✅ الرسالة نظيفة";
}

/* الناتج:
⚠️ رسالة مريبة!
الأسباب:
- نمط مريب: verify.*account
- نمط مريب: click.*urgent
- نمط مريب: confirm.*password
- أحرف كبيرة مفرطة
الدرجة: 7/10
*/
?>
```

---

### مثال 3: كشف الروابط المريبة

```php
<?php
require_once 'app/core/EmailValidator.php';

$body = <<<HTML
مرحباً، يرجى النقر على الرابط:
http://bit.ly/free-money
أو هذا: http://192.168.1.1/phishing
HTML;

$result = EmailValidator::findSuspiciousLinks($body);

if ($result['has_suspicious_links']) {
    echo "⚠️ روابط مريبة!\n";
    foreach ($result['links'] as $link) {
        echo "- $link\n";
    }
} else {
    echo "✅ لا توجد روابط مريبة";
}

/* الناتج:
⚠️ روابط مريبة!
- http://bit.ly/free-money
- http://192.168.1.1/phishing
*/
?>
```

---

### مثال 4: تشفير وفك تشفير

```php
<?php
require_once 'app/core/SecurityManager.php';

// التشفير
$plainPassword = 'MySecretPassword123';
$encrypted = SecurityManager::encrypt($plainPassword);

echo "مشفر: $encrypted\n";
// الناتج: mXQ9f7k2...base64...==

// فك التشفير
$decrypted = SecurityManager::decrypt($encrypted);

echo "فك التشفير: $decrypted\n";
// الناتج: MySecretPassword123

// التحقق
if ($plainPassword === $decrypted) {
    echo "✅ التشفير وفك التشفير يعملان بشكل صحيح";
}
?>
```

---

### مثال 5: Rate Limiting

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/RateLimiter.php';

$rateLimiter = new RateLimiter($pdo);

$recipientEmail = 'user@example.com';
$senderId = 'system';

// التحقق من الحد
$check = $rateLimiter->checkRateLimit($recipientEmail, $senderId);

if ($check['allowed']) {
    echo "✅ يمكن الإرسال\n";
    
    // إرسال البريد...
    $success = true;
    
    // تسجيل المحاولة
    $rateLimiter->logAttempt($recipientEmail, $success, $senderId);
} else {
    echo "❌ تجاوز الحد!\n";
    echo "السبب: " . $check['reason'] . "\n";
    echo "الحد: " . $check['limit'] . "\n";
    echo "المستخدم: " . $check['current'] . "\n";
}

// الحصول على الإحصائيات
$stats = $rateLimiter->getStats($recipientEmail);
echo "\nالإحصائيات:\n";
echo "آخر ساعة: " . $stats['last_hour'] . "\n";
echo "آخر يوم: " . $stats['last_day'] . "\n";
echo "آخر أسبوع: " . $stats['last_week'] . "\n";
?>
```

---

## 5. الإحصائيات والمراقبة

### مثال 1: الحصول على إحصائيات اليوم

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailStatistics.php';

$emailStats = new EmailStatistics($pdo);

$todayStats = $emailStats->getTodayStats();

echo "📊 إحصائيات اليوم:\n";
echo "الرسائل المرسلة: " . $todayStats['today_sent'] . "\n";
echo "الرسائل الفاشلة: " . $todayStats['today_failed'] . "\n";
echo "نسبة النجاح: " . $todayStats['today_success_rate'] . "%\n";
echo "إجمالي الرسائل: " . $todayStats['total_emails'] . "\n";

/* الناتج:
📊 إحصائيات اليوم:
الرسائل المرسلة: 45
الرسائل الفاشلة: 3
نسبة النجاح: 93.33%
إجمالي الرسائل: 1258
*/
?>
```

---

### مثال 2: الحصول على السجلات مع فلترة

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailStatistics.php';

$emailStats = new EmailStatistics($pdo);

$page = 1;
$limit = 20;
$filters = [
    'status' => 'failure',
    'email_type' => 'evaluation_notification',
    'date_from' => '2024-12-01',
    'date_to' => '2024-12-15'
];

$result = $emailStats->getEmailLogs($page, $limit, $filters);

echo "إجمالي النتائج: " . $result['total'] . "\n";
echo "الصفحات: " . $result['pages'] . "\n\n";

foreach ($result['logs'] as $log) {
    echo "ID: " . $log['id'] . "\n";
    echo "إلى: " . $log['to_email'] . "\n";
    echo "الموضوع: " . $log['subject'] . "\n";
    echo "الحالة: " . $log['status'] . "\n";
    echo "الخطأ: " . $log['error_message'] . "\n";
    echo "الوقت: " . $log['created_at'] . "\n";
    echo "---\n";
}
?>
```

---

### مثال 3: الحصول على التنبيهات

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailStatistics.php';

$emailStats = new EmailStatistics($pdo);

$alerts = $emailStats->getAlerts();

if (empty($alerts)) {
    echo "✅ لا توجد تنبيهات";
} else {
    foreach ($alerts as $alert) {
        $icon = $alert['type'] === 'danger' ? '🔴' : '⚠️';
        echo "$icon " . $alert['message'] . "\n";
    }
}

/* الناتج المحتمل:
🔴 فشل إرسال 5 رسائل في آخر ساعة!
⚠️ لم يتم إرسال أي رسالة منذ 6 ساعات
*/
?>
```

---

### مثال 4: إحصائيات حسب النوع

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailStatistics.php';

$emailStats = new EmailStatistics($pdo);

$statsByType = $emailStats->getStatsByType();

echo "📊 الإحصائيات حسب النوع:\n\n";

foreach ($statsByType as $stat) {
    echo "النوع: " . $stat['email_type'] . "\n";
    echo "الإجمالي: " . $stat['total'] . "\n";
    echo "النجاح: " . $stat['success_count'] . "\n";
    echo "الفشل: " . $stat['failure_count'] . "\n";
    echo "نسبة النجاح: " . $stat['success_rate'] . "%\n";
    echo "---\n";
}

/* الناتج:
📊 الإحصائيات حسب النوع:

النوع: evaluation_notification
الإجمالي: 450
النجاح: 432
الفشل: 18
نسبة النجاح: 96.00%
---
النوع: new_user
الإجمالي: 120
النجاح: 118
الفشل: 2
نسبة النجاح: 98.33%
---
*/
?>
```

---

## 6. السيناريوهات الكاملة

### سيناريو 1: دورة تقييم كاملة (موظف مع مشرف)

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/EmailService.php';

// الإعداد
$pdo->prepare("UPDATE system_settings SET `value` = 'average_complete' WHERE `key` = 'evaluation_method'")->execute();
$pdo->prepare("UPDATE system_settings SET `value` = 'waiting_supervisor_plus_final' WHERE `key` = 'evaluation_email_average_complete_mode'")->execute();
$pdo->prepare("UPDATE system_settings SET `value` = '1' WHERE `key` = 'auto_send_eval'")->execute();

$emailService = new EmailService($pdo);

$employeeId = 30; // لديه مشرف
$cycleId = 2025;
$managerId = 10;
$supervisorId = 5;

echo "🔄 بدء دورة التقييم...\n\n";

// الخطوة 1: المدير يقيّم
echo "1️⃣ المدير يقيّم الموظف...\n";
$emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', $managerId);
echo "✅ تم إرسال: 'في انتظار تقييم المشرف'\n\n";

// الخطوة 2: المشرف يقيّم
echo "2️⃣ المشرف يقيّم الموظف...\n";
$emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', $supervisorId);
echo "✅ تم إرسال: 'تقييمك النهائي: 87.5'\n\n";

echo "✅ اكتملت دورة التقييم بنجاح!";
?>
```

---

### سيناريو 2: اختبار شامل للنظام

```php
<?php
require_once 'app/core/db.php';
require_once 'app/core/Mailer.php';
require_once 'app/core/EmailValidator.php';
require_once 'app/core/SecurityManager.php';
require_once 'app/core/RateLimiter.php';

echo "🧪 بدء الاختبار الشامل...\n\n";

// 1. اختبار التشفير
echo "1️⃣ اختبار التشفير...\n";
$password = 'TestPassword123';
$encrypted = SecurityManager::encrypt($password);
$decrypted = SecurityManager::decrypt($encrypted);
echo ($password === $decrypted) ? "✅ التشفير يعمل\n\n" : "❌ التشفير فاشل\n\n";

// 2. اختبار التحقق من البريد
echo "2️⃣ اختبار التحقق من البريد...\n";
$validEmail = 'test@example.com';
$result = EmailValidator::validate($validEmail);
echo $result['is_valid'] ? "✅ التحقق يعمل\n\n" : "❌ التحقق فاشل\n\n";

// 3. اختبار كشف Spam
echo "3️⃣ اختبار كشف Spam...\n";
$spamSubject = 'URGENT: Verify your account!';
$spamBody = 'Click here: http://bit.ly/scam';
$spamResult = EmailValidator::detectSpam($spamSubject, $spamBody);
echo $spamResult['is_suspicious'] ? "✅ كشف Spam يعمل\n\n" : "❌ كشف Spam فاشل\n\n";

// 4. اختبار Rate Limiter
echo "4️⃣ اختبار Rate Limiter...\n";
$rateLimiter = new RateLimiter($pdo);
$check = $rateLimiter->checkRateLimit('test@example.com', 'test');
echo $check['allowed'] ? "✅ Rate Limiter يعمل\n\n" : "❌ Rate Limiter فاشل\n\n";

// 5. اختبار الإرسال (اختياري - يتطلب SMTP)
echo "5️⃣ اختبار الإرسال...\n";
$mailer = new Mailer($pdo);
// $result = $mailer->sendCustomEmail('test@example.com', 'Test', 'Test Subject', '<p>Test Body</p>');
// echo $result ? "✅ الإرسال يعمل\n\n" : "❌ الإرسال فاشل\n\n";
echo "⏭️ تم تخطي اختبار الإرسال (تعليق)\n\n";

echo "✅ اكتمل الاختبار الشامل!";
?>
```

---

## 7. الصيانة

### مثال 1: حذف السجلات القديمة

```bash
# في Terminal
php app/maintenance-email-gdpr.php cleanup

# الناتج:
========================================
🗑️ حذف سجلات البريد القديمة (GDPR)
========================================

فترة الاحتفاظ: 90 يوم
تاريخ القطع: 2024-09-16

✅ تم حذف 458 سجل قديم من email_logs
✅ تم حذف 1250 سجل قديم من email_rate_limit_logs

✅ اكتملت عملية التنظيف!
```

---

### مثال 2: عرض الإحصائيات

```bash
# في Terminal
php app/maintenance-email-gdpr.php stats

# الناتج:
========================================
📊 إحصائيات نظام البريد الإلكتروني
========================================

📧 email_logs:
   إجمالي السجلات: 1258
   رسائل ناجحة: 1205 (95.79%)
   رسائل فاشلة: 53 (4.21%)

🚦 email_rate_limit_logs:
   إجمالي المحاولات: 3542
   محاولات ناجحة: 3450 (97.40%)
   محاولات فاشلة: 92 (2.60%)

📋 gdpr_policies:
   سياسات نشطة: 7
   فترة الاحتفاظ: 90 يوم
   الحد/الساعة: 100
   الحد/اليوم: 5
```

---

## 8. نصائح وأفضل الممارسات

### ✅ الممارسات الجيدة

```php
// 1. استخدم try-catch دائماً
try {
    $emailService->sendAndLog(...);
} catch (Exception $e) {
    error_log('Email failed: ' . $e->getMessage());
}

// 2. تحقق من الإعدادات قبل الإرسال
$autoSend = $pdo->query("SELECT value FROM system_settings WHERE `key` = 'auto_send_eval'")->fetchColumn();
if ($autoSend !== '1') {
    // لا ترسل
    return;
}

// 3. استخدم placeholders للقوالب
$placeholders = [
    'name' => htmlspecialchars($name), // XSS protection
    'score' => number_format($score, 2),
    'date' => date('Y-m-d')
];

// 4. سجّل دائماً
$emailService->sendAndLog(...); // بدلاً من sendEmail مباشرة

// 5. تحقق من Rate Limit
$rateLimiter = new RateLimiter($pdo);
$check = $rateLimiter->checkRateLimit($email, $sender);
if (!$check['allowed']) {
    // توقف
    return;
}
```

---

### ❌ الممارسات السيئة

```php
// ❌ لا تفعل: إرسال بدون تسجيل
$mailer->sendEmail(...); // سيئ

// ✅ افعل: استخدم EmailService
$emailService->sendAndLog(...); // جيد

// ❌ لا تفعل: تجاهل الأخطاء
$result = $mailer->sendEmail(...);
// لا تفعل شيء

// ✅ افعل: معالجة الأخطاء
if (!$result) {
    error_log('Email failed');
    // إخطار الإدارة
}

// ❌ لا تفعل: كشف كلمات المرور
echo $settings['smtp_pass']; // خطر!

// ✅ افعل: تشفير دائماً
$encrypted = SecurityManager::encrypt($password);
```

---

## الخلاصة

هذه الأمثلة تغطي **جميع السيناريوهات الشائعة**:
- ✅ الإعداد الأولي
- ✅ إرسال بسيط ومتقدم
- ✅ الأمان والتحقق
- ✅ الإحصائيات والمراقبة
- ✅ السيناريوهات الكاملة
- ✅ الصيانة
- ✅ أفضل الممارسات

**لمزيد من المعلومات:** راجع `EMAIL_SYSTEM_ANALYSIS.md`

---

**الإصدار:** 1.0  
**التاريخ:** 2024-12-15
