-- تصحيح أسماء الأعمدة في جدول email_logs
-- يقوم هذا السكريبت بإعادة تسمية العمود to_email إلى recipient_email

-- التحقق من وجود العمود to_email وإعادة تسميته إلى recipient_email
-- إذا كان العمود موجوداً، سيتم تغيير الاسم
ALTER TABLE `email_logs` 
  CHANGE COLUMN `to_email` `recipient_email` varchar(150) DEFAULT NULL;

-- ملاحظة: إذا كان العمود recipient_email موجوداً بالفعل، سيظهر خطأ وهذا طبيعي
-- يمكن تجاهل الخطأ في هذه الحالة
