-- إضافة جداول أمان البريد الإلكتروني

-- 1. إضافة عمود للبريد المشفر والمجزأ في سجلات البريد (للخصوصية)
ALTER TABLE `email_logs` ADD COLUMN `recipient_email_hash` varchar(64) DEFAULT NULL AFTER `to_email`;
ALTER TABLE `email_logs` ADD COLUMN `is_encrypted` tinyint(1) DEFAULT 0 AFTER `recipient_email_hash`;
ALTER TABLE `email_logs` ADD KEY `idx_recipient_hash` (`recipient_email_hash`);

-- 2. إنشاء جدول سجلات حد التصنيف
CREATE TABLE IF NOT EXISTS `email_rate_limit_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(150) NOT NULL,
  `sender_id` varchar(50) DEFAULT 'system',
  `success` tinyint(1) DEFAULT 1,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recipient_time` (`recipient_email`, `attempted_at`),
  KEY `idx_sender_time` (`sender_id`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. إضافة عمود كلمة المرور المشفرة للإعدادات
ALTER TABLE `system_settings` ADD COLUMN `is_encrypted` tinyint(1) DEFAULT 0 AFTER `value`;

-- 4. إنشاء جدول سياسات الخصوصية (GDPR)
CREATE TABLE IF NOT EXISTS `gdpr_policies` (
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

-- 5. إدراج سياسات GDPR الافتراضية
INSERT INTO `gdpr_policies` (`policy_key`, `policy_name`, `policy_value`, `description`, `is_active`) VALUES
('email_logs_retention_days', 'فترة الاحتفاظ بسجلات البريد', '90', 'عدد أيام الاحتفاظ بسجلات البريد الإلكتروني', 1),
('max_emails_per_hour', 'الحد الأقصى للبريد في الساعة', '100', 'الحد الأقصى للرسائل المرسلة في الساعة الواحدة', 1),
('max_emails_per_recipient_daily', 'الحد الأقصى للبريد للمستقبل يومياً', '5', 'الحد الأقصى لعدد الرسائل للمستقبل الواحد يومياً', 1),
('encrypt_sensitive_data', 'تشفير البيانات الحساسة', '1', 'تشفير بيانات المستقبل وكلمات المرور', 1),
('anonymize_email_logs', 'إخفاء هوية سجلات البريد', '1', 'استخدام hash للبريد الإلكتروني بدلاً من النص الصريح', 1),
('allow_data_export', 'السماح بتصدير البيانات', '1', 'السماح للمستخدمين بتصدير بيانات البريد الخاصة بهم', 1),
('allow_data_deletion', 'السماح بحذف البيانات', '1', 'السماح للمستخدمين بحذف سجل بريدهم القديم', 1);
