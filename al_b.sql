-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 20 أكتوبر 2025 الساعة 00:29
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `al_b`
--

-- --------------------------------------------------------

--
-- بنية الجدول `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name_ar` varchar(150) NOT NULL COMMENT 'اسم الإدارة بالعربية',
  `name_en` varchar(150) DEFAULT NULL COMMENT 'اسم الإدارة بالإنجليزية',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `departments`
--

INSERT INTO `departments` (`id`, `name_ar`, `name_en`, `status`, `created_at`) VALUES
(1, 'الموارد البشرية', NULL, 'active', '2025-10-13 20:52:58'),
(2, 'العامة', NULL, 'active', '2025-10-13 22:41:08'),
(3, 'المالية', NULL, 'active', '2025-10-13 22:41:12'),
(4, 'الفنية', NULL, 'active', '2025-10-13 22:41:17'),
(5, 'العمليات الجوية', NULL, 'active', '2025-10-13 22:41:23'),
(6, 'العمليات الأرضية', NULL, 'active', '2025-10-13 22:41:28'),
(7, 'مكتب بنغازي', NULL, 'active', '2025-10-13 22:41:33'),
(8, 'التطوير والتخطيط', NULL, 'active', '2025-10-13 22:42:22'),
(9, 'الصلاحية الجوية (CAMO)', NULL, 'active', '2025-10-13 22:42:46');

-- --------------------------------------------------------

--
-- بنية الجدول `employee_evaluations`
--

CREATE TABLE `employee_evaluations` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL COMMENT 'الموظف المُقيَّم',
  `cycle_id` int(10) UNSIGNED NOT NULL,
  `evaluator_id` int(10) UNSIGNED NOT NULL COMMENT 'المُقيّم (مدير أو رئيس مباشر)',
  `evaluator_role` enum('manager','supervisor') NOT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `total_score` decimal(5,2) DEFAULT NULL COMMENT 'المجموع النهائي (0-100)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `employee_evaluations`
--

INSERT INTO `employee_evaluations` (`id`, `employee_id`, `cycle_id`, `evaluator_id`, `evaluator_role`, `status`, `total_score`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 2, 'manager', 'submitted', 60.00, '2025-10-14 21:40:26', '2025-10-19 22:00:42'),
(2, 4, 1, 2, 'manager', 'submitted', 55.00, '2025-10-18 17:54:00', '2025-10-19 21:59:35'),
(3, 3, 1, 5, 'supervisor', 'submitted', 87.00, '2025-10-19 19:56:56', '2025-10-19 21:38:39');

-- --------------------------------------------------------

--
-- بنية الجدول `employee_evaluation_links`
--

CREATE TABLE `employee_evaluation_links` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `cycle_id` int(10) UNSIGNED NOT NULL,
  `unique_token` varchar(36) NOT NULL COMMENT 'UUID فريد',
  `expires_at` datetime DEFAULT NULL COMMENT 'تاريخ انتهاء الصلاحية (اختياري)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `employee_evaluation_links`
--

INSERT INTO `employee_evaluation_links` (`id`, `employee_id`, `cycle_id`, `unique_token`, `expires_at`, `created_at`) VALUES
(1, 3, 1, '984700bf6027bcbc0acc4a31c134981b', NULL, '2025-10-19 21:37:05'),
(2, 4, 1, 'b65dc38520a3082c49cfc4f0afadb8c6', NULL, '2025-10-19 21:40:28');

-- --------------------------------------------------------

--
-- بنية الجدول `evaluation_custom_text_fields`
--

CREATE TABLE `evaluation_custom_text_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `cycle_id` int(10) UNSIGNED NOT NULL COMMENT 'دورة التقييم',
  `title_ar` varchar(200) NOT NULL COMMENT 'عنوان الحقل بالعربية',
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'هل الحقل إلزامي؟',
  `order_index` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluation_custom_text_fields`
--

INSERT INTO `evaluation_custom_text_fields` (`id`, `cycle_id`, `title_ar`, `is_required`, `order_index`) VALUES
(1, 1, 'الدورات التدريبية التي يحتاجها', 0, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `evaluation_custom_text_responses`
--

CREATE TABLE `evaluation_custom_text_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `evaluation_id` int(10) UNSIGNED NOT NULL,
  `field_id` int(10) UNSIGNED NOT NULL,
  `response_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluation_custom_text_responses`
--

INSERT INTO `evaluation_custom_text_responses` (`id`, `evaluation_id`, `field_id`, `response_text`) VALUES
(1, 2, 1, '78527828'),
(3, 3, 1, 'تانعهم'),
(7, 1, 1, 'كل شي');

-- --------------------------------------------------------

--
-- بنية الجدول `evaluation_cycles`
--

CREATE TABLE `evaluation_cycles` (
  `id` int(10) UNSIGNED NOT NULL,
  `year` year(4) NOT NULL COMMENT 'سنة التقييم',
  `status` enum('active','inactive') NOT NULL DEFAULT 'inactive',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluation_cycles`
--

INSERT INTO `evaluation_cycles` (`id`, `year`, `status`, `start_date`, `end_date`, `created_at`) VALUES
(1, '2025', 'active', NULL, NULL, '2025-10-13 20:53:19');

-- --------------------------------------------------------

--
-- بنية الجدول `evaluation_fields`
--

CREATE TABLE `evaluation_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `cycle_id` int(10) UNSIGNED NOT NULL COMMENT 'دورة التقييم',
  `title_ar` varchar(200) NOT NULL COMMENT 'عنوان المجال بالعربية',
  `title_en` varchar(200) DEFAULT NULL,
  `max_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 20 COMMENT 'الدرجة القصوى (يجب أن يجمع المجموع 100)',
  `is_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'هل الحقل إلزامي؟',
  `order_index` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluation_fields`
--

INSERT INTO `evaluation_fields` (`id`, `cycle_id`, `title_ar`, `title_en`, `max_score`, `is_required`, `order_index`) VALUES
(1, 1, 'المظهر والقيافة', NULL, 10, 1, 0),
(2, 1, 'العمل', NULL, 90, 1, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `evaluation_responses`
--

CREATE TABLE `evaluation_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `evaluation_id` int(10) UNSIGNED NOT NULL,
  `field_id` int(10) UNSIGNED NOT NULL,
  `score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 CHECK (`score` >= 0),
  `comments` text DEFAULT NULL COMMENT 'تعليقات اختيارية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluation_responses`
--

INSERT INTO `evaluation_responses` (`id`, `evaluation_id`, `field_id`, `score`, `comments`) VALUES
(1, 1, 1, 10, NULL),
(3, 1, 2, 50, NULL),
(12, 2, 1, 5, NULL),
(13, 2, 2, 50, NULL),
(24, 3, 1, 7, NULL),
(25, 3, 2, 80, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message_ar` text NOT NULL,
  `message_en` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link` varchar(255) DEFAULT NULL COMMENT 'رابط للانتقال',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message_ar`, `message_en`, `is_read`, `link`, `created_at`) VALUES
(1, 1, 'قام الموظف بالموافقة على تقييمه.', NULL, 0, NULL, '2025-10-19 21:43:40'),
(2, 4, 'قام الموظف بالموافقة على تقييمه.', NULL, 0, NULL, '2025-10-19 21:43:40'),
(4, 1, 'قام الموظف برفض تقييمه.', NULL, 0, NULL, '2025-10-19 21:59:06'),
(5, 4, 'قام الموظف برفض تقييمه.', NULL, 0, NULL, '2025-10-19 21:59:06');

-- --------------------------------------------------------

--
-- بنية الجدول `strengths_weaknesses`
--

CREATE TABLE `strengths_weaknesses` (
  `id` int(10) UNSIGNED NOT NULL,
  `evaluation_id` int(10) UNSIGNED NOT NULL,
  `type` enum('strength','weakness') NOT NULL COMMENT 'نوع الملاحظة',
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `system_settings`
--

INSERT INTO `system_settings` (`id`, `key`, `value`) VALUES
(1, 'company_name', 'شركة البراق للنقل الجوي'),
(2, 'primary_color', '#0d6efd'),
(3, 'secondary_color', '#6c757d'),
(4, 'logo_path', 'logo.png'),
(5, 'template_style', 'light');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'الاسم الكامل',
  `email` varchar(100) NOT NULL COMMENT 'البريد الإلكتروني',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور المشفرة',
  `role` enum('admin','manager','supervisor','employee','evaluator') NOT NULL COMMENT 'الدور',
  `department_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'الإدارة',
  `manager_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'مدير الإدارة (ID)',
  `supervisor_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'الرئيس المباشر (ID)',
  `job_title` varchar(100) DEFAULT NULL COMMENT 'الوظيفة',
  `birth_date` date DEFAULT NULL COMMENT 'تاريخ الميلاد',
  `marital_status` varchar(20) DEFAULT NULL COMMENT 'الحالة الاجتماعية',
  `gender` enum('ذكر','أنثى') DEFAULT NULL COMMENT 'النوع',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'إجبار تغيير كلمة المرور عند أول دخول',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `department_id`, `manager_id`, `supervisor_id`, `job_title`, `birth_date`, `marital_status`, `gender`, `status`, `force_password_change`, `created_at`, `last_login`) VALUES
(1, 'المسؤول الرئيسي', 'alkobat@buraq.aero', '$2y$10$3FJyqBJTJt/Do3QE77wWLOuGpDnyfwOipAXq/E1eZs0MPgBGUGPKO', 'admin', NULL, NULL, NULL, '', NULL, NULL, NULL, 'active', 0, '2025-10-13 20:32:04', '2025-10-19 21:48:43'),
(2, 'مجدي', 'hr.manager@buraq.aero', '$2y$10$wlqmwJ1GURl3EIJmxof2iuc6eXs.J9QTvu.mnSza3krbKGcUEFzHy', 'manager', 1, NULL, NULL, 'مدير ادارة الموارد البشرية', NULL, NULL, NULL, 'active', 0, '2025-10-13 21:42:10', '2025-10-19 23:39:49'),
(3, 'موسى', 'mosa@buraq.aero', '$2y$10$ZtMDxEwdPZRQUuRBQBcVzeDWoIwLO7FU.9R8Q2970Njl5PzdHp8s6', 'employee', 1, 2, 5, 'موظف', NULL, NULL, NULL, 'active', 0, '2025-10-13 22:04:46', '2025-10-18 12:58:48'),
(4, 'وصال الهادي العزابي', 'wesal@buraq.aero', '$2y$10$GuMMZ3nvM/tDsnWit4zj0O9CakkP86CNxTLJlxpvT6G4k9wK0T2Tu', 'evaluator', 1, 2, NULL, 'منسق وحدة شئون العاملين', NULL, NULL, NULL, 'active', 0, '2025-10-14 20:28:47', '2025-10-19 21:53:30'),
(5, 'حاتم عياد بن حامد', 'hatem@buraq.aero', '$2y$10$pfUVPUdgxVnWSs5a0eWzZ.AEMs16JmTBHkC08rn1sIu0K2ptn00lO', 'supervisor', 1, 2, NULL, 'موظف', NULL, NULL, NULL, 'active', 0, '2025-10-19 19:55:23', '2025-10-19 23:38:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_evaluations`
--
ALTER TABLE `employee_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_eval` (`employee_id`,`cycle_id`,`evaluator_role`),
  ADD KEY `evaluator_id` (`evaluator_id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Indexes for table `employee_evaluation_links`
--
ALTER TABLE `employee_evaluation_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`unique_token`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Indexes for table `evaluation_custom_text_fields`
--
ALTER TABLE `evaluation_custom_text_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Indexes for table `evaluation_custom_text_responses`
--
ALTER TABLE `evaluation_custom_text_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_custom_response` (`evaluation_id`,`field_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `evaluation_cycles`
--
ALTER TABLE `evaluation_cycles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year` (`year`);

--
-- Indexes for table `evaluation_fields`
--
ALTER TABLE `evaluation_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Indexes for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_response` (`evaluation_id`,`field_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `strengths_weaknesses`
--
ALTER TABLE `strengths_weaknesses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluation_id` (`evaluation_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employee_evaluations`
--
ALTER TABLE `employee_evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employee_evaluation_links`
--
ALTER TABLE `employee_evaluation_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `evaluation_custom_text_fields`
--
ALTER TABLE `evaluation_custom_text_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `evaluation_custom_text_responses`
--
ALTER TABLE `evaluation_custom_text_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `evaluation_cycles`
--
ALTER TABLE `evaluation_cycles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `evaluation_fields`
--
ALTER TABLE `evaluation_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `strengths_weaknesses`
--
ALTER TABLE `strengths_weaknesses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `employee_evaluations`
--
ALTER TABLE `employee_evaluations`
  ADD CONSTRAINT `employee_evaluations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_evaluations_ibfk_2` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_evaluations_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `employee_evaluation_links`
--
ALTER TABLE `employee_evaluation_links`
  ADD CONSTRAINT `employee_evaluation_links_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_evaluation_links_ibfk_2` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `evaluation_custom_text_fields`
--
ALTER TABLE `evaluation_custom_text_fields`
  ADD CONSTRAINT `evaluation_custom_text_fields_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `evaluation_custom_text_responses`
--
ALTER TABLE `evaluation_custom_text_responses`
  ADD CONSTRAINT `evaluation_custom_text_responses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_custom_text_responses_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `evaluation_custom_text_fields` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `evaluation_fields`
--
ALTER TABLE `evaluation_fields`
  ADD CONSTRAINT `evaluation_fields_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD CONSTRAINT `evaluation_responses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_responses_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `evaluation_fields` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `strengths_weaknesses`
--
ALTER TABLE `strengths_weaknesses`
  ADD CONSTRAINT `strengths_weaknesses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
