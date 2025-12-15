# 📧 تحليل شامل لنظام البريد الإلكتروني

## نظرة عامة

نظام البريد الإلكتروني في تطبيق تقييم الأداء للخطوط الجوية البراق هو نظام متكامل ومتطور يدعم:
- ✅ إرسال إشعارات التقييم للموظفين
- ✅ ثلاث طرق مختلفة لحساب التقييم النهائي
- ✅ أمان متقدم (تشفير، حد تصنيف، كشف spam)
- ✅ Dashboard شامل للمراقبة
- ✅ امتثال GDPR

---

## البنية المعمارية

### 1. الطبقات الأساسية

```
┌─────────────────────────────────────┐
│        User Interface Layer         │
│  (Dashboard, Settings, Email Test)  │
└─────────────┬───────────────────────┘
              │
┌─────────────▼───────────────────────┐
│      Service Layer (EmailService)   │
│  - handleEvaluationSubmitted()      │
│  - sendAndLog()                     │
│  - getEmployeeScores()              │
└─────────────┬───────────────────────┘
              │
┌─────────────▼───────────────────────┐
│     Core Components Layer           │
│  ┌──────────┬──────────┬─────────┐  │
│  │ Mailer   │Validator │Security │  │
│  │          │          │Manager  │  │
│  └──────────┴──────────┴─────────┘  │
│  ┌──────────┬──────────────────┐    │
│  │RateLimiter│EmailStatistics │    │
│  └──────────┴──────────────────┘    │
└─────────────┬───────────────────────┘
              │
┌─────────────▼───────────────────────┐
│        Data Layer (Database)        │
│  - email_logs                       │
│  - email_rate_limit_logs            │
│  - gdpr_policies                    │
│  - system_settings                  │
└─────────────────────────────────────┘
```

---

## مكونات النظام

### 1. Mailer (app/core/Mailer.php)

**المسؤولية:** إرسال البريد الفعلي عبر SMTP

**الوظائف الرئيسية:**
- `sendEmail($toEmail, $toName, $templateType, $placeholders)` - إرسال عبر قوالب
- `sendCustomEmail($toEmail, $toName, $subject, $body)` - إرسال مخصص
- `getMailer()` - إعداد PHPMailer

**المميزات:**
- دعم PHPMailer 6.9
- تشفير تلقائي لكلمات المرور (SecurityManager)
- دعم المرفقات
- معالجة أخطاء شاملة

**التبعيات:**
- PHPMailer\PHPMailer\PHPMailer
- SecurityManager

---

### 2. EmailService (app/core/EmailService.php)

**المسؤولية:** منطق الأعمال للبريد

**الوظائف الرئيسية:**
- `handleEvaluationSubmitted()` - معالجة إرسال تقييم
- `sendAndLog()` - إرسال مع تسجيل
- `sendEvaluationNotification()` - إشعار التقييم
- `getEmployeeScores()` - الحصول على الدرجات

**الإعدادات المدعومة:**

| الإعداد | الوصف | القيمة الافتراضية |
|---------|-------|-------------------|
| auto_send_eval | Master Toggle | '0' (معطل) |
| evaluation_email_manager_only_enabled | تفعيل طريقة manager_only | '0' |
| evaluation_email_available_score_mode | وضع available_score | 'any' |
| evaluation_email_average_complete_mode | وضع average_complete | 'waiting_supervisor_plus_final' |

**منطق الإرسال:**

```php
// 1. التحقق من Master Toggle
if (auto_send_eval !== '1') return;

// 2. حسب طريقة الحساب
switch ($method) {
    case 'manager_only':
        // إرسال عند تقييم المدير فقط
        break;
    
    case 'available_score':
        // إرسال حسب الوضع (any/manager_only/supervisor_only/both)
        break;
    
    case 'average_complete':
        // إرسال عند الاكتمال أو حسب الوضع
        break;
}

// 3. التحقق من عدم التكرار
if (tokenExists) return;

// 4. التحقق من Rate Limit
if (!rateLimiter->checkRateLimit()) return;

// 5. التحقق من Spam
if (validator->detectSpam()) return;

// 6. الإرسال الفعلي
mailer->sendCustomEmail();

// 7. التسجيل
logEmail();
```

---

### 3. EmailValidator (app/core/EmailValidator.php)

**المسؤولية:** التحقق من صحة البريد والكشف عن Spam

**الوظائف:**
- `validate($email)` - التحقق من صحة البريد
- `detectSpam($subject, $body)` - كشف الرسائل المريبة
- `findSuspiciousLinks($body)` - كشف الروابط المريبة
- `sanitizeEmailList($emails)` - تنظيف قائمة

**أنماط Spam المكتشفة:**
```regex
verify.*account
confirm.*password
click.*urgent
act.*immediately
update.*payment
suspended|blocked
bitcoin|ethereum|crypto
lottery|prize|claim
free.*money|cash
```

**الروابط المريبة:**
- Shortened URLs: bit.ly, tinyurl, goo.gl, ow.ly
- IP-based URLs: http://192.168.1.1/
- Data/JavaScript protocols

---

### 4. SecurityManager (app/core/SecurityManager.php)

**المسؤولية:** تشفير وفك تشفير البيانات الحساسة

**الخوارزمية:** AES-256-GCM

**الوظائف:**
- `encrypt($plaintext)` - تشفير
- `decrypt($ciphertext)` - فك التشفير
- `sanitizeEmailContent($content)` - تنظيف المحتوى

**آلية التشفير:**
```php
// التشفير
1. توليد IV عشوائي (16 بايت)
2. تشفير البيانات بـ AES-256-GCM
3. استخراج Authentication Tag
4. دمج: IV + Ciphertext + Tag
5. ترميز Base64

// فك التشفير
1. فك ترميز Base64
2. استخراج IV (أول 16 بايت)
3. استخراج Tag (آخر 16 بايت)
4. فك تشفير Ciphertext
5. التحقق من Tag
```

**المتطلبات:**
- PHP extension: openssl
- .env: ENCRYPTION_KEY (256-bit hex)

---

### 5. RateLimiter (app/core/RateLimiter.php)

**المسؤولية:** منع الإرسال المفرط

**الحدود الافتراضية:**
- 100 رسالة في الساعة (إجمالي)
- 5 رسائل لنفس المستقبل يومياً

**الوظائف:**
- `checkRateLimit($email, $sender)` - التحقق
- `logAttempt($email, $success, $sender)` - تسجيل
- `getStats($email)` - إحصائيات
- `deleteOldLogs($days)` - حذف قديم

**آلية العمل:**
```php
// 1. فحص الحد الإجمالي (آخر ساعة)
$hourlyCount = COUNT emails WHERE attempted_at > NOW() - 1 HOUR;
if ($hourlyCount >= 100) return FAIL;

// 2. فحص حد المستقبل (آخر يوم)
$dailyCount = COUNT emails WHERE recipient = $email AND attempted_at > NOW() - 1 DAY;
if ($dailyCount >= 5) return FAIL;

// 3. السماح بالإرسال
return ALLOW;
```

---

### 6. EmailStatistics (app/core/EmailStatistics.php)

**المسؤولية:** حساب الإحصائيات والتقارير

**الوظائف:**
- `getTodayStats()` - إحصائيات اليوم
- `getEmailLogs($page, $limit, $filters)` - السجلات مع فلترة
- `getStatsByType()` - حسب النوع
- `getStatsByRecipient($limit)` - حسب المستقبل
- `getDailyStats($days)` - يومي (للرسوم البيانية)
- `getFailedEmails($limit)` - الفاشلة
- `getAlerts()` - تنبيهات النظام

**مثال على الناتج:**
```php
[
    'today_sent' => 45,
    'today_failed' => 3,
    'today_success_rate' => 93.75,
    'total_emails' => 1250,
    'failed_last_hour' => 1,
    'no_activity_hours' => 0
]
```

---

## تدفق البيانات

### سيناريو 1: إرسال إشعار تقييم

```
1. المدير يقيّم الموظف
   ↓
2. manager/evaluate.php
   ↓
3. EmailService->handleEvaluationSubmitted(
     employeeId, cycleId, 'manager', managerId
   )
   ↓
4. فحص Master Toggle (auto_send_eval)
   ↓
5. تحديد طريقة الحساب
   ↓
6. فحص الإعدادات المناسبة
   ↓
7. إنشاء/جلب Token
   ↓
8. EmailService->sendAndLog()
   ↓
9. EmailValidator->validate()
   ↓
10. EmailValidator->detectSpam()
   ↓
11. RateLimiter->checkRateLimit()
   ↓
12. Mailer->sendCustomEmail()
   ↓
13. RateLimiter->logAttempt()
   ↓
14. EmailService->logEmail()
   ↓
15. تسجيل في email_logs
```

---

### سيناريو 2: مراقبة Dashboard

```
1. Admin يفتح email-dashboard.php
   ↓
2. EmailStatistics->getTodayStats()
   ↓
3. EmailStatistics->getDailyStats(30)
   ↓
4. EmailStatistics->getLastEmails(5)
   ↓
5. EmailStatistics->getAlerts()
   ↓
6. عرض الإحصائيات + الرسوم البيانية
```

---

## قاعدة البيانات

### جدول email_logs

**الغرض:** تسجيل جميع محاولات الإرسال

```sql
CREATE TABLE email_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT,                    -- معرف الموظف
  cycle_id INT,                       -- معرف دورة التقييم
  to_email VARCHAR(150),              -- البريد المستقبل
  recipient_email_hash VARCHAR(64),   -- Hash للخصوصية
  is_encrypted TINYINT,               -- علامة التشفير
  subject VARCHAR(255),               -- الموضوع
  body MEDIUMTEXT,                    -- المحتوى
  email_type VARCHAR(50),             -- نوع الرسالة
  status ENUM('success','failure'),   -- الحالة
  error_message TEXT,                 -- رسالة الخطأ
  metadata TEXT,                      -- JSON بيانات إضافية
  created_at TIMESTAMP                -- وقت الإنشاء
);
```

**أنواع البريد (email_type):**
- `evaluation_notification` - إشعار تقييم
- `manager_evaluated` - تقييم المدير
- `supervisor_evaluated` - تقييم المشرف
- `final_complete` - تقييم نهائي مكتمل
- `waiting_supervisor` - انتظار المشرف
- `new_user` - مستخدم جديد

---

### جدول email_rate_limit_logs

**الغرض:** تتبع محاولات الإرسال للـ Rate Limiting

```sql
CREATE TABLE email_rate_limit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  recipient_email VARCHAR(150),     -- البريد المستقبل
  sender_id VARCHAR(50),            -- معرف المرسل
  success TINYINT(1),               -- نجح/فشل
  attempted_at TIMESTAMP            -- وقت المحاولة
);
```

---

### جدول gdpr_policies

**الغرض:** سياسات الخصوصية وGDPR

```sql
CREATE TABLE gdpr_policies (
  id INT PRIMARY KEY AUTO_INCREMENT,
  policy_key VARCHAR(100) UNIQUE,   -- مفتاح السياسة
  policy_name VARCHAR(255),         -- الاسم
  policy_value TEXT,                -- القيمة
  description TEXT,                 -- الوصف
  is_active TINYINT(1),             -- نشط؟
  updated_at TIMESTAMP              -- آخر تحديث
);
```

**السياسات:**
1. email_logs_retention_days (90)
2. max_emails_per_hour (100)
3. max_emails_per_recipient_daily (5)
4. encrypt_sensitive_data (1)
5. anonymize_email_logs (1)
6. allow_data_export (1)
7. allow_data_deletion (1)

---

## Dashboard

### صفحة email-dashboard.php

**الميزات:**
- 4 بطاقات إحصائية
  - رسائل اليوم
  - الرسائل الفاشلة
  - نسبة النجاح
  - الإجمالي الكلي
- رسم بياني خطي (30 يوم)
- رسم بياني دائري (نجاح/فشل)
- جدول آخر 5 رسائل
- قسم التنبيهات
- أزرار سريعة

**التقنيات:**
- Bootstrap 5
- Chart.js
- Font Awesome
- RTL support

---

### صفحة email-logs.php

**الميزات:**
- فلترة متقدمة
  - تاريخ من-إلى
  - الحالة (نجاح/فشل)
  - نوع البريد
  - البحث في المستقبل
  - البحث في الموضوع
- عرض مفصل (Modal)
- إعادة إرسال للفاشلة
- ترقيم الصفحات (20/صفحة)

---

### صفحة email-test.php

**الميزات:**
- نموذج إرسال تجريبي
- عرض إعدادات SMTP
- قائمة مراجعة
- معلومات الخوادم
- دليل حل المشاكل

---

## الأمان

### 1. تشفير AES-256-GCM

**المستوى:** عسكري  
**الخوارزمية:** AES-256 في وضع GCM  
**حجم المفتاح:** 256 بت  
**IV:** عشوائي لكل عملية  
**التحقق:** Authentication Tag

---

### 2. Rate Limiting

**الهدف:** منع الإساءة والتكرار المفرط

**الحدود:**
- إجمالي: 100/ساعة
- لكل مستقبل: 5/يوم

**الآلية:**
- فحص قبل الإرسال
- تسجيل كل محاولة
- حذف تلقائي للقديم

---

### 3. Spam Detection

**الأنماط:** 15+ نمط regex  
**الروابط:** 10+ نوع مريب  
**الإجراء:** رفض فوري + تسجيل

---

### 4. GDPR Compliance

**الميزات:**
- تشفير البيانات الحساسة
- إخفاء الهوية (Hash)
- حذف تلقائي بعد 90 يوم
- حق التصدير
- حق الحذف
- سجل واضح

---

## الأداء

### تحسينات مطبقة

1. **Indexes مناسبة:**
   ```sql
   KEY idx_email_logs_employee_cycle (employee_id, cycle_id)
   KEY idx_email_logs_type_status (email_type, status)
   KEY idx_recipient_hash (recipient_email_hash)
   KEY idx_recipient_time (recipient_email, attempted_at)
   ```

2. **Pagination:**
   - Dashboard: 5 رسائل
   - Logs: 20 رسالة/صفحة
   - Stats: آخر 30 يوم

3. **Caching:**
   - إعدادات SMTP تُحمّل مرة واحدة
   - Prepared statements

4. **Lazy Loading:**
   - تحميل التفاصيل عند الطلب
   - Modal للعرض المفصل

---

## الاختبار

### Unit Tests

**ملف:** tests/EmailServiceTest.php (607 سطر)

**الفئات:**
1. اختبارات الإرسال (5)
2. اختبارات طرق الحساب (6)
3. اختبارات منع التكرار (3)
4. اختبارات Rate Limiting (4)
5. اختبارات الأمان (2)

**تشغيل:**
```bash
vendor/bin/phpunit
```

---

### Integration Tests

**مطلوب:**
- [ ] اختبار Dashboard في المتصفح
- [ ] اختبار إرسال بريد حقيقي
- [ ] اختبار SMTP Test Page
- [ ] اختبار Filters والبحث
- [ ] اختبار إعادة الإرسال
- [ ] اختبار Responsive Design

---

## الصيانة

### سكريبتات الصيانة

**1. setup-encryption.php**
```bash
php app/setup-encryption.php
```
- توليد ENCRYPTION_KEY
- حفظ في .env
- تشفير كلمة مرور SMTP

**2. maintenance-email-gdpr.php**
```bash
# حذف السجلات القديمة
php app/maintenance-email-gdpr.php cleanup

# عرض الإحصائيات
php app/maintenance-email-gdpr.php stats

# تشغيل جميع المهام
php app/maintenance-email-gdpr.php all
```

---

### المهام الدورية المقترحة

| المهمة | التكرار | الأمر |
|--------|----------|-------|
| حذف سجلات قديمة | أسبوعي | `cleanup` |
| مراجعة الفاشلة | يومي | Dashboard |
| تحديث الإحصائيات | يومي | `stats` |
| نسخ احتياطي | يومي | mysqldump |

---

## الأخطاء الشائعة والحلول

### 1. PHPMailer غير موجود
```bash
composer install
```

### 2. مفتاح التشفير مفقود
```bash
php app/setup-encryption.php
```

### 3. تجاوز Rate Limit
```bash
php app/maintenance-email-gdpr.php cleanup
```

### 4. SMTP Connection Failed
- تحقق من الإعدادات في email_settings.php
- استخدم email-test.php للتشخيص

---

## التطوير المستقبلي

### ميزات مقترحة

- [ ] Queue system للإرسال
- [ ] Email templates في Dashboard
- [ ] Bulk email actions
- [ ] Email forwarding rules
- [ ] Bounce/complaint handling
- [ ] Integration مع SendGrid/Mailgun
- [ ] CSV export للتقارير
- [ ] Scheduled reports
- [ ] Two-Factor Authentication
- [ ] IP Whitelist

---

## الخلاصة

نظام البريد الإلكتروني هو نظام **متكامل ومتطور** يغطي جميع جوانب:
- ✅ الإرسال
- ✅ الأمان
- ✅ المراقبة
- ✅ الامتثال
- ✅ الأداء

**الحالة:** جاهز للإنتاج بعد الإعداد الأولي

---

**نهاية التحليل**  
**الإصدار:** 1.0  
**التاريخ:** 2024-12-15
