-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 15 ديسمبر 2025 الساعة 23:57
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
-- بنية الجدول `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المستخدم الذي قام بالعملية',
  `user_name` varchar(150) DEFAULT NULL COMMENT 'الاسم وقت التنفيذ (للحفظ حتى لو حذف المستخدم)',
  `role` varchar(50) DEFAULT NULL COMMENT 'دور المستخدم',
  `action` varchar(100) NOT NULL COMMENT 'نوع العملية: login, create, update, delete, logout',
  `description` text DEFAULT NULL COMMENT 'وصف تفصيلي: مثلا "قام بتعديل الموظف رقم 5"',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'عنوان IP للمستخدم',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `role`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 21:50:34'),
(2, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 21:51:06'),
(3, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 21:51:13'),
(4, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 21:57:17'),
(5, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 22:11:42'),
(6, 4, 'وصال الهادي العزابي', 'evaluator', 'update', 'تم تعديل بيانات المستخدم رقم: 6', '::1', '2025-12-09 22:12:09'),
(7, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 22:12:30'),
(8, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 22:14:29'),
(9, 4, 'وصال الهادي العزابي', 'evaluator', 'create', 'تمت إضافة مستخدم جديد: yjjukyukuy (yjtukuu@tggtrh.gthtr)', '::1', '2025-12-09 22:14:56'),
(10, 4, 'وصال الهادي العزابي', 'evaluator', 'delete', 'تم حذف بيانات المستخدم رقم: ', '::1', '2025-12-09 22:15:14'),
(11, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 22:15:23'),
(12, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-09 22:24:06'),
(13, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-09 22:24:16'),
(14, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 19:29:24'),
(15, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 19:32:55'),
(16, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 19:33:10'),
(17, 4, 'وصال الهادي العزابي', 'evaluator', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 19:43:46'),
(18, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 19:44:45'),
(19, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 19:49:17'),
(20, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 19:56:45'),
(21, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 19:57:30'),
(22, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 19:57:40'),
(23, 4, 'وصال الهادي العزابي', 'evaluator', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:09:44'),
(24, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:09:52'),
(25, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:26:34'),
(26, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:26:46'),
(27, 4, 'وصال الهادي العزابي', 'evaluator', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:28:47'),
(28, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:28:55'),
(29, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:46:39'),
(30, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:46:52'),
(31, 4, 'وصال الهادي العزابي', 'evaluator', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:53:05'),
(32, 2, 'مجدي', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:53:12'),
(33, 2, 'مجدي', 'manager', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:57:06'),
(34, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:57:17'),
(35, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 20:59:17'),
(36, 4, 'وصال الهادي العزابي', 'evaluator', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 20:59:24'),
(37, 4, 'وصال الهادي العزابي', 'evaluator', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 21:00:14'),
(38, 5, 'حاتم عياد بن حامد', 'supervisor', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 21:00:23'),
(39, 5, 'حاتم عياد بن حامد', 'supervisor', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 21:05:35'),
(40, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 21:05:42'),
(41, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 21:08:44'),
(42, 2, 'مجدي', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 21:08:56'),
(43, 2, 'مجدي', 'manager', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 21:12:50'),
(44, 5, 'حاتم عياد بن حامد', 'supervisor', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 21:12:59'),
(45, 5, 'حاتم عياد بن حامد', 'supervisor', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-10 21:33:50'),
(46, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-10 21:34:12'),
(47, 1, 'المسؤول الرئيسي', 'admin', 'create', 'تم إنشاء نسخة احتياطية للنظام: backup_2025-12-10_22-36-03.sql', '::1', '2025-12-10 21:36:03'),
(48, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-11 22:17:34'),
(49, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-12 00:44:06'),
(50, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-13 19:45:51'),
(51, 1, 'المسؤول الرئيسي', 'admin', 'settings', 'تم تغيير طريقة احتساب التقييمات من \'تقييم مدير الإدارة فقط\' إلى \'متوسط تقييمي المدير والمشرف\'', '::1', '2025-12-13 21:25:30'),
(52, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-13 21:26:56'),
(53, 2, 'مجدي', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-13 21:27:08'),
(54, 2, 'مجدي', 'manager', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-13 21:28:40'),
(55, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-13 21:28:47'),
(56, 1, 'المسؤول الرئيسي', 'admin', 'settings', 'تم تغيير طريقة احتساب التقييمات من \'متوسط تقييمي المدير والمشرف\' إلى \'تقييم مدير الإدارة فقط\'', '::1', '2025-12-13 21:28:53'),
(57, 1, 'المسؤول الرئيسي', 'admin', 'settings', 'تم تغيير طريقة احتساب التقييمات من \'تقييم مدير الإدارة فقط\' إلى \'متوسط تقييمي المدير والمشرف\'', '::1', '2025-12-13 21:43:14'),
(58, 1, 'المسؤول الرئيسي', 'admin', 'settings', 'تم تغيير طريقة احتساب التقييمات من \'متوسط المدير والمشرف (مع التحقق من الاكتمال)\' إلى \'استخدام التقييم الموجود\'', '::1', '2025-12-13 22:13:35'),
(59, 1, 'المسؤول الرئيسي', 'admin', 'settings', 'تم تغيير طريقة احتساب التقييمات من \'استخدام التقييم الموجود\' إلى \'تقييم مدير الإدارة فقط\'', '::1', '2025-12-13 22:14:06'),
(60, 1, 'المسؤول الرئيسي', 'admin', 'settings', 'تم تغيير طريقة احتساب التقييمات من \'تقييم مدير الإدارة فقط\' إلى \'متوسط المدير والمشرف (مع التحقق من الاكتمال)\'', '::1', '2025-12-13 22:14:58'),
(61, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 19:31:31'),
(62, 1, 'المسؤول الرئيسي', 'admin', 'delete', 'تم حذف بيانات المستخدم رقم: 6', '::1', '2025-12-14 22:04:11'),
(63, 1, 'المسؤول الرئيسي', 'admin', 'create', 'تمت إضافة مستخدم جديد: خبيبيطة (hr@buraq.aero)', '::1', '2025-12-14 22:05:01'),
(64, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-14 22:06:37'),
(65, 2, 'مجدي', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 22:06:46'),
(66, 2, 'مجدي', 'manager', 'evaluation', 'قام بإرسال تقييم للموظف: خبيبيطة', '::1', '2025-12-14 22:07:16'),
(67, 2, 'مجدي', 'manager', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-14 22:09:19'),
(68, 2, 'مجدي', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 22:15:50'),
(69, 2, 'مجدي', 'manager', 'evaluation', 'قام بإرسال تقييم للموظف: خبيبيطة', '::1', '2025-12-14 22:16:33'),
(70, 2, 'مجدي', 'manager', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-14 22:20:47'),
(71, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 22:20:56'),
(72, 1, 'المسؤول الرئيسي', 'admin', 'update', 'تم تعديل بيانات المستخدم رقم: 8', '::1', '2025-12-14 22:29:07'),
(73, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-14 22:38:06'),
(74, 2, 'مجدي', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 22:38:14'),
(75, 2, 'مجدي', 'manager', 'evaluation', 'قام بإرسال تقييم للموظف: خبيبيطة', '::1', '2025-12-14 22:39:00'),
(76, 2, 'مجدي', 'manager', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-14 22:39:58'),
(77, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 22:40:05'),
(78, 1, 'المسؤول الرئيسي', 'admin', 'delete', 'تم حذف بيانات المستخدم رقم: 8', '::1', '2025-12-14 22:45:12'),
(79, 1, 'المسؤول الرئيسي', 'admin', 'create', 'تمت إضافة مستخدم جديد: خبيبيطة (hr@buraq.aero)', '::1', '2025-12-14 22:46:16'),
(80, 1, 'المسؤول الرئيسي', 'admin', 'delete', 'تم حذف بيانات المستخدم رقم: 9', '::1', '2025-12-14 22:46:46'),
(81, 1, 'المسؤول الرئيسي', 'admin', 'create', 'تمت إضافة مستخدم جديد: خبيبيطة (hr@buraq.aero)', '::1', '2025-12-14 22:46:57'),
(82, 1, 'المسؤول الرئيسي', 'admin', 'logout', 'قام بتسجيل الخروج من النظام', '::1', '2025-12-14 22:47:17'),
(83, 10, 'خبيبيطة', 'manager', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-14 22:47:25'),
(84, 1, 'المسؤول الرئيسي', 'admin', 'login', 'قام بتسجيل الدخول للنظام', '::1', '2025-12-15 19:50:17');

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
-- بنية الجدول `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `status` enum('pending','sent','failed','bounced') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_type` varchar(100) DEFAULT NULL,
  `related_employee_id` int(11) DEFAULT NULL,
  `related_cycle_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `email_rate_limits`
--

CREATE TABLE `email_rate_limits` (
  `id` int(11) NOT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `daily_count` int(11) DEFAULT 0,
  `hourly_count` int(11) DEFAULT 0,
  `last_reset` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `email_rate_limit_logs`
--

CREATE TABLE `email_rate_limit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `sender_id` varchar(50) DEFAULT 'system',
  `success` tinyint(1) DEFAULT 1,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `email_rate_limit_logs`
--

INSERT INTO `email_rate_limit_logs` (`id`, `recipient_email`, `sender_id`, `success`, `attempted_at`) VALUES
(1, 'test@example.com', 'test_user', 1, '2025-12-15 21:45:44'),
(2, 'test@example.com', 'test_user', 1, '2025-12-15 22:47:41');

-- --------------------------------------------------------

--
-- بنية الجدول `email_settings`
--

CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'نوع القالب: new_user, evaluation_link, announcement',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `placeholders` text DEFAULT NULL COMMENT 'وصف المتغيرات المتاحة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `email_templates`
--

INSERT INTO `email_templates` (`id`, `type`, `subject`, `body`, `placeholders`) VALUES
(1, 'new_user', 'بيانات الدخول للنظام', '<p>مرحباً {name}،</p><p>تم إنشاء حساب لك في نظام الموارد البشرية.</p><p><strong>البريد الإلكتروني:</strong> {email}<br><strong>كلمة المرور:</strong> {password}</p><p>يرجى تسجيل الدخول وتغيير كلمة المرور.</p>', '{name}, {email}, {password}'),
(2, 'evaluation_link', 'رابط تقييم الأداء السنوي', '<p>مرحباً {name}،</p><p>قام مديرك المباشر برفع تقييم الأداء الخاص بك.</p><p>يرجى الاطلاع عليه والموافقة أو الرفض عبر الرابط التالي:</p><p><a href=\"{link}\">{link}</a></p>', '{name}, {link}'),
(3, 'announcement', 'إعلان إداري', '<p>مرحباً {name}،</p><p>{message}</p>', '{name}, {message}'),
(4, 'evaluation_reminder', 'تذكير: تقييمات معلقة بانتظار إنجازك', '<p>عزيزي <strong>{name}</strong>،</p><p>نود تذكيرك بأن لديك <strong>{count}</strong> موظفاً بانتظار إكمال تقييم الأداء الخاص بهم لدورة {year}.</p><p>يرجى التكرم بالدخول للنظام وإنجاز التقييمات في أقرب وقت.</p><p>شكراً لتعاونكم،<br>إدارة الموارد البشرية</p>', '{name}, {count}, {year}');

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `accepted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `employee_evaluations`
--

INSERT INTO `employee_evaluations` (`id`, `employee_id`, `cycle_id`, `evaluator_id`, `evaluator_role`, `status`, `total_score`, `created_at`, `updated_at`, `accepted_at`) VALUES
(1, 3, 1, 2, 'manager', 'approved', 60.00, '2025-10-14 21:40:26', '2025-10-22 22:16:16', '2025-10-23 00:16:16'),
(2, 4, 1, 2, 'manager', 'submitted', 55.00, '2025-10-18 17:54:00', '2025-10-19 21:59:35', NULL),
(3, 3, 1, 5, 'supervisor', 'approved', 87.00, '2025-10-19 19:56:56', '2025-12-12 00:23:28', NULL),
(4, 5, 1, 2, 'manager', 'draft', 20.00, '2025-12-06 21:00:26', '2025-12-06 21:00:26', NULL);

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
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'inactive',
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
  `comments` text DEFAULT NULL COMMENT 'تعليقات اختيارية',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluation_responses`
--

INSERT INTO `evaluation_responses` (`id`, `evaluation_id`, `field_id`, `score`, `comments`, `created_at`) VALUES
(1, 1, 1, 10, NULL, '2025-10-20 21:14:11'),
(3, 1, 2, 50, NULL, '2025-10-20 21:14:11'),
(12, 2, 1, 5, NULL, '2025-10-20 21:14:11'),
(13, 2, 2, 50, NULL, '2025-10-20 21:14:11'),
(24, 3, 1, 7, NULL, '2025-10-20 21:14:11'),
(25, 3, 2, 80, NULL, '2025-10-20 21:14:11'),
(60, 4, 1, 10, NULL, '2025-12-06 21:00:26'),
(61, 4, 2, 10, NULL, '2025-12-06 21:00:26');

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 3, 'تم رفض تقييمك', 'تم رفض تقييمك من قبل  في دورة 2025. يمكنك تعديله وإعادة إرساله.', 'warning', 1, '2025-10-22 21:44:22'),
(2, 3, 'تمت الموافقة على تقييمك', 'تمت الموافقة على تقييمك من قبل  في دورة 2025.', 'success', 0, '2025-10-22 21:57:59'),
(3, 4, 'تمت الموافقة على تقييم موظف', 'تمت الموافقة على تقييم موسى من قبل .', 'info', 0, '2025-10-22 21:57:59'),
(4, 3, 'تمت الموافقة على تقييمك', 'تمت الموافقة على تقييمك من قبل  في دورة 2025.', 'success', 0, '2025-10-22 21:58:03'),
(5, 4, 'تمت الموافقة على تقييم موظف', 'تمت الموافقة على تقييم موسى من قبل .', 'info', 0, '2025-10-22 21:58:03'),
(6, 3, 'تمت الموافقة على تقييمك', 'تمت الموافقة على تقييمك من قبل  في دورة 2025.', 'success', 0, '2025-10-22 21:58:29'),
(7, 4, 'تمت الموافقة على تقييم موظف', 'تمت الموافقة على تقييم موسى من قبل .', 'info', 0, '2025-10-22 21:58:29'),
(8, 3, 'تم رفض تقييمك', 'تم رفض تقييمك من قبل  في دورة 2025. يمكنك تعديله وإعادة إرساله.', 'warning', 0, '2025-10-22 22:04:49'),
(9, 3, 'تم رفض تقييمك', 'تم رفض تقييمك من قبل  في دورة 2025. يمكنك تعديله وإعادة إرساله.', 'warning', 0, '2025-10-22 22:06:19'),
(10, 3, 'تمت الموافقة على تقييمك', 'تمت الموافقة على تقييمك من قبل  في دورة 2025.', 'success', 1, '2025-10-22 22:16:16'),
(11, 1, 'تمت الموافقة على تقييم موظف', 'تمت الموافقة على تقييم موسى من قبل .', 'info', 1, '2025-10-22 22:16:16');

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
(5, 'template_style', 'light'),
(6, 'smtp_host', 'mail.buraq.aero'),
(7, 'smtp_port', '465'),
(8, 'smtp_user', 'hr@buraq.aero'),
(9, 'smtp_pass', 'buraq@1234'),
(10, 'smtp_secure', 'ssl'),
(11, 'smtp_from_email', 'hr@buraq.aero'),
(12, 'smtp_from_name', 'نظام تقييم الأداء'),
(13, 'auto_send_user', '1'),
(14, 'auto_send_eval', '1'),
(15, 'cron_secret_key', 'buraq_secret_123'),
(16, 'evaluation_method', 'manager_only');

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
(1, 'المسؤول الرئيسي', 'alkobat@buraq.aero', '$2y$10$3FJyqBJTJt/Do3QE77wWLOuGpDnyfwOipAXq/E1eZs0MPgBGUGPKO', 'admin', NULL, NULL, NULL, '', NULL, NULL, NULL, 'active', 0, '2025-10-13 20:32:04', '2025-12-15 21:50:17'),
(2, 'مجدي', 'hr.manager@buraq.aero', '$2y$10$IpvZdJxIM5TR17awNKQ2guyXuPUINQWH9bIU/1RJ2tm.aRKhvs8Pm', 'manager', 1, NULL, NULL, 'مدير ادارة الموارد البشرية', NULL, NULL, NULL, 'active', 0, '2025-10-13 21:42:10', '2025-12-15 00:38:14'),
(3, 'موسى', 'mosa@buraq.aero', '$2y$10$ZtMDxEwdPZRQUuRBQBcVzeDWoIwLO7FU.9R8Q2970Njl5PzdHp8s6', 'employee', 1, 2, 5, 'موظف', NULL, NULL, NULL, 'active', 0, '2025-10-13 22:04:46', '2025-12-01 22:29:42'),
(4, 'وصال الهادي العزابي', 'wesal@buraq.aero', '$2y$10$GuMMZ3nvM/tDsnWit4zj0O9CakkP86CNxTLJlxpvT6G4k9wK0T2Tu', 'evaluator', 1, 2, NULL, 'منسق وحدة شئون العاملين', NULL, NULL, NULL, 'active', 0, '2025-10-14 20:28:47', '2025-12-10 22:59:24'),
(5, 'حاتم عياد بن حامد', 'hatem@buraq.aero', '$2y$10$pfUVPUdgxVnWSs5a0eWzZ.AEMs16JmTBHkC08rn1sIu0K2ptn00lO', 'supervisor', 1, 2, NULL, 'موظف', NULL, NULL, NULL, 'active', 0, '2025-10-19 19:55:23', '2025-12-10 23:12:59'),
(10, 'خبيبيطة', 'hr@buraq.aero', '$2y$10$2yc32NfYC64daCXwFi7z.eofJZ9oQo3sedIYLpSno3RnrVf93Pkm.', 'manager', 3, NULL, NULL, '', NULL, NULL, NULL, 'active', 0, '2025-12-14 22:46:57', '2025-12-15 00:47:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_email_type` (`email_type`);

--
-- Indexes for table `email_rate_limits`
--
ALTER TABLE `email_rate_limits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_rate_limit_logs`
--
ALTER TABLE `email_rate_limit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient_time` (`recipient_email`,`attempted_at`),
  ADD KEY `idx_sender_time` (`sender_id`,`attempted_at`);

--
-- Indexes for table `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type` (`type`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_rate_limits`
--
ALTER TABLE `email_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_rate_limit_logs`
--
ALTER TABLE `email_rate_limit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_settings`
--
ALTER TABLE `email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employee_evaluations`
--
ALTER TABLE `employee_evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employee_evaluation_links`
--
ALTER TABLE `employee_evaluation_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `evaluation_custom_text_fields`
--
ALTER TABLE `evaluation_custom_text_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `evaluation_custom_text_responses`
--
ALTER TABLE `evaluation_custom_text_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `evaluation_cycles`
--
ALTER TABLE `evaluation_cycles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evaluation_fields`
--
ALTER TABLE `evaluation_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `strengths_weaknesses`
--
ALTER TABLE `strengths_weaknesses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
