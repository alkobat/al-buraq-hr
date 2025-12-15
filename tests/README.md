# اختبارات نظام البريد الإلكتروني - Email System Tests

## نظرة عامة

هذا المجلد يحتوي على اختبارات تلقائية شاملة لنظام البريد الإلكتروني في نظام تقييم الأداء.

## البنية

```
tests/
├── TestCase.php                 # الكلاس الأساسي للاختبارات
├── EmailServiceTest.php         # اختبارات EmailService
├── MailerTest.php              # اختبارات Mailer
├── fixtures/
│   └── test_data.php           # بيانات اختبار ثابتة
└── README.md                   # هذا الملف
```

## متطلبات التشغيل

1. تثبيت PHPUnit:
```bash
composer install
```

2. تشغيل جميع الاختبارات:
```bash
vendor/bin/phpunit
```

3. تشغيل اختبارات محددة:
```bash
vendor/bin/phpunit tests/EmailServiceTest.php
vendor/bin/phpunit tests/MailerTest.php
```

4. تشغيل اختبار واحد:
```bash
vendor/bin/phpunit --filter test_sends_email_on_complete_evaluation
```

## EmailServiceTest - اختبارات خدمة البريد

### 1. اختبارات الإرسال (Email Sending Tests)

- ✅ `test_sends_email_on_complete_evaluation` - إرسال بريد عند اكتمال التقييم
- ✅ `test_sends_email_with_approval_link` - إرسال بريد مع رابط الموافقة
- ✅ `test_sends_email_on_evaluation_changes` - إرسال بريد عند التغييرات

### 2. اختبارات الشروط (Condition Tests)

- ✅ `test_sends_email_when_auto_send_enabled` - الإرسال عند تفعيل auto_send_eval
- ✅ `test_does_not_send_email_when_auto_send_disabled` - عدم الإرسال عند التعطيل
- ✅ `test_master_toggle_prevents_all_emails` - Master Toggle يمنع جميع الإرسالات

### 3. اختبارات الطرق الثلاث (Three Methods Tests)

#### طريقة manager_only
- ✅ `test_manager_only_sends_on_manager_evaluation` - إرسال عند تقييم المدير
- ✅ `test_manager_only_does_not_send_on_supervisor_evaluation` - عدم الإرسال عند تقييم المشرف

#### طريقة available_score
- ✅ `test_available_score_sends_on_any_evaluation` - إرسال عند أي تقييم (mode: any)
- ✅ `test_available_score_manager_only_mode` - إرسال للمدير فقط (mode: manager_only)
- ✅ `test_available_score_both_mode_requires_completion` - يتطلب الاكتمال (mode: both)

#### طريقة average_complete
- ✅ `test_average_complete_sends_when_evaluations_complete` - إرسال عند الاكتمال
- ✅ `test_average_complete_no_waiting_email_without_supervisor` - عدم إرسال "بانتظار" للموظف بدون مشرف
- ✅ `test_average_complete_both_only_mode` - إرسال فقط عند الاكتمال (mode: both_only)

### 4. اختبارات منع التكرار (Duplicate Prevention Tests)

- ✅ `test_prevents_duplicate_emails` - منع إرسال نفس البريد مرتين
- ✅ `test_duplicate_detection_by_email_type` - كشف التكرار حسب نوع البريد

### 5. اختبارات الأخطاء (Error Tests)

- ✅ `test_handles_missing_smtp_settings` - معالجة غياب إعدادات SMTP
- ✅ `test_handles_invalid_email` - معالجة البريد الإلكتروني غير الصحيح
- ✅ `test_handles_exceptions_gracefully` - معالجة الاستثناءات بشكل سلس

### اختبارات إضافية

- ✅ `test_creates_evaluation_link_token` - إنشاء Token للرابط
- ✅ `test_email_content_is_in_arabic` - محتوى البريد بالعربية
- ✅ `test_logs_metadata_correctly` - تسجيل Metadata بشكل صحيح

**إجمالي الاختبارات: 21 اختبار**

## MailerTest - اختبارات مُرسل البريد

### 1. اختبارات إعدادات SMTP

- ✅ `test_loads_smtp_settings_from_database` - تحميل الإعدادات من قاعدة البيانات
- ✅ `test_send_custom_email_basic_structure` - إرسال بريد مخصص
- ✅ `test_send_email_from_template` - إرسال من template
- ✅ `test_handles_missing_template` - معالجة template غير موجود

### 2. اختبارات المرفقات (Attachments)

- ✅ `test_attach_file_to_email` - إضافة مرفق من ملف
- ✅ `test_attach_string_to_email` - إضافة مرفق نصي
- ✅ `test_attach_multiple_files` - إضافة مرفقات متعددة

### 3. اختبارات معالجة الأخطاء

- ✅ `test_handles_invalid_smtp_settings` - معالجة إعدادات SMTP خاطئة
- ✅ `test_handles_invalid_email_address` - معالجة عنوان بريد خاطئ
- ✅ `test_handles_empty_content` - معالجة محتوى فارغ
- ✅ `test_does_not_throw_exception_on_send_failure` - عدم رفع استثناءات عند الفشل

### 4. اختبارات الترميز والتنسيق

- ✅ `test_supports_utf8_and_arabic_text` - دعم UTF-8 والنصوص العربية
- ✅ `test_supports_html_content` - دعم محتوى HTML
- ✅ `test_replaces_placeholders_in_template` - استبدال Placeholders

### 5. اختبارات إعدادات FROM

- ✅ `test_uses_from_email_and_name_from_settings` - استخدام FROM من الإعدادات
- ✅ `test_fallback_to_smtp_user_when_from_email_missing` - Fallback لـ smtp_user

### 6. اختبارات SMTP Security

- ✅ `test_supports_tls_encryption` - دعم TLS
- ✅ `test_supports_ssl_encryption` - دعم SSL

### 7. اختبارات تكامل PHPMailer

- ✅ `test_phpmailer_is_loaded` - التأكد من تحميل PHPMailer
- ✅ `test_error_message_when_phpmailer_missing` - رسالة خطأ عند الغياب

**إجمالي الاختبارات: 19 اختبار**

## إجمالي التغطية

**إجمالي عدد الاختبارات: 40 اختبار**

### التغطية حسب الفئة:

- ✅ اختبارات الإرسال: 3
- ✅ اختبارات الشروط: 3
- ✅ اختبارات الطرق الثلاث: 8
- ✅ اختبارات منع التكرار: 2
- ✅ اختبارات الأخطاء: 6
- ✅ اختبارات المرفقات: 3
- ✅ اختبارات الترميز: 3
- ✅ اختبارات الأمان: 2
- ✅ اختبارات إضافية: 10

## قاعدة البيانات للاختبارات

تستخدم الاختبارات قاعدة بيانات SQLite في الذاكرة (in-memory) مع البيانات التالية:

### المستخدمون:
1. أحمد محمد (Manager) - ahmed@example.com
2. فاطمة علي (Supervisor) - fatima@example.com
3. محمد خالد (Employee مع مشرف) - mohammed@example.com
4. سارة أحمد (Employee بدون مشرف) - sarah@example.com
5. عمر حسن (Employee بدون email) - null

### الإعدادات:
- auto_send_eval: 1
- evaluation_method: average_complete
- SMTP settings: إعدادات اختبار

## سيناريوهات الاختبار

### السيناريو 1: إرسال بريد عند اكتمال التقييم
```php
// موظف لديه مشرف
// المدير يقيّم -> إرسال بريد "بانتظار المشرف"
// المشرف يقيّم -> إرسال بريد "التقييم مكتمل"
```

### السيناريو 2: موظف بدون مشرف
```php
// موظف بدون مشرف
// المدير يقيّم -> إرسال بريد "التقييم مكتمل" مباشرة
```

### السيناريو 3: Master Toggle معطّل
```php
// auto_send_eval = 0
// أي تقييم -> لا يُرسل أي بريد
```

### السيناريو 4: طريقة manager_only
```php
// evaluation_method = manager_only
// المدير يقيّم -> إرسال بريد
// المشرف يقيّم -> لا يُرسل بريد
```

## ملاحظات مهمة

1. **Mock SMTP**: الاختبارات تستخدم إعدادات SMTP وهمية، لذا لن يتم إرسال أي بريد فعلي
2. **In-Memory Database**: قاعدة البيانات تُحذف بعد كل اختبار
3. **Isolation**: كل اختبار مستقل تماماً عن الآخر
4. **Error Handling**: جميع الاختبارات تتحقق من معالجة الأخطاء بشكل صحيح

## إضافة اختبارات جديدة

لإضافة اختبار جديد:

1. افتح الملف المناسب (EmailServiceTest.php أو MailerTest.php)
2. أضف دالة جديدة مع `@test` annotation:

```php
/**
 * @test
 * وصف الاختبار بالعربية
 */
public function test_your_test_name(): void
{
    // Arrange - تجهيز البيانات
    $employeeId = 3;
    
    // Act - تنفيذ العملية
    $result = $this->emailService->someMethod($employeeId);
    
    // Assert - التحقق من النتيجة
    $this->assertEquals('expected', $result);
}
```

3. شغّل الاختبار للتأكد من نجاحه:
```bash
vendor/bin/phpunit --filter test_your_test_name
```

## استكشاف الأخطاء

### خطأ: Class not found
```bash
composer dump-autoload
```

### خطأ: Database error
تحقق من أن `TestCase::createTestDatabase()` يتم استدعاؤه في `setUp()`

### خطأ: PHPUnit not found
```bash
composer install
```

## الدعم

للأسئلة أو المشاكل، راجع الوثائق الرئيسية للمشروع أو افتح issue جديد.

---

**آخر تحديث:** 2024-12-15
**الإصدار:** 1.0.0
