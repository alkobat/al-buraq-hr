# تقرير تقني - نظام إرسال البريد الإلكتروني

## الملفات والأسطر المحددة بدقة

### 1. ملف: `/public/manager/evaluate.php`

#### الموقع الدقيق للإرسال
```
السطر 365-401: كود إرسال البريد عند إرسال التقييم من المدير
```

#### الكود الحالي (مختصر):
```php
// السطور 365-402
if (isset($_POST['submit'])) {
    // السطر 366
    $pdo->prepare("UPDATE employee_evaluations SET status = 'submitted' WHERE id = ?")->execute([$evaluation_id]);
    
    // السطور 369-379: إنشاء رابط
    $token_stmt = $pdo->prepare("SELECT unique_token FROM employee_evaluation_links WHERE employee_id = ? AND cycle_id = ?");
    $token_stmt->execute([$employee_id, $active_cycle['id']]);
    $existing_token = $token_stmt->fetchColumn();
    
    if (!$existing_token) {
        $new_token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+90 days'));
        $pdo->prepare("
            INSERT INTO employee_evaluation_links (employee_id, cycle_id, unique_token, expires_at) 
            VALUES (?, ?, ?, ?)
        ")->execute([$employee_id, $active_cycle['id'], $new_token, $expires_at]);
        
        // السطور 380-398: **جزء الإرسال**
        $settings = $pdo->query("SELECT value FROM system_settings WHERE `key`='auto_send_eval'")->fetchColumn();
        if ($settings == '1') {
            require_once '../../app/core/Mailer.php';
            $mailer = new Mailer($pdo);
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $path = dirname(dirname($_SERVER['PHP_SELF']));
            $link = "$protocol://$host$path/view-evaluation.php?token=$new_token";
            
            $mailer->sendEmail($employee['email'], $employee['name'], 'evaluation_link', [
                'name' => $employee['name'],
                'link' => $link
            ]);
        }
    }
    
    // السطور 399-400: تسجيل النشاط
    $logger->log('evaluation', "قام بإرسال التقييم النهائي للموظف: {$employee['name']}");
}
```

#### المتغيرات المتاحة في هذه النقطة
```php
$employee          // بيانات الموظف (array)
  ├─ id
  ├─ email           // ← البريد الإلكتروني للموظف
  ├─ name            // ← اسم الموظف
  ├─ supervisor_id   // ← معرف الرئيس المباشر (NULL إذا لا يوجد)
  └─ manager_id      // ← معرف المدير

$employee_id       // معرف الموظف
$active_cycle      // دورة التقييم الحالية
  ├─ id
  └─ year

$manager_id        // معرف مدير الإدارة (المُقيّم)

$evaluation_id     // معرف التقييم الذي تم إرساله للتو
$total_score       // درجة التقييم (0-100)

$pdo              // اتصال قاعدة البيانات
$logger           // كائن تسجيل الأنشطة
```

---

### 2. ملف: `/public/supervisor/evaluate.php`

#### الموقع: **لا يوجد كود إرسال بريد** ❌

السطور المحددة للمقارنة:
- السطر 193-248: معالجة POST (حفظ وإرسال التقييم)
  - السطر 230-241: معالجة حالة `submit`
  - **المشكلة:** لا توجد أي مكالمة لـ `Mailer::sendEmail()`

المتغيرات المتاحة (مماثلة للمدير):
```php
$employee          // بيانات الموظف
$supervisor_id     // معرف المشرف (المُقيّم)
$evaluation_id     // معرف التقييم
$total_score       // درجة التقييم
$active_cycle      // دورة التقييم
```

---

### 3. ملف: `/app/core/Mailer.php`

#### الدالة الرئيسية:
```php
// السطور 35-73
public function sendEmail($toEmail, $toName, $templateType, $placeholders = [], $attachments = [])
```

#### المعاملات:
```php
$toEmail        // string: البريد الإلكتروني للمستقبل
$toName         // string: اسم المستقبل
$templateType   // string: نوع القالب ('evaluation_link', 'new_user', etc)
$placeholders   // array: المتغيرات للقالب ['key' => 'value']
$attachments    // array: المرفقات (اختياري)
```

---

### 4. ملف: `/public/admin/email_settings.php`

#### واجهة المستخدم:
```html
<!-- السطور 75-78 -->
<input class="form-check-input" type="checkbox" 
       name="auto_send_eval" value="1" 
       <?= $settings['auto_send_eval']=='1'?'checked':''?>>
<label>إرسال تلقائي للموظف عند اكتمال التقييم من المدير</label>
```

---

## جداول قاعدة البيانات

### جدول: `system_settings` - الصفوف ذات الصلة
```
id   key                    value
---  ---                    -----
6    smtp_host              mail.buraq.aero
7    smtp_port              465
8    smtp_user              hr@buraq.aero
9    smtp_pass              buraq@1234
10   smtp_secure            ssl
11   smtp_from_email        hr@buraq.aero
12   smtp_from_name         نظام تقييم الأداء
13   auto_send_user         (empty or '1')
14   auto_send_eval         (empty or '1')  ← **المفتاح الأساسي**
15   evaluation_method      average_complete
```

### جدول: `email_templates` - قالب التقييم
```
id    type               subject
---   ----               -------
2     evaluation_link    رابط تقييم الأداء السنوي

body: '<p>مرحباً {name}،</p>
       <p>قام مديرك المباشر برفع تقييم الأداء الخاص بك.</p>
       <p>يرجى الاطلاع عليه والموافقة أو الرفض عبر الرابط التالي:</p>
       <p><a href=\"{link}\">{link}</a></p>'
       
placeholders: '{name}, {link}'
```

---

## الخلاصة الشاملة

**التقرير كامل وموجود في ملف ANALYSIS_EMAIL_SENDING_SYSTEM.md**
