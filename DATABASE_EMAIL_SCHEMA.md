# قاعدة بيانات نظام البريد الإلكتروني - التوثيق

## نظرة عامة
تم إنشاء جميع جداول قاعدة البيانات المطلوبة لنظام مراقبة البريد الإلكتروني بنجاح.

## الجداول المنشأة

### 1. جدول email_logs (الأساسي)
**الغرض:** تخزين سجلات جميع الرسائل المرسلة

```sql
CREATE TABLE `email_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `cycle_id` int(10) UNSIGNED DEFAULT NULL,
  `to_email` varchar(150) DEFAULT NULL,
  `recipient_email_hash` varchar(64) DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `email_type` varchar(50) DEFAULT NULL,
  `status` enum('success','failure') NOT NULL DEFAULT 'failure',
  `error_message` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_employee_cycle` (`employee_id`,`cycle_id`),
  KEY `idx_email_logs_type_status` (`email_type`,`status`),
  KEY `idx_recipient_hash` (`recipient_email_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**الحقول المهمة:**
- `employee_id`: معرف الموظف المرتبط بالرسالة
- `cycle_id`: معرف دورة التقييم المرتبطة
- `recipient_email_hash`: تجزئة البريد للمستقبل (للخصوصية)
- `is_encrypted`: مؤشر تشفير البيانات
- `email_type`: نوع البريد (test, reminder, report, etc.)
- `status`: حالة الإرسال (success/failure)
- `metadata`: بيانات إضافية JSON

### 2. جدول email_templates
**الغرض:** تخزين قوالب البريد الإلكتروني

```sql
CREATE TABLE `email_templates` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) UNIQUE NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. جدول email_rate_limits
**الغرض:** إدارة حدود إرسال البريد (Rate Limiting)

```sql
CREATE TABLE `email_rate_limits` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email_address` varchar(255) DEFAULT NULL,
  `daily_count` int(10) UNSIGNED DEFAULT 0,
  `hourly_count` int(10) UNSIGNED DEFAULT 0,
  `last_reset` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_address` (`email_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. جدول email_settings
**الغرض:** تخزين إعدادات نظام البريد

```sql
CREATE TABLE `email_settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) UNIQUE NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**الإعدادات الافتراضية:**
- `max_emails_per_hour`: 100
- `max_emails_per_day`: 1000
- `max_emails_per_recipient`: 5
- `email_encryption`: true
- `email_anonymization`: true
- `retry_failed_emails`: true
- `smtp_timeout`: 30
- `log_retention_days`: 90

### 5. جدول email_rate_limit_logs
**الغرض:** سجل مفصل لمحاولات إرسال البريد

```sql
CREATE TABLE `email_rate_limit_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(150) NOT NULL,
  `sender_id` varchar(50) DEFAULT 'system',
  `success` tinyint(1) DEFAULT 1,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recipient_time` (`recipient_email`, `attempted_at`),
  KEY `idx_sender_time` (`sender_id`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. جدول gdpr_policies
**الغرض:** سياسات الخصوصية GDPR

```sql
CREATE TABLE `gdpr_policies` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_key` varchar(100) NOT NULL UNIQUE,
  `policy_name` varchar(255) NOT NULL,
  `policy_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_policy_key` (`policy_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## الفهارس والإداء

### الفهارس المُنشأة:
- `idx_email_logs_employee_cycle`: لتحسين استعلامات البحث بالموظف ودورة التقييم
- `idx_email_logs_type_status`: لتصفية السجلات حسب النوع والحالة
- `idx_recipient_hash`: للبحث السريع عن المستقبلين
- `idx_email_address`: في جدول حدود الإرسال
- `idx_recipient_time` و `idx_sender_time`: في جدول سجل الحدود

### تحسين الأداء:
- استخدام `CHARACTER SET utf8mb4` لدعم النصوص العربية الكاملة
- أنواع البيانات المناسبة لكل حقل
- فهارس محسنة للاستعلامات الشائعة

## الأمان والخصوصية

### التشفير:
- تجزئة عناوين البريد للمستقبلين (`recipient_email_hash`)
- عمود `is_encrypted` لتتبع البيانات المشفرة
- دعم تشفير البيانات الحساسة

### GDPR:
- سياسات واضحة للاحتفاظ بالبيانات
- إمكانية إخفاء هوية السجلات
- إعدادات قابلة للتخصيص

## استخدام البيانات

### للإحصائيات:
```sql
-- إحصائيات اليوم
SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
  SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failed
FROM email_logs 
WHERE DATE(created_at) = CURDATE();
```

### لعرض سجلات البريد:
```sql
-- السجلات الأخيرة مع التصفية
SELECT id, subject, email_type, status, created_at
FROM email_logs 
WHERE email_type = ? AND status = ?
ORDER BY created_at DESC 
LIMIT 10;
```

### لإدارة الحدود:
```sql
-- فحص حدود الإرسال
SELECT hourly_count, daily_count 
FROM email_rate_limits 
WHERE email_address = ?
```

## البيانات النموذجية

تم إدراج 3 سجلات نموذجية في `email_logs` للاختبار:
1. رسالة اختبار ناجحة
2. رسالة تذكير فاشلة
3. تقرير أداء ناجح

## التحديثات المستقبلية

- إضافة مؤشرات أداء إضافية
- تحسين خوارزميات التجزئة
- إضافة دعم لقواعد البيانات الأخرى
- تحسين واجهة إدارة السياسات

---

**تاريخ الإنشاء:** 15 ديسمبر 2025  
**الإصدار:** 1.0  
**الحالة:** ✅ مكتمل وجاهز للاستخدام