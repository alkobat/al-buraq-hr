# تحسينات أمان البريد الإلكتروني والتشفير

## نظرة عامة
تم تنفيذ تحسينات شاملة لأمان نظام البريد الإلكتروني تشمل:
- ✅ تشفير AES-256 لكلمات المرور
- ✅ حد التصنيف (Rate Limiting)
- ✅ التحقق من صحة البريد الإلكتروني
- ✅ حماية من الـ Spam
- ✅ سياسات الخصوصية (GDPR)

---

## 1. تشفير كلمات المرور (AES-256)

### الوصف
تشفير SMTP Password في قاعدة البيانات باستخدام AES-256-GCM.

### الملفات
- **app/core/SecurityManager.php** - فئة التشفير الرئيسية
- **app/setup-encryption.php** - سكريبت إعداد المفتاح

### المميزات
- ✅ تشفير قوي (AES-256-GCM)
- ✅ استخدام IV عشوائي لكل عملية
- ✅ التحقق من سلامة البيانات (Authentication Tag)
- ✅ فك تشفير تلقائي عند الاستخدام
- ✅ معالجة الأخطاء الآمنة

### الإعداد

#### 1. توليد مفتاح التشفير
```bash
php app/setup-encryption.php
```

هذا يولد مفتاح عشوائي 256-بت ويحفظه في `.env`:
```
ENCRYPTION_KEY=a1b2c3d4e5f6...
```

#### 2. تشفير كلمة المرور القديمة
يمكن تشفير كلمة المرور الحالية من خلال نفس السكريبت.

### الاستخدام

```php
require_once 'app/core/SecurityManager.php';

// التشفير
$encrypted = SecurityManager::encrypt('my_password');

// فك التشفير
$plaintext = SecurityManager::decrypt($encrypted);
```

---

## 2. حد التصنيف (Rate Limiting)

### الوصف
منع الإرسال المفرط للبريد الإلكتروني.

### الملفات
- **app/core/RateLimiter.php** - فئة حد التصنيف
- **migrations/add_email_security_tables.sql** - جدول السجلات

### الحدود الافتراضية
- 100 رسالة في الساعة (إجمالي)
- 5 رسائل لنفس المستقبل يومياً

### المميزات
- ✅ التحقق من الحدود قبل الإرسال
- ✅ تسجيل محاولات الإرسال
- ✅ إحصائيات فصلية
- ✅ حذف السجلات القديمة (GDPR)

### جدول السجلات
```sql
CREATE TABLE email_rate_limit_logs (
  id INT PRIMARY KEY,
  recipient_email VARCHAR(150),
  sender_id VARCHAR(50),
  success TINYINT,
  attempted_at TIMESTAMP
);
```

### الاستخدام

```php
$rateLimiter = new RateLimiter($pdo);

// التحقق من حد التصنيف
$check = $rateLimiter->checkRateLimit('user@example.com', 'sender_id');
if (!$check['allowed']) {
    echo "خطأ: " . $check['reason'];
    return;
}

// تسجيل محاولة الإرسال
$rateLimiter->logAttempt('user@example.com', true, 'sender_id');

// الحصول على الإحصائيات
$stats = $rateLimiter->getStats('user@example.com');

// حذف السجلات القديمة
$rateLimiter->deleteOldLogs(90);
```

---

## 3. التحقق من صحة البريد (Email Validation)

### الوصف
التحقق من صيغة البريد والكشف عن الرسائل المريبة.

### الملفات
- **app/core/EmailValidator.php** - فئة التحقق والتصفية

### المميزات
- ✅ التحقق من صحة الصيغة
- ✅ التحقق من صحة النطاق
- ✅ الكشف عن الـ Spam
- ✅ البحث عن الروابط المريبة
- ✅ تطهير قوائم البريد

### أنماط الـ Spam المكتشفة
- `verify.*account` - طلب التحقق من الحساب
- `confirm.*password` - طلب تأكيد كلمة المرور
- `click.*urgent` - طلب عاجل للنقر
- `act.*immediately` - التصرف الفوري
- `update.*payment` - تحديث البيانات المالية
- `suspended|blocked` - تصريح بإيقاف الحساب
- `bitcoin|ethereum|crypto` - عملات رقمية
- `lottery|prize|claim` - جوائز/يانصيب

### الروابط المريبة المكتشفة
- Shortened URLs: bit.ly, tinyurl, goo.gl, إلخ
- IP-based URLs: `http://192.168.1.1/...`
- JavaScript/Data protocols

### الاستخدام

```php
require_once 'app/core/EmailValidator.php';

// التحقق من صحة البريد
$validation = EmailValidator::validate('user@example.com');
if (!$validation['is_valid']) {
    echo "خطأ: " . $validation['message'];
}

// الكشف عن الـ Spam
$spam = EmailValidator::detectSpam($subject, $body);
if ($spam['is_suspicious']) {
    echo "تحذير: " . implode(', ', $spam['reasons']);
}

// البحث عن الروابط المريبة
$links = EmailValidator::findSuspiciousLinks($body);
if ($links['has_suspicious_links']) {
    echo "روابط مريبة: " . implode(', ', $links['links']);
}

// تنظيف قائمة البريد
$cleanEmails = EmailValidator::sanitizeEmailList($emails);
```

---

## 4. حماية من الـ Spam

### الوصف
تصفية الرسائل المريبة ومنع الإرسال المتكرر.

### الآليات المطبقة
1. **الكشف عن أنماط Spam** - باستخدام التعبيرات النمطية
2. **فحص محتوى الرسالة** - أحرف كبيرة مفرطة، علامات ترقيم
3. **منع الروابط المريبة** - URLات مختصرة، IP-based URLs
4. **حد التصنيف** - منع الإرسال المفرط

### التطبيق
يتم التحقق تلقائياً عند استدعاء `sendAndLog()`:

```php
// التحقق من الـ Spam
$spamCheck = EmailValidator::detectSpam($subject, $body);
if ($spamCheck['is_suspicious']) {
    // تسجيل الفشل مع السبب
    $this->logEmail(..., 'failure', 'رسالة مريبة', $meta);
    return;
}

// البحث عن الروابط المريبة
$linkCheck = EmailValidator::findSuspiciousLinks($body);
if ($linkCheck['has_suspicious_links']) {
    // تسجيل الفشل
    return;
}
```

---

## 5. سياسات الخصوصية (GDPR)

### الوصف
حماية بيانات المستخدمين وامتثال معايير GDPR.

### الملفات
- **migrations/add_email_security_tables.sql** - جداول السياسات
- **app/core/EmailService.php** - دوال الخصوصية

### المميزات
- ✅ تشفير بيانات المستقبل في السجلات
- ✅ إخفاء الهوية باستخدام Hash
- ✅ حذف السجلات القديمة تلقائياً
- ✅ تصدير بيانات الموظف
- ✅ حق حذف البيانات
- ✅ جدول سياسات GDPR

### جدول السياسات

```sql
CREATE TABLE gdpr_policies (
  id INT PRIMARY KEY,
  policy_key VARCHAR(100) UNIQUE,
  policy_name VARCHAR(255),
  policy_value TEXT,
  description TEXT,
  is_active TINYINT,
  updated_at TIMESTAMP
);
```

### السياسات الافتراضية

| Policy Key | الاسم | القيمة | الوصف |
|-----------|------|--------|------|
| email_logs_retention_days | فترة الاحتفاظ | 90 | أيام الاحتفاظ بسجلات البريد |
| max_emails_per_hour | الحد/الساعة | 100 | الحد الأقصى للبريد بالساعة |
| max_emails_per_recipient_daily | الحد/اليوم | 5 | الحد الأقصى للمستقبل يومياً |
| encrypt_sensitive_data | التشفير | 1 | تشفير البيانات الحساسة |
| anonymize_email_logs | إخفاء الهوية | 1 | استخدام hash للبريد |
| allow_data_export | التصدير | 1 | السماح بتصدير البيانات |
| allow_data_deletion | الحذف | 1 | السماح بحذف البيانات |

### تحديثات قاعدة البيانات

```sql
-- إضافة أعمدة التشفير والخصوصية
ALTER TABLE email_logs ADD COLUMN recipient_email_hash VARCHAR(64);
ALTER TABLE email_logs ADD COLUMN is_encrypted TINYINT DEFAULT 0;
ALTER TABLE system_settings ADD COLUMN is_encrypted TINYINT DEFAULT 0;
```

### الاستخدام

```php
$emailService = new EmailService($pdo);

// تنظيف السجلات القديمة
$deleted = $emailService->cleanupOldEmailLogs(90);

// الحصول على سجل الموظف
$logs = $emailService->getEmployeeEmailLogs($employeeId, 50);

// حذف بيانات الموظف (Right to be Forgotten)
$emailService->deleteEmployeeEmailData($employeeId);

// تصدير بيانات الموظف (Data Export Request)
$exportData = $emailService->exportEmployeeEmailData($employeeId);
json_encode($exportData);

// الحصول على الإحصائيات
$stats = $emailService->getEmailStats();
```

---

## 6. سكريبتات الصيانة

### سكريبت إعداد التشفير

```bash
php app/setup-encryption.php
```

**المهام:**
1. توليد مفتاح التشفير العشوائي
2. حفظ المفتاح في `.env`
3. تشفير كلمة المرور الحالية (اختياري)

### سكريبت صيانة GDPR

```bash
# حذف السجلات القديمة
php app/maintenance-email-gdpr.php cleanup

# عرض الإحصائيات
php app/maintenance-email-gdpr.php stats

# تشغيل جميع المهام
php app/maintenance-email-gdpr.php all
```

---

## 7. تكامل مع EmailService

### تدفق الفحص

```
┌─────────────────────────┐
│  sendAndLog()           │
└────────┬────────────────┘
         │
    1. EmailValidator::validate() ──┐
         │                          │
    2. EmailValidator::detectSpam() ├─ قبول أم رفض؟
         │                          │
    3. EmailValidator::findSuspiciousLinks() ──┤
         │
    4. RateLimiter::checkRateLimit()
         │
    5. SecurityManager::sanitizeEmailContent()
         │
    6. Mailer::sendCustomEmail()
         │
    7. RateLimiter::logAttempt()
         │
    8. EmailService::logEmail()
         │
┌────────▼────────────────┐
│  تسجيل النتيجة          │
└─────────────────────────┘
```

### مثال عملي

```php
// بيانات الرسالة
$employeeId = 1;
$cycleId = 2025;
$toEmail = 'user@example.com';
$toName = 'الموظف';
$subject = 'تقييم الأداء';
$body = 'محتوى التقييم...';
$emailType = 'evaluation_notification';

// إرسال الرسالة مع جميع الفحوصات
$emailService->sendAndLog(
    $employeeId, 
    $cycleId, 
    $toEmail, 
    $toName, 
    $subject, 
    $body, 
    $emailType
);

// النتائج المحتملة:
// - إذا فشل التحقق: تسجيل "فشل" مع السبب
// - إذا نجح: تسجيل "نجاح" + تحديث إحصائيات حد التصنيف
```

---

## 8. متطلبات الأمان

### البيئة

```bash
# .env
ENCRYPTION_KEY=a1b2c3d4e5f6...
```

### PHP Extensions

- `openssl` - للتشفير
- `pdo` - لقاعدة البيانات
- `json` - للـ metadata

---

## 9. الأخطاء الشائعة والحلول

### المشكلة: "مفتاح التشفير غير محدد"
**الحل:** تشغيل سكريبت الإعداد
```bash
php app/setup-encryption.php
```

### المشكلة: "فشل فك التشفير"
**الحل:** التأكد من أن المفتاح لم يتغير
```bash
# التحقق من المفتاح في .env
cat .env | grep ENCRYPTION_KEY
```

### المشكلة: "تجاوز حد التصنيف"
**الحل:** زيادة الحدود في الإعدادات أو حذف السجلات القديمة
```bash
php app/maintenance-email-gdpr.php cleanup
```

---

## 10. Logging والمراقبة

### السجلات
- `/error_log` - أخطاء التشفير والمرسل
- `email_logs` table - سجلات إرسال البريد
- `email_rate_limit_logs` table - سجلات محاولات الإرسال

### الإحصائيات

```php
$stats = $emailService->getEmailStats();

// النتائج:
$stats['total_sent'];           // الرسائل المرسلة
$stats['total_failed'];         // الرسائل الفاشلة
$stats['total_logs'];           // إجمالي السجلات
$stats['rate_limit_violations']; // تجاوزات حد التصنيف
$stats['spam_detected'];        // رسائل مريبة
```

---

## 11. التحديثات المستقبلية المقترحة

- [ ] إضافة Two-Factor Authentication للإعدادات الحساسة
- [ ] تطبيق IP Whitelist للإرسال
- [ ] إضافة Certificate Pinning للاتصالات
- [ ] دعم PGP/GPG للتوقيع الرقمي
- [ ] تحسين الكشف عن Phishing
- [ ] إضافة Machine Learning للكشف عن الـ Spam

---

## الترخيص والدعم

تم تطوير هذه الميزات لضمان أمان وخصوصية المستخدمين.

للمزيد من المعلومات، يرجى الاتصال بفريق التطوير.
