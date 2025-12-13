# ميزة طريقة احتساب التقييم المرجح

## نظرة عامة

تم إضافة ميزة جديدة تسمح بتحديد طريقة احتساب التقييم النهائي للموظف عند وجود تقييمين (من المدير والمشرف المباشر).

## الطرق المتاحة

### 1. تقييم مدير الإدارة فقط (الافتراضي)
- **الرمز**: `manager_only`
- **الحساب**: التقييم النهائي = تقييم المدير
- **الوصف**: الطريقة التقليدية حيث يعتمد التقييم النهائي على تقييم المدير فقط

### 2. متوسط التقييمين
- **الرمز**: `average`
- **الحساب**: التقييم النهائي = (تقييم المدير + تقييم المشرف) ÷ 2
- **الوصف**: تأخذ بعين الاعتبار كلا التقييمين بوزن متساوي

## الملفات المضافة

### 1. `app/core/EvaluationCalculator.php`
كلاس جديد يوفر وظائف حساب التقييم النهائي:

```php
// مثال على الاستخدام
$calculator = new EvaluationCalculator($pdo);

// جلب الطريقة الحالية
$method = $calculator->getEvaluationMethod(); // 'manager_only' أو 'average'

// حساب التقييم النهائي
$finalScore = $calculator->calculateFinalScore($managerScore, $supervisorScore);

// جلب جميع تقييمات موظف
$scores = $calculator->getEmployeeScores($employeeId, $cycleId);
// يرجع: ['manager_score' => 85, 'supervisor_score' => 90, 'final_score' => 87.5, 'method' => 'average']

// تغيير الطريقة (للمسؤولين فقط)
$calculator->setEvaluationMethod('average');

// الحصول على اسم الطريقة بالعربية
$methodName = $calculator->getMethodName(); // "متوسط تقييمي المدير والمشرف"
```

### 2. `public/admin/settings.php`
تم تحديث صفحة الإعدادات لإضافة قسم جديد:
- اختيار طريقة الحساب عبر Radio buttons
- عرض الطريقة الحالية
- حماية CSRF
- تسجيل التغييرات في activity_logs

### 3. `migrations/add_evaluation_method_setting.sql`
سكريبت SQL لإضافة الإعداد الجديد:

```sql
INSERT INTO system_settings (`key`, value) 
VALUES ('evaluation_method', 'manager_only')
ON DUPLICATE KEY UPDATE `key` = `key`;
```

## الملفات المحدثة

### 1. `public/view-evaluation.php`
- عرض تقييم المشرف وتقييم المدير بشكل منفصل
- عرض التقييم النهائي المحسوب
- إضافة ملاحظة توضح طريقة الحساب المستخدمة

### 2. `public/approve.php`
- نفس التحديثات في view-evaluation.php
- عرض شامل لجميع التقييمات

## تشغيل الميزة

### الخطوة 1: تطبيق التحديث على قاعدة البيانات

```bash
mysql -u username -p database_name < migrations/add_evaluation_method_setting.sql
```

أو عبر phpMyAdmin:
1. افتح phpMyAdmin
2. اختر قاعدة البيانات
3. اذهب إلى تبويب SQL
4. انسخ محتوى `migrations/add_evaluation_method_setting.sql` والصقه
5. اضغط "Go"

### الخطوة 2: التحقق من التثبيت

1. سجل الدخول كمسؤول
2. اذهب إلى **إعدادات النظام** (`/public/admin/settings.php`)
3. يجب أن ترى قسم "طريقة احتساب التقييمات"
4. الطريقة الافتراضية هي "تقييم مدير الإدارة فقط"

### الخطوة 3: تغيير الطريقة (اختياري)

1. في صفحة الإعدادات، اختر الطريقة المطلوبة
2. اضغط "حفظ"
3. سيتم تسجيل التغيير في activity_logs

## معالجة الحالات الخاصة

### حالة 1: موظف لديه تقييم من المدير فقط
- **manager_only**: التقييم النهائي = تقييم المدير
- **average**: التقييم النهائي = تقييم المدير (لا يوجد مشرف)

### حالة 2: موظف لديه تقييم من المشرف فقط
- **manager_only**: التقييم النهائي = null (لا يوجد تقييم مدير)
- **average**: التقييم النهائي = تقييم المشرف

### حالة 3: موظف لديه كلا التقييمين
- **manager_only**: التقييم النهائي = تقييم المدير (يتجاهل المشرف)
- **average**: التقييم النهائي = (تقييم المدير + تقييم المشرف) ÷ 2

## التأثير على المكونات الأخرى

### صفحات العرض
- ✅ `public/view-evaluation.php` - محدث
- ✅ `public/approve.php` - محدث
- ⚠️ `public/admin/reports.php` - يعرض التقييمات الخام (يحتاج تحديث مستقبلي)
- ⚠️ `public/admin/analytics-dashboard.php` - يستخدم AnalyticsService (يحتاج تحديث مستقبلي)

### الخدمات الخلفية
- ⚠️ `app/core/AnalyticsService.php` - يستخدم total_score مباشرة من قاعدة البيانات
- ⚠️ `app/core/ExportService.php` - يصدر التقييمات الخام

### ملاحظة مهمة
الخدمات التحليلية والتقارير الحالية تعمل على التقييمات الخام (total_score من كل صف).
للحصول على تحليلات دقيقة بناءً على الطريقة المختارة، يجب تحديث هذه الخدمات مستقبلاً لاستخدام EvaluationCalculator.

## الأمان

1. **CSRF Protection**: جميع نماذج تغيير الإعدادات محمية بـ CSRF tokens
2. **Validation**: التحقق من صحة القيم المدخلة
3. **Prepared Statements**: استخدام prepared statements في جميع الاستعلامات
4. **Activity Logging**: تسجيل كل تغيير في الطريقة في activity_logs
5. **Role Checking**: فقط المسؤولون يمكنهم تغيير الطريقة

## الاختبار

### اختبار 1: التحقق من الحساب
```php
// في manager_only mode
$calculator = new EvaluationCalculator($pdo);
$calculator->setEvaluationMethod('manager_only');
$result = $calculator->calculateFinalScore(80, 90);
// يجب أن يكون $result = 80

// في average mode
$calculator->setEvaluationMethod('average');
$result = $calculator->calculateFinalScore(80, 90);
// يجب أن يكون $result = 85
```

### اختبار 2: التبديل بين الطرق
1. سجل الدخول كمسؤول
2. اذهب إلى الإعدادات
3. غير الطريقة من manager_only إلى average
4. افتح صفحة تقييم موظف لديه كلا التقييمين
5. تحقق من أن التقييم النهائي تغير إلى المتوسط

### اختبار 3: سجل النشاط
1. غير الطريقة
2. اذهب إلى **سجل النشاط** (`/public/admin/activity_logs.php`)
3. يجب أن ترى سجل: "تم تغيير طريقة احتساب التقييمات من 'X' إلى 'Y'"

## الأسئلة الشائعة

**س: هل يؤثر تغيير الطريقة على التقييمات القديمة؟**
ج: نعم، التقييم النهائي يحسب ديناميكياً. تغيير الطريقة يؤثر على جميع التقييمات فوراً.

**س: هل يمكن إضافة طرق حساب أخرى مستقبلاً؟**
ج: نعم، الكلاس مصمم بطريقة قابلة للتوسع. يمكن إضافة طرق جديدة بسهولة.

**س: ماذا يحدث إذا كان أحد التقييمين مفقوداً؟**
ج: في وضع average، إذا كان أحد التقييمين مفقوداً، يُستخدم التقييم المتاح فقط.

**س: هل يمكن تحديد أوزان مختلفة (مثل 70% مدير + 30% مشرف)؟**
ج: حالياً لا، لكن يمكن إضافة هذه الميزة مستقبلاً بتعديل EvaluationCalculator.

## الصيانة المستقبلية

### التحسينات المقترحة
1. إضافة طريقة حساب بأوزان قابلة للتخصيص
2. تحديث AnalyticsService لاستخدام EvaluationCalculator
3. تحديث ExportService لعرض التقييم النهائي بدلاً من الخام
4. إضافة تقارير مقارنة بين تقييمات المدير والمشرف
5. إضافة إمكانية تحديد طريقة مختلفة لكل دورة تقييم

## الدعم الفني

للمساعدة أو الإبلاغ عن مشاكل:
1. تحقق من سجل activity_logs للأخطاء
2. تحقق من error_log في PHP
3. تحقق من أن الإعداد موجود في جدول system_settings

## المراجع

- `app/core/EvaluationCalculator.php` - الكود المصدري الرئيسي
- `IMPLEMENTATION_SUMMARY.md` - ملخص التنفيذ الشامل
- `SECURITY_RECOMMENDATIONS.md` - التوصيات الأمنية
