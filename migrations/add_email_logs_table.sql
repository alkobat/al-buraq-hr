-- إنشاء جدول سجلات البريد الإلكتروني

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `cycle_id` int(10) UNSIGNED DEFAULT NULL,
  `to_email` varchar(150) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `email_type` varchar(50) DEFAULT NULL,
  `status` enum('success','failure') NOT NULL DEFAULT 'failure',
  `error_message` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_employee_cycle` (`employee_id`,`cycle_id`),
  KEY `idx_email_logs_type_status` (`email_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
