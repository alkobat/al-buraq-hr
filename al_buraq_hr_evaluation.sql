-- --------------------------------------------------------
-- نظام تقييم الأداء الوظيفي - شركة البراق للنقل الجوي
-- --------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar` VARCHAR(150) NOT NULL COMMENT 'اسم الإدارة بالعربية',
  `name_en` VARCHAR(150) NULL DEFAULT NULL COMMENT 'اسم الإدارة بالإنجليزية',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL COMMENT 'الاسم الكامل',
  `email` VARCHAR(100) NOT NULL UNIQUE COMMENT 'البريد الإلكتروني',
  `password` VARCHAR(255) NOT NULL COMMENT 'كلمة المرور المشفرة',
  `role` ENUM('admin','manager','supervisor','employee','evaluator') NOT NULL COMMENT 'الدور',
  `department_id` INT UNSIGNED NULL COMMENT 'الإدارة',
  `manager_id` INT UNSIGNED NULL COMMENT 'مدير الإدارة (ID)',
  `supervisor_id` INT UNSIGNED NULL COMMENT 'الرئيس المباشر (ID)',
  `job_title` VARCHAR(100) NULL COMMENT 'الوظيفة',
  `birth_date` DATE NULL COMMENT 'تاريخ الميلاد',
  `marital_status` VARCHAR(20) NULL COMMENT 'الحالة الاجتماعية',
  `gender` ENUM('ذكر','أنثى') NULL COMMENT 'النوع',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `force_password_change` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'إجبار تغيير كلمة المرور عند أول دخول',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supervisor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluation_cycles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year` YEAR NOT NULL UNIQUE COMMENT 'سنة التقييم',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluation_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cycle_id` INT UNSIGNED NOT NULL COMMENT 'دورة التقييم',
  `title_ar` VARCHAR(200) NOT NULL COMMENT 'عنوان المجال بالعربية',
  `title_en` VARCHAR(200) NULL,
  `max_score` TINYINT UNSIGNED NOT NULL DEFAULT 20 COMMENT 'الدرجة القصوى (يجب أن يجمع المجموع 100)',
  `is_required` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'هل الحقل إلزامي؟',
  `order_index` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluation_custom_text_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cycle_id` INT UNSIGNED NOT NULL COMMENT 'دورة التقييم',
  `title_ar` VARCHAR(200) NOT NULL COMMENT 'عنوان الحقل بالعربية',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل الحقل إلزامي؟',
  `order_index` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluation_custom_text_responses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluation_id` INT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `response_text` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_custom_response` (`evaluation_id`, `field_id`),
  FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`field_id`) REFERENCES `evaluation_custom_text_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_evaluations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL COMMENT 'الموظف المُقيَّم',
  `cycle_id` INT UNSIGNED NOT NULL,
  `evaluator_id` INT UNSIGNED NOT NULL COMMENT 'المُقيّم (مدير أو رئيس مباشر)',
  `evaluator_role` ENUM('manager','supervisor') NOT NULL,
  `status` ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `total_score` DECIMAL(5,2) NULL COMMENT 'المجموع النهائي (0-100)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_eval` (`employee_id`, `cycle_id`, `evaluator_role`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evaluation_responses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluation_id` INT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `score` TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (`score` >= 0),
  `comments` TEXT NULL COMMENT 'تعليقات اختيارية',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_response` (`evaluation_id`, `field_id`),
  FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`field_id`) REFERENCES `evaluation_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `strengths_weaknesses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluation_id` INT UNSIGNED NOT NULL,
  `type` ENUM('strength','weakness') NOT NULL COMMENT 'نوع الملاحظة',
  `description` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_evaluation_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `cycle_id` INT UNSIGNED NOT NULL,
  `unique_token` VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID فريد',
  `expires_at` DATETIME NULL COMMENT 'تاريخ انتهاء الصلاحية (اختياري)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

CREATE TABLE `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(50) NOT NULL UNIQUE,
  `value` TEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`key`, `value`) VALUES
('company_name', 'شركة البراق للنقل الجوي'),
('primary_color', '#0d6efd'),
('secondary_color', '#6c757d'),
('logo_path', NULL),
('template_style', 'light');

INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `force_password_change`)
VALUES ('المسؤول الرئيسي', 'admin@alburaq.aero', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1);

SET FOREIGN_KEY_CHECKS = 1;