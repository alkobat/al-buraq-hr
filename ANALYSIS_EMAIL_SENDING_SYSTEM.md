# تقرير تحليل نظام إرسال البريد الإلكتروني - نظام تقييم الأداء

**تاريخ التحليل:** 13 ديسمبر 2025  
**الحالة:** تحليل شامل لنظام الإرسال الحالي وعلاقته بخيارات الاحتساب

---

## 1. ملفات التقييم المدروسة

### أ) `/public/manager/evaluate.php` (613 سطر)
- **الوصف:** صفحة تقييم الموظفين من قبل مدير الإدارة
- **الدور:** تقييم من قبل `evaluator_role = 'manager'`
- **الحالة:** يحتوي على كود إرسال البريد ✅

### ب) `/public/supervisor/evaluate.php` (319 سطر)
- **الوصف:** صفحة تقييم الموظفين من قبل الرئيس المباشر
- **الدور:** تقييم من قبل `evaluator_role = 'supervisor'`
- **الحالة:** **لا يحتوي على كود إرسال البريد** ❌

### ج) `/public/view-evaluation.php` (494 سطر)
- **الوصف:** عرض التقييم للموظف والموافقة/الرفض
- **الملاحظة:** يستخدم EvaluationCalculator للحساب النهائي

### د) `/public/approve.php`
- **الملاحظة:** ملف الاعتماد النهائي للتقييم

---

## 2. موقع الإعداد: "إرسال تلقائي للموظف عند اكتمال التقييم"

### أ) جدول قاعدة البيانات: `system_settings`
```sql
CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL
);

INSERT INTO `system_settings` VALUES
(13, 'auto_send_user', ''),      
(14, 'auto_send_eval', ''),      
(15, 'evaluation_method', 'average_complete'),
```

### ب) اسم العمود بالضبط
- **العمود:** `value` في جدول `system_settings`
- **المفتاح (key):** `auto_send_eval`
- **القيم المتوقعة:** 
  - `'1'` = تفعيل الإرسال التلقائي
  - `''` أو `'0'` = تعطيل الإرسال

---

## 3. واجهة المستخدم لتغيير الإعداد

### ملف: `/public/admin/email_settings.php` (110 أسطر)
```php
// السطور 71-78: واجهة التحكم
<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" 
           name="auto_send_eval" value="1" 
           <?= $settings['auto_send_eval']=='1'?'checked':''?>>
    <label class="form-check-label">
        إرسال تلقائي للموظف عند اكتمال التقييم من المدير
    </label>
</div>
```

---

## 4. آلية الإرسال الحالية

### أ) موقع الكود
**الملف:** `/public/manager/evaluate.php`  
**السطور:** 381-398

### ب) الشروط الحالية للإرسال
1. **المكان:** عند إرسال التقييم من مدير الإدارة فقط
2. **الشرط:** `if ($settings == '1')` - فقط إذا كان `auto_send_eval` = 1
3. **نوع الموظف:** يحصل على الرابط فقط الموظفون لديهم `manager_id`
4. **عدم الإرسال المكرر:** يفحص إذا كان الرابط موجوداً بالفعل

---

## 5. نظام البريد (SMTP)

### أ) الملف الرئيسي: `/app/core/Mailer.php` (98 سطر)
- **المكتبة:** PHPMailer
- **الاتصال:** SMTP مع مصادقة
- **الدالة الرئيسية:** `sendEmail($toEmail, $toName, $templateType, $placeholders)`

### ب) إعدادات SMTP
```
smtp_host       : mail.buraq.aero
smtp_port       : 465
smtp_user       : hr@buraq.aero
smtp_pass       : buraq@1234
smtp_secure     : ssl
smtp_from_email : hr@buraq.aero
smtp_from_name  : نظام تقييم الأداء
```

---

## 6. قالب البريد

### جدول: `email_templates`

```
النوع:          evaluation_link
الموضوع:        رابط تقييم الأداء السنوي
المتغيرات:      {name}, {link}
```

**النص:** "قام مديرك المباشر برفع تقييم الأداء الخاص بك..."

---

## 7. العلاقة بين الإرسال وخيارات الاحتساب الثلاثة

### الوضع الحالي ❌

| خيار الاحتساب | المتطلب | حالة الإرسال |
|---|---|---|
| **manager_only** | تقييم المدير فقط | ✅ يرسل |
| **available_score** | أي تقييم متاح | ❌ لا يأخذها في الاعتبار |
| **average_complete** | كلا التقييمين (مع فحص supervisor_id) | ❌ لا يعتبر الاكتمال |

### المشاكل المحددة

**1. لا فحص للاكتمال:**
- الإرسال عند إرسال المدير بدون التحقق من اكتمال التقييم
- لا يراعي `method = 'average_complete'` الذي قد يتطلب تقييم المشرف أيضاً

**2. عدم الإرسال من المشرف:**
- في `/public/supervisor/evaluate.php` لا يوجد كود إرسال

**3. عدم الاعتبار لـ supervisor_id:**
- الإرسال لا يفحص إذا كان الموظف لديه رئيس مباشر

---

## 8. نقاط الإرسال الممكنة

### الحالية:
1. **manager/evaluate.php:381-398** - عند إرسال تقييم المدير ✅

### الناقصة:
1. `supervisor/evaluate.php` - عند إرسال تقييم المشرف ❌
2. `approve.php` - عند اعتماد النهائي ❌
3. `view-evaluation.php` - عند موافقة/رفض الموظف ❌

---

## 9. دوال EvaluationCalculator المتعلقة

### الملف: `/app/core/EvaluationCalculator.php`

**1. `calculateFinalScore()`** - حساب التقييم النهائي حسب الطريقة
- ترجع: `['score', 'status', 'note']`
- Status: 'complete', 'partial', 'incomplete', 'error'

**2. `getEmployeeScores()`** - جلب كل التقييمات والنتيجة النهائية
- ترجع: `['final_score', 'status', 'note', 'method']`

**3. `getEvaluationMethod()`** - جلب الطريقة الحالية

---

## 10. ملخص الاكتشافات

### الميزات الموجودة ✅
1. نظام SMTP متكامل مع PHPMailer
2. إعدادات قابلة للتعديل في واجهة الإدارة
3. حماية CSRF عند الإرسال
4. قوالب بريد قابلة للتخصيص

### الفجوات والمشاكل ❌
1. **عدم المحاذاة مع الخيارات الثلاثة** - الإرسال لا يأخذ طريقة الاحتساب في الحسبان
2. **عدم الإرسال من المشرف** - لا كود في supervisor/evaluate.php
3. **عدم فحص الاكتمال** - لا يفحص الاكتمال الفعلي قبل الإرسال
4. **عدم تسجيل الأخطاء** - فشل الإرسال لا يُسجّل أو يُنبّه

---

## 11. توصيات التطوير

### الخطوة 1: إضافة فحص الطريقة في manager/evaluate.php
```php
if ($settings == '1') {
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    $should_send = false;
    if ($method === 'manager_only' || $method === 'available_score') {
        $should_send = true;
    } elseif ($method === 'average_complete' && !$employee['supervisor_id']) {
        // بدون رئيس مباشر = التقييم مكتمل
        $should_send = true;
    }
    
    if ($should_send) {
        $mailer->sendEmail(...);
    }
}
```

### الخطوة 2: إضافة إرسال في supervisor/evaluate.php
- نسخ الكود من manager مع التعديلات
- فحص الطريقة الحالية قبل الإرسال

### الخطوة 3: تحسين معالجة الأخطاء
- تسجيل فشل الإرسال في السجلات
- إشعار الإداري بأي مشاكل

---

**تم إنشاء التقرير بواسطة:** نظام التحليل الآلي  
**التاريخ:** 13 ديسمبر 2025
