# مقترحات تطوير نظام إرسال البريد - محاذاة مع الخيارات الثلاثة

**التاريخ:** 13 ديسمبر 2025  
**الهدف:** توضيح كيفية تعديل نظام الإرسال ليتوافق مع الخيارات الثلاثة لحساب التقييم

---

## المشكلة الأساسية

نظام الإرسال الحالي **غير متوافق** مع خيارات الاحتساب الثلاثة لأنه:
1. يرسل عند إرسال المدير **دائماً** بدون فحص الطريقة
2. لا يفحص إذا كان التقييم **مكتملاً فعلاً** حسب الطريقة المحددة
3. لا يُرسل من المشرف إطلاقاً

---

## الحل المقترح

### المبدأ الأساسي الجديد:

**الإرسال يجب أن يتم فقط عندما يكون التقييم "مكتملاً" حسب الطريقة المحددة**

---

## سيناريوهات التفعيل حسب كل طريقة

### 1️⃣ **Scenario: method = 'manager_only'**

#### المتطلب:
- تقييم المدير فقط (تقييم المشرف للعرض فقط)
- بدون فحص الرئيس المباشر

#### شروط الإرسال:
```
متى يرسل؟
├─ عند إرسال تقييم المدير (submit) ✅
│  └─ السبب: التقييم مكتمل بإرسال المدير
│
└─ عند إرسال تقييم المشرف (submit) ❌
   └─ السبب: ليس ضروري (المدير هو صاحب القرار)
```

#### الكود المقترح (manager/evaluate.php):
```php
if (isset($_POST['submit'])) {
    // ... الكود الموجود ...
    
    // فحص الطريقة
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    if ($method === 'manager_only') {
        // أرسل عند إرسال المدير (الحالة الحالية ✓)
        if ($settings == '1') {
            // إرسال البريد
            $mailer->sendEmail(...);
        }
    }
}
```

---

### 2️⃣ **Scenario: method = 'available_score'**

#### المتطلب:
- استخدام أي تقييم متاح (المدير/المشرف/المتوسط)
- تقييم واحد يكفي لإكمال العملية

#### شروط الإرسال:
```
متى يرسل؟
├─ عند إرسال تقييم المدير (submit) ✅
│  └─ السبب: التقييم متاح للعرض (يمكن استخدام تقييم المدير وحده)
│
└─ عند إرسال تقييم المشرف (submit):
   ├─ إذا لم يقيّم المدير بعد → أرسل ✅
   └─ إذا قيّم المدير قبلاً → لا تأرسل مجدداً ⏭️
```

#### الكود المقترح (manager/evaluate.php):
```php
if (isset($_POST['submit'])) {
    // ... الكود الموجود ...
    
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    if ($method === 'available_score') {
        // أرسل دائماً عند إرسال المدير (هو الأول عادة)
        if ($settings == '1') {
            $mailer->sendEmail(...);
        }
    }
}
```

#### الكود المقترح (supervisor/evaluate.php):
```php
if (isset($_POST['submit'])) {
    // ... الكود الموجود ...
    
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    if ($method === 'available_score') {
        // فحص: هل المدير قيّم قبل المشرف؟
        $manager_eval = $pdo->prepare("
            SELECT id FROM employee_evaluations 
            WHERE employee_id = ? AND cycle_id = ? 
            AND evaluator_role = 'manager' 
            AND status != 'draft'
        ");
        $manager_eval->execute([$employee_id, $active_cycle['id']]);
        
        if (!$manager_eval->fetchColumn()) {
            // المدير لم يقيّم بعد → أرسل
            if ($settings == '1') {
                $mailer->sendEmail(...);
            }
        }
        // المدير قيّم قبلاً → لا تأرسل (تم الإرسال مسبقاً)
    }
}
```

---

### 3️⃣ **Scenario: method = 'average_complete'**

#### المتطلب:
- إذا كان الموظف **بدون رئيس مباشر** (supervisor_id = NULL):
  - استخدم تقييم المدير فقط
  
- إذا كان الموظف **لديه رئيس مباشر** (supervisor_id ≠ NULL):
  - **يجب** أن يقيّم كلاهما (المدير والمشرف)
  - التقييم غير مكتمل إذا نقص أحدهما

#### شروط الإرسال:
```
متى يرسل؟

الحالة A: الموظف بدون رئيس (supervisor_id = NULL)
├─ عند إرسال تقييم المدير (submit) → أرسل ✅
│  └─ السبب: التقييم مكتمل (لا يوجد رئيس)
│
└─ عند إرسال تقييم المشرف → لا يوجد رئيس (استثناء) ❌

الحالة B: الموظف لديه رئيس (supervisor_id ≠ NULL)
├─ عند إرسال تقييم المدير (submit)
│  ├─ إذا قيّم المشرف قبلاً → أرسل ✅
│  │  (التقييم أصبح مكتملاً)
│  │
│  └─ إذا لم يقيّم المشرف بعد → لا تأرسل ❌
│     (في انتظار تقييم المشرف)
│
└─ عند إرسال تقييم المشرف (submit)
   ├─ إذا قيّم المدير قبلاً → أرسل ✅
   │  (التقييم أصبح مكتملاً الآن)
   │
   └─ إذا لم يقيّم المدير بعد → لا تأرسل ❌
      (في انتظار تقييم المدير)
```

#### الكود المقترح (manager/evaluate.php):
```php
if (isset($_POST['submit'])) {
    // ... الكود الموجود ...
    
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    if ($method === 'average_complete') {
        $should_send = false;
        
        // فحص: هل الموظف لديه رئيس مباشر؟
        if (!$employee['supervisor_id']) {
            // لا رئيس → أرسل (التقييم مكتمل)
            $should_send = true;
        } else {
            // يوجد رئيس → تحقق من تقييم المشرف
            $supervisor_eval = $pdo->prepare("
                SELECT id FROM employee_evaluations 
                WHERE employee_id = ? AND cycle_id = ? 
                AND evaluator_role = 'supervisor' 
                AND status != 'draft'
            ");
            $supervisor_eval->execute([$employee_id, $active_cycle['id']]);
            
            if ($supervisor_eval->fetchColumn()) {
                // المشرف قيّم قبلاً → أرسل (التقييم مكتمل)
                $should_send = true;
            }
            // المشرف لم يقيّم بعد → لا تأرسل
        }
        
        if ($should_send && $settings == '1') {
            $mailer->sendEmail(...);
        }
    }
}
```

#### الكود المقترح (supervisor/evaluate.php):
```php
if (isset($_POST['submit'])) {
    // ... الكود الموجود ...
    
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    if ($method === 'average_complete') {
        $should_send = false;
        
        // فحص: هل الموظف لديه رئيس مباشر؟
        // (يجب أن تكون الإجابة "نعم" إذا وصلنا هنا)
        
        if ($employee['supervisor_id']) {
            // يوجد رئيس → تحقق من تقييم المدير
            $manager_eval = $pdo->prepare("
                SELECT id FROM employee_evaluations 
                WHERE employee_id = ? AND cycle_id = ? 
                AND evaluator_role = 'manager' 
                AND status != 'draft'
            ");
            $manager_eval->execute([$employee_id, $active_cycle['id']]);
            
            if ($manager_eval->fetchColumn()) {
                // المدير قيّم قبلاً → أرسل (التقييم مكتمل)
                $should_send = true;
            }
            // المدير لم يقيّم بعد → لا تأرسل
        }
        
        if ($should_send && $settings == '1') {
            $mailer->sendEmail(...);
        }
    }
}
```

---

## التعديلات المطلوبة في كل ملف

### `/public/manager/evaluate.php`

**الملف الحالي:**
- السطور 381-398 يحتوي على كود الإرسال (بسيط جداً)

**التعديل المقترح:**
1. إضافة فحص للطريقة الحالية
2. حساب ما إذا كان التقييم مكتملاً
3. إرسال البريد فقط إذا كان مكتملاً

**مثال الكود:**
```php
// بعد السطر 366: UPDATE status = 'submitted'

$settings = $pdo->query("SELECT value FROM system_settings WHERE `key`='auto_send_eval'")->fetchColumn();

if ($settings == '1') {
    require_once '../../app/core/Mailer.php';
    require_once '../../app/core/EvaluationCalculator.php';
    
    $calculator = new EvaluationCalculator($pdo);
    $method = $calculator->getEvaluationMethod();
    
    $should_send = evaluateCompleteness($pdo, $method, $employee, $employee_id, $active_cycle['id']);
    
    if ($should_send) {
        // إنشاء الرابط وإرسال البريد (الكود الموجود)
        $mailer = new Mailer($pdo);
        // ...
    }
}
```

---

### `/public/supervisor/evaluate.php`

**الملف الحالي:**
- السطور 230-241 معالجة submit **بدون** إرسال بريد

**التعديل المقترح:**
1. إضافة نفس منطق الإرسال من manager/evaluate.php
2. مع حساب الاكتمال حسب الطريقة
3. إرسال البريد عند اكتمال التقييم

**الإجراء:**
1. نسخ الكود من manager/evaluate.php (السطور 381-398)
2. تعديله ليناسب supervisor (المشرف)
3. إضافة فحوصات الاكتمال الصحيحة

---

## دالة مساعدة مقترحة

يمكن إنشاء دالة في `EvaluationCalculator` أو ملف جديد:

```php
/**
 * فحص ما إذا كان التقييم "مكتملاً" للإرسال
 * 
 * @param PDO $pdo اتصال قاعدة البيانات
 * @param string $method الطريقة (manager_only, available_score, average_complete)
 * @param array $employee بيانات الموظف
 * @param int $employeeId معرف الموظف
 * @param int $cycleId معرف دورة التقييم
 * @param string $completedBy من قام بالتقييم ('manager' أو 'supervisor')
 * @return bool هل التقييم مكتمل؟
 */
function isEvaluationComplete($pdo, $method, $employee, $employeeId, $cycleId, $completedBy) {
    switch ($method) {
        case 'manager_only':
            // مكتمل إذا قام المدير
            return $completedBy === 'manager';
        
        case 'available_score':
            // مكتمل دائماً (أي تقييم متاح)
            return true;
        
        case 'average_complete':
            if (!$employee['supervisor_id']) {
                // بدون رئيس → مكتمل عند المدير
                return $completedBy === 'manager';
            } else {
                // مع رئيس → مكتمل عند كلاهما
                $both_evaluated = $pdo->prepare("
                    SELECT COUNT(*) FROM employee_evaluations 
                    WHERE employee_id = ? AND cycle_id = ? 
                    AND evaluator_role IN ('manager', 'supervisor')
                    AND status != 'draft'
                ")->execute([$employeeId, $cycleId]);
                
                return $both_evaluated->fetchColumn() >= 2;
            }
        
        default:
            return false;
    }
}
```

---

## قائمة التحقق من التطبيق

### التطوير:
- [ ] إضافة فحص الطريقة في manager/evaluate.php
- [ ] إضافة إرسال بريد في supervisor/evaluate.php
- [ ] إضافة دالة `isEvaluationComplete()` أو `shouldSendEmail()`
- [ ] معالجة أخطاء الإرسال (تسجيل في السجلات)
- [ ] إضافة رسالة للمستخدم إذا فشل الإرسال

### الاختبار:
- [ ] اختبار إرسال عند method = 'manager_only'
- [ ] اختبار عدم الإرسال المتكرر
- [ ] اختبار method = 'available_score' مع تقييم واحد
- [ ] اختبار method = 'average_complete' مع موظف بدون رئيس
- [ ] اختبار method = 'average_complete' مع موظف لديه رئيس
  - قام المدير فقط → لا إرسال
  - قام المشرف أولاً → قام المدير → إرسال من المشرف عند إرساله
- [ ] التحقق من السجلات والأخطاء

### التوثيق:
- [ ] تحديث README
- [ ] توثيق السلوك الجديد في ملف منفصل
- [ ] إضافة تعليقات في الكود

---

## ملاحظات إضافية

### 1. الرابط الفريد
- يجب إنشاء رابط فريد واحد فقط حسب (employee_id, cycle_id)
- الكود الحالي يفحص هذا بشكل صحيح: `if (!$existing_token)`

### 2. الإرسال المتكرر
- يجب تجنب إرسال البريد أكثر من مرة للموظف في نفس الدورة
- الحل: فحص وجود الرابط قبل الإرسال (موجود ✓)

### 3. الإشعارات الداخلية
- يمكن إضافة إشعارات في قاعدة البيانات بدلاً من البريس
- مفيد للموظفين الذين قد لا يتفقدون بريدهم

### 4. تسجيل الأنشطة
- الكود الحالي يسجل: "قام بإرسال التقييم النهائي"
- يجب إضافة: "تم إرسال البريد للموظف" أو "فشل إرسال البريس"

---

**تم إعداد المقترحات بواسطة:** فريق التحليل  
**التاريخ:** 13 ديسمبر 2025
