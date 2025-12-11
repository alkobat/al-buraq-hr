# توصيات أمنية إضافية لنظام تقييم الأداء

## 📋 ملخص الإصلاحات المنفذة

تم إصلاح الثغرات الأمنية التالية:

### ✅ الثغرات الحرجة المُصلحة

1. **SQL Injection في approve.php** - تم الإصلاح ✅
   - تحويل جميع استعلامات `query()` إلى `prepare()` مع parameters
   
2. **SQL Injection في view-evaluation.php** - تم الإصلاح ✅
   - تحويل استعلامات evaluators إلى prepared statements

3. **CSRF Protection في approve.php** - تم الإصلاح ✅
   - إضافة session-based CSRF tokens
   - التحقق من tokens في جميع POST requests
   - إعادة توليد tokens بعد كل عملية

4. **CSRF Protection في view-evaluation.php** - تم الإصلاح ✅
   - نفس آلية الحماية المطبقة في approve.php

5. **Authorization Bypass في users.php** - تم الإصلاح ✅
   - تحويل الحذف من GET إلى POST
   - إضافة CSRF protection للحذف
   - إضافة confirmation dialog

6. **CSRF Protection في change_password.php** - تم الإصلاح ✅
   - إضافة CSRF token للنموذج
   - تحسين سياسة كلمات المرور (8 أحرف + تعقيد)

7. **Undefined Variable في users.php** - تم الإصلاح ✅
   - إصلاح المتغير $id غير المعرّف في سطر 219

---

## 🔧 التوصيات المتبقية (للتنفيذ)

### 1. إضافة Rate Limiting لتسجيل الدخول

**الأولوية:** عالية  
**الملف:** `public/login.php`

**الخطوات:**
```sql
-- إنشاء جدول لتتبع محاولات تسجيل الدخول
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255),
    success TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, created_at)
);
```

```php
// في login.php قبل التحقق من المستخدم
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM login_attempts 
    WHERE ip_address = ? 
    AND success = 0 
    AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$stmt->execute([$_SERVER['REMOTE_ADDR']]);
$failed_attempts = $stmt->fetchColumn();

if ($failed_attempts >= 5) {
    $error = "تم حظر تسجيل الدخول مؤقتاً (15 دقيقة) بسبب محاولات فاشلة متعددة.";
    // عرض الخطأ والخروج
    // يمكن إضافة CAPTCHA هنا
}

// بعد محاولة تسجيل الدخول (سواء نجحت أو فشلت)
$stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)");
$stmt->execute([$_SERVER['REMOTE_ADDR'], $email, $user ? 1 : 0]);
```

---

### 2. تحسين أمان إرسال كلمات المرور

**الأولوية:** عالية  
**الملف:** `public/admin/users.php` (سطر 316-329)

**بدلاً من إرسال كلمة المرور:**
```php
// توليد رابط إعادة تعيين كلمة المرور
$reset_token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

// حفظ في قاعدة البيانات
$pdo->prepare("
    INSERT INTO password_reset_tokens (user_id, token, expires_at) 
    VALUES (?, ?, ?)
")->execute([$new_user_id, $reset_token, $expires]);

// إرسال رابط في البريد
$reset_link = "https://yourdomain.com/reset-password.php?token=$reset_token";
$mailer->sendEmail($email, $name, 'new_user_with_reset', [
    'name' => $name,
    'reset_link' => $reset_link
]);
```

---

### 3. إضافة Session Timeout

**الأولوية:** متوسطة  
**الملفات:** جميع الصفحات التي تستخدم session

```php
// في بداية كل صفحة بعد session_start()
$timeout_duration = 1800; // 30 دقيقة

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();
```

---

### 4. إضافة Audit Log للعمليات الحساسة

**الأولوية:** متوسطة  
**تم جزئياً في Logger.php**

**تحسينات مقترحة:**
- تسجيل محاولات الوصول غير المصرح بها
- تسجيل تغييرات كلمات المرور
- تسجيل عمليات الحذف مع تفاصيل المستخدم المحذوف
- تسجيل IP addresses لجميع العمليات

```php
// مثال: تسجيل تغيير كلمة المرور
$logger->log('password_change', "تم تغيير كلمة المرور من IP: " . $_SERVER['REMOTE_ADDR']);
```

---

### 5. تفعيل Content Security Policy (CSP)

**الأولوية:** متوسطة  
**الملف:** `.htaccess` أو headers في كل صفحة

```php
// في بداية كل صفحة HTML
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:;");
```

---

### 6. إضافة File Upload Validation

**الأولوية:** عالية (إذا كان هناك رفع ملفات)  
**الملفات:** أي صفحة تحتوي على file upload

```php
// مثال: التحقق من رفع Excel في users.php
if (isset($_FILES['excel_file'])) {
    $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['excel_file']['type'], $allowed_types)) {
        die("نوع الملف غير مسموح. يُسمح فقط بملفات Excel.");
    }
    
    if ($_FILES['excel_file']['size'] > $max_size) {
        die("حجم الملف كبير جداً. الحد الأقصى 5MB.");
    }
    
    // التحقق من امتداد الملف
    $filename = $_FILES['excel_file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'])) {
        die("امتداد الملف غير صالح.");
    }
}
```

---

### 7. تشفير البيانات الحساسة في قاعدة البيانات

**الأولوية:** متوسطة  
**البيانات المقترح تشفيرها:**
- أرقام الهوية
- معلومات التواصل الحساسة
- ملاحظات التقييم الخاصة

```php
// استخدام OpenSSL للتشفير
function encrypt_data($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decrypt_data($data, $key) {
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
}
```

---

### 8. إضافة Two-Factor Authentication (2FA)

**الأولوية:** منخفضة (لكن موصى بها للمسؤولين)

**خطوات التنفيذ:**
1. استخدام مكتبة مثل `google/google-authenticator-php`
2. إضافة حقل `two_factor_secret` في جدول users
3. إضافة خيار تفعيل 2FA في إعدادات المستخدم
4. التحقق من OTP عند تسجيل الدخول

---

### 9. مراقبة وتنبيهات الأمان

**الأولوية:** متوسطة

**تنبيهات مقترحة:**
- إرسال بريد عند محاولات تسجيل دخول فاشلة متعددة
- تنبيه عند تغيير كلمة مرور المسؤول
- تنبيه عند إضافة/حذف مستخدمين
- تنبيه عند الوصول من IP غير معتاد

```php
// مثال: إرسال تنبيه للمسؤولين
function send_security_alert($title, $message) {
    global $pdo, $mailer;
    
    $admins = $pdo->query("SELECT email, name FROM users WHERE role = 'admin'")->fetchAll();
    
    foreach ($admins as $admin) {
        $mailer->sendCustomEmail(
            $admin['email'], 
            $admin['name'], 
            "تنبيه أمني: $title", 
            $message
        );
    }
}
```

---

### 10. النسخ الاحتياطي التلقائي

**الأولوية:** عالية  
**الملف:** `public/admin/backups.php` (موجود)

**توصيات:**
- جدولة نسخ احتياطية يومية تلقائية (cron job)
- تشفير النسخ الاحتياطية
- تخزين النسخ في مكان آمن خارج الخادم
- اختبار استعادة النسخ دورياً

```bash
# مثال: cron job للنسخ الاحتياطي اليومي
0 2 * * * /usr/bin/php /path/to/project/scripts/backup.php
```

---

## 🔐 إعدادات الخادم الموصى بها

### 1. إعدادات PHP (php.ini)

```ini
; تعطيل عرض الأخطاء في production
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; إخفاء معلومات PHP
expose_php = Off

; حدود الذاكرة والتنفيذ
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 20M
upload_max_filesize = 10M

; Session Security
session.cookie_httponly = 1
session.cookie_secure = 1  ; فقط مع HTTPS
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
session.use_only_cookies = 1
session.gc_maxlifetime = 1800  ; 30 دقيقة

; تعطيل الوظائف الخطرة
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

### 2. إعدادات Apache (.htaccess)

```apache
# منع الوصول للملفات الحساسة
<FilesMatch "\.(env|sql|log|sh)$">
    Require all denied
</FilesMatch>

# حماية من SQL Injection في URLs
<IfModule mod_rewrite.c>
    RewriteCond %{QUERY_STRING} (.*)(union|select|insert|cast|set|declare|drop|update|md5|benchmark).* [NC]
    RewriteRule ^(.*)$ - [F,L]
</IfModule>

# تفعيل HTTPS (إذا كان متاحاً)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# Headers أمنية
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>
```

### 3. إعدادات MySQL

```sql
-- إنشاء مستخدم قاعدة بيانات بصلاحيات محدودة
CREATE USER 'hr_app_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON al_b.* TO 'hr_app_user'@'localhost';
FLUSH PRIVILEGES;

-- تفعيل SSL للاتصالات (موصى به)
-- في my.cnf
[mysqld]
require_secure_transport=ON
```

---

## 🧪 اختبارات الأمان الموصى بها

### 1. Penetration Testing
- استخدام أدوات مثل OWASP ZAP أو Burp Suite
- اختبار SQL Injection يدوياً
- اختبار XSS في جميع النماذج
- اختبار CSRF bypasses

### 2. Code Review
- مراجعة الكود دورياً
- استخدام static analysis tools (مثل PHPStan)
- فحص dependencies للثغرات المعروفة

### 3. Security Monitoring
- مراقبة سجلات الأخطاء
- مراقبة محاولات تسجيل الدخول الفاشلة
- تتبع أنماط الوصول غير العادية

---

## 📚 مصادر إضافية

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [MySQL Security Best Practices](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)

---

## ✅ Checklist قبل النشر في Production

- [ ] تم تطبيق جميع الإصلاحات الحرجة
- [ ] تم تفعيل HTTPS
- [ ] تم تعطيل `display_errors` في php.ini
- [ ] تم تغيير جميع كلمات المرور الافتراضية
- [ ] تم إعداد النسخ الاحتياطية التلقائية
- [ ] تم اختبار استعادة النسخ الاحتياطية
- [ ] تم تفعيل Rate Limiting لتسجيل الدخول
- [ ] تم مراجعة صلاحيات قاعدة البيانات
- [ ] تم تفعيل Security Headers في Apache
- [ ] تم اختبار جميع النماذج ضد CSRF
- [ ] تم اختبار جميع الاستعلامات ضد SQL Injection
- [ ] تم إعداد monitoring للأخطاء والتنبيهات
- [ ] تم توثيق جميع التغييرات

---

**تم إعداده بواسطة:** فريق الأمن السيبراني  
**التاريخ:** 11 ديسمبر 2024  
**الإصدار:** 1.0
