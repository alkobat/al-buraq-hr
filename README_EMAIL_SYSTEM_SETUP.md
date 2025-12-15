# دليل إعدادات نظام البريد الإلكتروني

## نظرة عامة
هذا الدليل يوضح كيفية إعداد وتشغيل نظام مراقبة البريد الإلكتروني في نظام إدارة الأداء العربي.

## الإعداد الأولي

### 1. إعدادات قاعدة البيانات
الجداول منشأة تلقائياً عبر ملفات migration:
- `add_email_logs_table.sql` - الجدول الأساسي
- `add_email_security_tables.sql` - جداول الأمان  
- `add_additional_email_tables.sql` - الجداول الإضافية

### 2. التحقق من الإعداد
```bash
# التحقق من الجداول المنشأة
sudo mysql al_b -e "SHOW TABLES LIKE 'email%';"

# التحقق من البيانات الأولية
sudo mysql al_b -e "SELECT COUNT(*) FROM email_logs;"
sudo mysql al_b -e "SELECT * FROM email_settings;"
```

### 3. اختبار الاتصال
```bash
# اختبار تشغيل لوحة المراقبة
cd /home/engine/project
php -S localhost:8000
# ثم زيارة: http://localhost:8000/public/admin/email-dashboard.php
```

## ملفات النظام المهمة

### 1. Core Files
- `app/core/EmailStatistics.php` - محرك الإحصائيات
- `app/core/EmailService.php` - خدمة البريد
- `app/core/Mailer.php` - إرسال البريد
- `app/core/RateLimiter.php` - إدارة الحدود

### 2. Admin Pages
- `public/admin/email-dashboard.php` - لوحة المراقبة الرئيسية
- `public/admin/email-logs.php` - سجلات البريد
- `public/admin/email-test.php` - اختبار SMTP
- `public/admin/email_settings.php` - إعدادات البريد

### 3. Assets
- `public/assets/css/email-dashboard.css` - تنسيق لوحة المراقبة
- `public/assets/js/email-dashboard.js` - وظائف JavaScript

## الإعدادات القابلة للتخصيص

### 1. حدود الإرسال
```sql
-- تحديث الحدود
UPDATE email_settings 
SET setting_value = '200' 
WHERE setting_key = 'max_emails_per_hour';

UPDATE email_settings 
SET setting_value = 'false' 
WHERE setting_key = 'email_encryption';
```

### 2. سياسات GDPR
```sql
-- تحديث سياسات الخصوصية
UPDATE gdpr_policies 
SET policy_value = '60' 
WHERE policy_key = 'email_logs_retention_days';
```

### 3. SMTP Settings
يتم قراءتها من جدول `system_settings`:
```sql
-- عرض الإعدادات الحالية
SELECT * FROM system_settings WHERE setting_key LIKE '%smtp%' OR setting_key LIKE '%mail%';
```

## الوظائف الرئيسية

### 1. لوحة المراقبة (Dashboard)
- **الإحصائيات اليومية:** عدد الرسائل المرسلة/الفاشلة
- **الرسوم البيانية:** إحصائيات الـ 30 يوم الماضية
- **الإنذارات:** تنبيهات للمشاكل
- **النشاط الأخير:** آخر الرسائل المرسلة

### 2. سجلات البريد (Logs)
- **التصفية المتقدمة:** حسب التاريخ، النوع، الحالة
- **عرض التفاصيل:** كامل محتوى الرسالة
- **إعادة الإرسال:** للرسائل الفاشلة
- **التصدير:** CSV, JSON

### 3. اختبار SMTP
- **اختبار الاتصال:** التحقق من إعدادات SMTP
- **إرسال اختبار:** إرسال رسالة تجريبية
- **إعدادات مسبقة:** Gmail, Office 365, Yahoo

### 4. إعدادات البريد
- **إعدادات SMTP:** الخادم، المنفذ، التشفير
- **حدود الإرسال:** بالساعة/اليومية
- **سياسات الأمان:** التشفير، إخفاء الهوية
- **إعدادات التنظيف:** مدة الاحتفاظ بالسجلات

## حل المشاكل الشائعة

### 1. خطأ "Table 'al_b.email_logs' doesn't exist"
```bash
# إعادة تطبيق الميجريشنز
sudo mysql al_b < migrations/add_email_logs_table.sql
sudo mysql al_b < migrations/add_email_security_tables.sql
sudo mysql al_b < migrations/add_additional_email_tables.sql
```

### 2. خطأ اتصال قاعدة البيانات
```bash
# التحقق من حالة MySQL
ps aux | grep mysql
sudo mysql -e "SHOW DATABASES;"

# التحقق من إعدادات الاتصال
# مراجعة app/core/db.php
```

### 3. لا تظهر الإحصائيات
```sql
-- التحقق من البيانات
SELECT COUNT(*) FROM email_logs;
SELECT email_type, status, COUNT(*) FROM email_logs GROUP BY email_type, status;

-- التحقق من الفهارس
SHOW INDEX FROM email_logs;
```

### 4. مشاكل SMTP
```php
// اختبار SMTP برمجياً
<?php
$testResult = testSMTPConnection();
echo $testResult ? "✅ نجح" : "❌ فشل";
?>
```

## الصيانة والنسخ الاحتياطي

### 1. تنظيف السجلات القديمة
```sql
-- حذف السجلات الأقدم من 90 يوم
DELETE FROM email_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### 2. النسخ الاحتياطي
```bash
# نسخ احتياطي للجداول
sudo mysqldump al_b email_logs email_templates email_settings > backup_email_system.sql
```

### 3. مراقبة الأداء
```sql
-- حجم الجداول
SELECT 
    table_name,
    round(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = 'al_b' 
AND table_name LIKE 'email%';
```

## التطوير والتخصيص

### 1. إضافة نوع بريد جديد
```php
// في EmailStatistics.php
public function getStatsByEmailType()
{
    $stmt = $this->pdo->prepare("
        SELECT email_type, 
               COUNT(*) as count,
               SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
               SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failed
        FROM email_logs 
        WHERE email_type = ?
        GROUP BY email_type
    ");
    $stmt->execute([$type]);
    return $stmt->fetch();
}
```

### 2. إضافة فلاتر جديدة
```javascript
// في email-dashboard.js
function addNewFilter(criteria) {
    // منطق الفلترة الجديدة
}
```

### 3. تخصيص الألوان والخط
```css
/* في email-dashboard.css */
:root {
    --primary-color: #007bff;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
}
```

## الأمان

### 1. حماية ملفات الإدارة
- تأكد من أن صفحات الإدارة تتطلب تسجيل دخول
- استخدم HTTPS في الإنتاج
- راجع صلاحيات قاعدة البيانات

### 2. تشفير البيانات الحساسة
- البريد الإلكتروني مخزن كـ hash
- يمكن تفعيل تشفير كامل
- سياسات GDPR مطبقة

### 3. نسخ البيانات الحساسة
```sql
-- عرض البيانات الحساسة (للتطوير فقط)
SELECT 
    id, 
    SHA2(to_email, 256) as email_hash,
    subject, 
    status
FROM email_logs 
LIMIT 10;
```

## الدعم والمساعدة

### ملفات التوثيق المرجعية:
- `DATABASE_EMAIL_SCHEMA.md` - تفاصيل قاعدة البيانات
- `README_EMAIL_SETTINGS_FIX.md` - إعدادات البريد
- `EMAIL_SECURITY_IMPLEMENTATION.md` - أمان النظام

### معلومات النسخة:
- **الإصدار:** 1.0
- **تاريخ الإنشاء:** 15 ديسمبر 2025
- **الوضع:** ✅ جاهز للإنتاج
- **اختبار الوظائف:** ✅ تم بنجاح

---

**معلومات إضافية:**
- النظام مطور لدعم اللغة العربية بالكامل
- جميع الواجهات تدعم RTL
- التصميم متجاوب (موبايل/تابلت/ديسكتوب)
- متوافق مع PHP 8.2+ و MariaDB 10.4+