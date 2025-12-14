# أمثلة عملية - نظام البريد الإلكتروني 📧

---

## 🎯 السيناريوهات العملية

### السيناريو 1: شركة صغيرة (50 موظف)

**الوضع:**
- مدير واحد فقط (الرئيس)
- بدون مشرفين
- يريدون بريد بسيط عند التقييم

**الحل الموصى به:**
```
طريقة الحساب: manager_only
evaluation_email_manager_only_enabled: 1 (مفعل)
auto_send_eval: 1 (مفعل)

النتيجة:
✓ بريد واحد عند تقييم كل موظف
✓ بسيط وفعال
✓ لا توجد رسائل انتظار معقدة
```

---

### السيناريو 2: شركة متوسطة (200 موظف)

**الوضع:**
- عدة مدراء إدارات
- مشرفين مباشرين
- يريدون رسالة عند أي تقييم

**الحل الموصى به:**
```
طريقة الحساب: available_score
evaluation_email_available_score_mode: 'any' (أي تقييم)
auto_send_eval: 1 (مفعل)

النتيجة:
✓ بريد عند تقييم المدير
✓ بريد عند تقييم المشرف
✓ متوسط النتيجتين إذا قيما معاً
✓ مرن وشامل
```

---

### السيناريو 3: شركة كبيرة (1000+ موظف)

**الوضع:**
- هيكل تنظيمي معقد
- كل موظف له مشرف مباشر ومدير إدارة
- يريدون مراقبة دقيقة على الاكتمال

**الحل الموصى به:**
```
طريقة الحساب: average_complete (الافتراضي)
evaluation_email_average_complete_mode: 'waiting_supervisor_plus_final'
auto_send_eval: 1 (مفعل)

النتيجة:
✓ بريد "بانتظار المشرف" عند تقييم المدير
✓ يدفع المشرفين للعمل أسرع
✓ بريد "اكتمل" مع النتيجة النهائية
✓ مراقبة كاملة للعملية
```

---

## 💾 أمثلة من قاعدة البيانات

### 1️⃣ الإعدادات الحالية:

```sql
-- عرض جميع إعدادات البريد
SELECT `key`, `value` FROM system_settings 
WHERE `key` LIKE 'smtp_%' 
   OR `key` LIKE 'auto_send%' 
   OR `key` = 'evaluation_method'
   OR `key` LIKE 'evaluation_email%';

-- النتيجة:
┌──────────────────────────────────────────────────────────┐
│ key                                    │ value           │
├──────────────────────────────────────────────────────────┤
│ smtp_host                              │ smtp.gmail.com  │
│ smtp_port                              │ 587             │
│ smtp_user                              │ hr@company.com  │
│ smtp_pass                              │ ****            │
│ smtp_secure                            │ tls             │
│ smtp_from_email                        │ hr@company.com  │
│ smtp_from_name                         │ الموارد البشرية │
│ auto_send_eval                         │ 1               │
│ auto_send_user                         │ 1               │
│ evaluation_method                      │ average_complete│
│ evaluation_email_manager_only_enabled  │ 0               │
│ evaluation_email_available_score_mode  │ any             │
│ evaluation_email_average_complete_mode │ waiting_+_final │
└──────────────────────────────────────────────────────────┘
```

### 2️⃣ سجل البريد (Email Logs):

```sql
-- رسائل البريد المرسلة
SELECT id, employee_id, to_email, email_type, status, 
       error_message, created_at
FROM email_logs 
ORDER BY created_at DESC 
LIMIT 10;

-- النتيجة (مثال):
┌─────┬────────────────┬──────────────────┬──────────────────┬────────┬─────────────┐
│ id  │ employee_id    │ to_email         │ email_type       │ status │ created_at  │
├─────┬────────────────┬──────────────────┬──────────────────┬────────┬─────────────┤
│ 42  │ 15 (أحمد)      │ ahmad@mail.com   │ final_complete   │ success│ 2024-12-13  │
│ 41  │ 15 (أحمد)      │ ahmad@mail.com   │ waiting_supervisor│success│ 2024-12-13  │
│ 40  │ 16 (فاطمة)     │ fatima@mail.com  │ manager_evaluated│ success│ 2024-12-13  │
│ 39  │ 17 (علي)       │ ali@mail.com     │ supervisor_eval  │ failure│ 2024-12-12  │
│     │                │                  │                  │        │ SMTP error  │
└─────┴────────────────┴──────────────────┴──────────────────┴────────┴─────────────┘

-- إحصائيات سريعة
SELECT email_type, status, COUNT(*) as count 
FROM email_logs 
GROUP BY email_type, status;

-- النتيجة:
┌──────────────────────┬─────────┬───────┐
│ email_type           │ status  │ count │
├──────────────────────┼─────────┼───────┤
│ manager_evaluated    │ success │ 45    │
│ supervisor_evaluated │ success │ 38    │
│ available_any        │ success │ 12    │
│ final_complete       │ success │ 28    │
│ waiting_supervisor   │ success │ 25    │
│ waiting_manager      │ success │ 3     │
│ final_complete       │ failure │ 2     │
└──────────────────────┴─────────┴───────┘

-- نسبة النجاح:
SELECT 
    COUNT(*) as total,
    SUM(IF(status='success', 1, 0)) as success,
    SUM(IF(status='failure', 1, 0)) as failure,
    ROUND(SUM(IF(status='success', 1, 0)) / COUNT(*) * 100, 2) as success_rate
FROM email_logs;

-- النتيجة:
┌───────┬─────────┬─────────┬──────────────┐
│ total │ success │ failure │ success_rate │
├───────┼─────────┼─────────┼──────────────┤
│ 156   │ 151     │ 5       │ 96.79%       │
└───────┴─────────┴─────────┴──────────────┘
```

---

## 🔍 أمثلة من الكود

### مثال 1: جلب إعدادات البريد يدويًا

```php
<?php
require_once '../../app/core/db.php';

// جلب إعداد واحد
$stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = ?");
$stmt->execute(['auto_send_eval']);
$auto_send_eval = $stmt->fetchColumn();

echo "Sending emails? " . ($auto_send_eval ? "YES" : "NO");

// جلب عدة إعدادات
$settings = $pdo->query("SELECT `key`, `value` FROM system_settings 
                         WHERE `key` LIKE 'evaluation_email%'")->fetchAll(PDO::FETCH_KEY_PAIR);

foreach($settings as $key => $value) {
    echo "$key = $value\n";
}
?>
```

### مثال 2: إرسال بريد يدوي

```php
<?php
require_once '../../app/core/db.php';
require_once '../../app/core/Mailer.php';

$mailer = new Mailer($pdo);

// بريد بسيط
$sent = $mailer->sendCustomEmail(
    'employee@mail.com',
    'أحمد محمود',
    'موضوع البريد',
    '<h1>مرحباً أحمد</h1><p>نص البريد هنا</p>'
);

if ($sent) {
    echo "✓ تم الإرسال بنجاح";
} else {
    echo "✗ فشل الإرسال - تحقق من error_log";
}
?>
```

### مثال 3: استدعاء EmailService

```php
<?php
require_once '../../app/core/db.php';
require_once '../../app/core/EmailService.php';

$emailService = new EmailService($pdo);

// عند إرسال تقييم من مدير
$emailService->handleEvaluationSubmitted(
    $employee_id = 15,      // معرف الموظف
    $cycle_id = 3,          // معرف دورة التقييم
    $evaluator_role = 'manager',  // الدور
    $evaluator_id = 8       // معرف المقيّم
);

// النظام يقرر تلقائياً:
// - ما إذا كان يجب الإرسال
// - نوع الرسالة
// - محتوى الرسالة
// - ويسجل كل شيء في email_logs
?>
```

### مثال 4: عرض سجل البريد

```php
<?php
require_once '../../app/core/db.php';

// آخر 20 رسالة
$logs = $pdo->query("
    SELECT 
        l.id,
        l.to_email,
        l.email_type,
        l.status,
        l.error_message,
        l.created_at,
        u.name as employee_name
    FROM email_logs l
    LEFT JOIN users u ON l.employee_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 20
")->fetchAll();

foreach($logs as $log) {
    $status_icon = $log['status'] == 'success' ? '✓' : '✗';
    echo "$status_icon {$log['employee_name']} - {$log['email_type']} - {$log['created_at']}\n";
    if ($log['error_message']) {
        echo "   خطأ: {$log['error_message']}\n";
    }
}
?>
```

---

## 🧪 سيناريوهات الاختبار

### الاختبار 1: اختبار SMTP

```php
<?php
require_once '../../app/core/db.php';
require_once '../../app/core/Mailer.php';

// جرّب الاتصال
try {
    $mailer = new Mailer($pdo);
    
    $result = $mailer->sendCustomEmail(
        'your-email@gmail.com',
        'أنت',
        'اختبار البريد',
        'هذا بريد اختبار'
    );
    
    if ($result) {
        echo "✅ نجح الاتصال! تحقق من بريدك.\n";
    } else {
        echo "❌ فشل الإرسال - تحقق من error_log\n";
    }
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
```

### الاختبار 2: محاكاة تقييم كامل

```php
<?php
require_once '../../app/core/db.php';
require_once '../../app/core/EmailService.php';

// 1. احصل على دورة نشطة
$cycle = $pdo->query("SELECT id FROM evaluation_cycles WHERE status='active' LIMIT 1")->fetch();

if (!$cycle) {
    echo "❌ لا توجد دورة نشطة\n";
    exit;
}

// 2. احصل على موظف لديه رئيس
$employee = $pdo->query("
    SELECT u.id, u.name 
    FROM users u 
    WHERE supervisor_id IS NOT NULL 
    AND status='active' 
    LIMIT 1
")->fetch();

if (!$employee) {
    echo "❌ لا يوجد موظف لديه رئيس\n";
    exit;
}

// 3. احصل على مدير
$manager = $pdo->query("SELECT id FROM users WHERE role='manager' LIMIT 1")->fetch();

// 4. محاكاة الإرسال
$emailService = new EmailService($pdo);

echo "محاكاة: موظف {$employee['name']} - دورة {$cycle['id']}\n";
echo "الإرسال كـ: مدير {$manager['id']}\n";

$emailService->handleEvaluationSubmitted(
    $employee['id'],
    $cycle['id'],
    'manager',
    $manager['id']
);

// 5. تحقق من السجل
$logs = $pdo->query("
    SELECT email_type, status 
    FROM email_logs 
    WHERE employee_id = {$employee['id']}
    AND cycle_id = {$cycle['id']}
    ORDER BY created_at DESC 
    LIMIT 1
")->fetch();

if ($logs) {
    echo "✓ تم التسجيل: {$logs['email_type']} - {$logs['status']}\n";
} else {
    echo "✗ لم يتم التسجيل\n";
}
?>
```

---

## 📊 أوامر MySQL مفيدة

### عرض آخر الأخطاء
```sql
SELECT email_type, error_message, COUNT(*) as count, MAX(created_at) as last_time
FROM email_logs 
WHERE status = 'failure'
GROUP BY email_type, error_message
ORDER BY last_time DESC;
```

### عرض إحصائيات يومية
```sql
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total,
    SUM(IF(status='success', 1, 0)) as success,
    SUM(IF(status='failure', 1, 0)) as failure
FROM email_logs
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

### حذف السجلات القديمة (تنظيف)
```sql
DELETE FROM email_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## 🎬 الخطوات الأولى

### 1. تثبيت وإعداد

```bash
# تأكد من تثبيت Composer
composer install

# تحقق من vendor/autoload.php موجود
ls vendor/autoload.php
```

### 2. تكوين SMTP

```
اذهب إلى: http://yoursite.com/public/admin/email_settings.php

أدخل:
- SMTP Host: smtp.gmail.com (أو خادمك)
- SMTP Port: 587
- Username: your-email@gmail.com
- Password: your-app-password (ليست كلمة المرور العادية!)
- From Email: your-email@gmail.com
- From Name: الموارد البشرية
- ✓ فعّل: auto_send_eval
```

### 3. اختبر الإرسال

```
اذهب إلى: http://yoursite.com/public/admin/bulk_email.php

اختر:
- Target: جميع المستخدمين
- Subject: اختبار
- Message: هذا بريد اختبار

اضغط: إرسال

تحقق من: بريدك الإلكتروني
```

### 4. راقب السجلات

```sql
-- في phpMyAdmin أو terminal
SELECT * FROM email_logs 
ORDER BY created_at DESC 
LIMIT 10;
```

---

**آخر تحديث:** 2024-12-13
