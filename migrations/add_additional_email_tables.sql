-- إنشاء الجداول الإضافية المطلوبة لنظام البريد الإلكتروني

-- 1. جدول حدود البريد الإلكتروني (Rate Limits)
CREATE TABLE IF NOT EXISTS `email_rate_limits` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `email_address` VARCHAR(255),
  `daily_count` INT DEFAULT 0,
  `hourly_count` INT DEFAULT 0,
  `last_reset` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_address (email_address)
);

-- 2. جدول إعدادات البريد الإلكتروني
CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `setting_key` VARCHAR(255) UNIQUE NOT NULL,
  `setting_value` LONGTEXT,
  `setting_type` VARCHAR(50),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_setting_key (setting_key)
);

-- 3. إدراج البيانات الأولية لجدول الإعدادات
INSERT IGNORE INTO `email_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('max_emails_per_hour', '100', 'integer', 'الحد الأقصى للرسائل المرسلة في الساعة الواحدة'),
('max_emails_per_day', '1000', 'integer', 'الحد الأقصى للرسائل المرسلة يومياً'),
('max_emails_per_recipient', '5', 'integer', 'الحد الأقصى للرسائل المستقبل الواحد يومياً'),
('email_encryption', 'true', 'boolean', 'تفعيل تشفير البريد الإلكتروني'),
('email_anonymization', 'true', 'boolean', 'تفعيل إخفاء هوية عنوان البريد في السجلات'),
('retry_failed_emails', 'true', 'boolean', 'إعادة محاولة إرسال الرسائل الفاشلة تلقائياً'),
('smtp_timeout', '30', 'integer', 'مهلة الاتصال SMTP بالثواني'),
('log_retention_days', '90', 'integer', 'عدد أيام الاحتفاظ بسجلات البريد');

-- 4. إدراج بعض البيانات النموذجية للاختبار
INSERT IGNORE INTO `email_logs` (`employee_id`, `to_email`, `subject`, `body`, `email_type`, `status`, `metadata`) VALUES
(1, 'test@example.com', 'رسالة اختبار', 'هذه رسالة اختبار للتحقق من عمل النظام', 'test', 'success', '{"test_mode": true}'),
(2, 'test2@example.com', 'إشعار تذكير', 'هذا إشعار تذكير للموظف', 'reminder', 'failure', '{"error": "SMTP connection failed"}'),
(3, 'test3@example.com', 'تقرير الأداء', 'محتوى تقرير الأداء الشهري', 'report', 'success', '{"cycle_id": 1}');