-- Al-Buraq HR System Backup
-- Date: 2025-12-10 22:36:03

SET FOREIGN_KEY_CHECKS=0;



CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'معرف المستخدم الذي قام بالعملية',
  `user_name` varchar(150) DEFAULT NULL COMMENT 'الاسم وقت التنفيذ (للحفظ حتى لو حذف المستخدم)',
  `role` varchar(50) DEFAULT NULL COMMENT 'دور المستخدم',
  `action` varchar(100) NOT NULL COMMENT 'نوع العملية: login, create, update, delete, logout',
  `description` text DEFAULT NULL COMMENT 'وصف تفصيلي: مثلا "قام بتعديل الموظف رقم 5"',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'عنوان IP للمستخدم',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO activity_logs VALUES("1","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-09 23:50:34");
INSERT INTO activity_logs VALUES("2","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-09 23:51:06");
INSERT INTO activity_logs VALUES("3","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-09 23:51:13");
INSERT INTO activity_logs VALUES("4","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-09 23:57:17");
INSERT INTO activity_logs VALUES("5","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 00:11:42");
INSERT INTO activity_logs VALUES("6","4","وصال الهادي العزابي","evaluator","update","تم تعديل بيانات المستخدم رقم: 6","::1","2025-12-10 00:12:09");
INSERT INTO activity_logs VALUES("7","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 00:12:30");
INSERT INTO activity_logs VALUES("8","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 00:14:29");
INSERT INTO activity_logs VALUES("9","4","وصال الهادي العزابي","evaluator","create","تمت إضافة مستخدم جديد: yjjukyukuy (yjtukuu@tggtrh.gthtr)","::1","2025-12-10 00:14:56");
INSERT INTO activity_logs VALUES("10","4","وصال الهادي العزابي","evaluator","delete","تم حذف بيانات المستخدم رقم: ","::1","2025-12-10 00:15:14");
INSERT INTO activity_logs VALUES("11","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 00:15:23");
INSERT INTO activity_logs VALUES("12","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 00:24:06");
INSERT INTO activity_logs VALUES("13","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 00:24:16");
INSERT INTO activity_logs VALUES("14","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 21:29:24");
INSERT INTO activity_logs VALUES("15","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 21:32:55");
INSERT INTO activity_logs VALUES("16","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 21:33:10");
INSERT INTO activity_logs VALUES("17","4","وصال الهادي العزابي","evaluator","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 21:43:46");
INSERT INTO activity_logs VALUES("18","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 21:44:45");
INSERT INTO activity_logs VALUES("19","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 21:49:17");
INSERT INTO activity_logs VALUES("20","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 21:56:45");
INSERT INTO activity_logs VALUES("21","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 21:57:30");
INSERT INTO activity_logs VALUES("22","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 21:57:40");
INSERT INTO activity_logs VALUES("23","4","وصال الهادي العزابي","evaluator","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:09:44");
INSERT INTO activity_logs VALUES("24","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:09:52");
INSERT INTO activity_logs VALUES("25","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:26:34");
INSERT INTO activity_logs VALUES("26","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:26:46");
INSERT INTO activity_logs VALUES("27","4","وصال الهادي العزابي","evaluator","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:28:47");
INSERT INTO activity_logs VALUES("28","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:28:55");
INSERT INTO activity_logs VALUES("29","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:46:39");
INSERT INTO activity_logs VALUES("30","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:46:52");
INSERT INTO activity_logs VALUES("31","4","وصال الهادي العزابي","evaluator","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:53:05");
INSERT INTO activity_logs VALUES("32","2","مجدي","manager","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:53:12");
INSERT INTO activity_logs VALUES("33","2","مجدي","manager","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:57:06");
INSERT INTO activity_logs VALUES("34","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:57:17");
INSERT INTO activity_logs VALUES("35","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 22:59:17");
INSERT INTO activity_logs VALUES("36","4","وصال الهادي العزابي","evaluator","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 22:59:24");
INSERT INTO activity_logs VALUES("37","4","وصال الهادي العزابي","evaluator","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 23:00:14");
INSERT INTO activity_logs VALUES("38","5","حاتم عياد بن حامد","supervisor","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 23:00:23");
INSERT INTO activity_logs VALUES("39","5","حاتم عياد بن حامد","supervisor","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 23:05:35");
INSERT INTO activity_logs VALUES("40","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 23:05:42");
INSERT INTO activity_logs VALUES("41","1","المسؤول الرئيسي","admin","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 23:08:44");
INSERT INTO activity_logs VALUES("42","2","مجدي","manager","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 23:08:56");
INSERT INTO activity_logs VALUES("43","2","مجدي","manager","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 23:12:50");
INSERT INTO activity_logs VALUES("44","5","حاتم عياد بن حامد","supervisor","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 23:12:59");
INSERT INTO activity_logs VALUES("45","5","حاتم عياد بن حامد","supervisor","logout","قام بتسجيل الخروج من النظام","::1","2025-12-10 23:33:50");
INSERT INTO activity_logs VALUES("46","1","المسؤول الرئيسي","admin","login","قام بتسجيل الدخول للنظام","::1","2025-12-10 23:34:12");


CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(150) NOT NULL COMMENT 'اسم الإدارة بالعربية',
  `name_en` varchar(150) DEFAULT NULL COMMENT 'اسم الإدارة بالإنجليزية',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departments VALUES("1","الموارد البشرية","","active","2025-10-13 22:52:58");
INSERT INTO departments VALUES("2","العامة","","active","2025-10-14 00:41:08");
INSERT INTO departments VALUES("3","المالية","","active","2025-10-14 00:41:12");
INSERT INTO departments VALUES("4","الفنية","","active","2025-10-14 00:41:17");
INSERT INTO departments VALUES("5","العمليات الجوية","","active","2025-10-14 00:41:23");
INSERT INTO departments VALUES("6","العمليات الأرضية","","active","2025-10-14 00:41:28");
INSERT INTO departments VALUES("7","مكتب بنغازي","","active","2025-10-14 00:41:33");
INSERT INTO departments VALUES("8","التطوير والتخطيط","","active","2025-10-14 00:42:22");
INSERT INTO departments VALUES("9","الصلاحية الجوية (CAMO)","","active","2025-10-14 00:42:46");


CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT 'نوع القالب: new_user, evaluation_link, announcement',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `placeholders` text DEFAULT NULL COMMENT 'وصف المتغيرات المتاحة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO email_templates VALUES("1","new_user","بيانات الدخول للنظام","<p>مرحباً {name}،</p><p>تم إنشاء حساب لك في نظام الموارد البشرية.</p><p><strong>البريد الإلكتروني:</strong> {email}<br><strong>كلمة المرور:</strong> {password}</p><p>يرجى تسجيل الدخول وتغيير كلمة المرور.</p>","{name}, {email}, {password}");
INSERT INTO email_templates VALUES("2","evaluation_link","رابط تقييم الأداء السنوي","<p>مرحباً {name}،</p><p>قام مديرك المباشر برفع تقييم الأداء الخاص بك.</p><p>يرجى الاطلاع عليه والموافقة أو الرفض عبر الرابط التالي:</p><p><a href=\"{link}\">{link}</a></p>","{name}, {link}");
INSERT INTO email_templates VALUES("3","announcement","إعلان إداري","<p>مرحباً {name}،</p><p>{message}</p>","{name}, {message}");


CREATE TABLE `employee_evaluation_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `cycle_id` int(10) unsigned NOT NULL,
  `unique_token` varchar(36) NOT NULL COMMENT 'UUID فريد',
  `expires_at` datetime DEFAULT NULL COMMENT 'تاريخ انتهاء الصلاحية (اختياري)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`unique_token`),
  KEY `employee_id` (`employee_id`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `employee_evaluation_links_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_evaluation_links_ibfk_2` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO employee_evaluation_links VALUES("1","3","1","984700bf6027bcbc0acc4a31c134981b","","2025-10-19 23:37:05");
INSERT INTO employee_evaluation_links VALUES("2","4","1","b65dc38520a3082c49cfc4f0afadb8c6","","2025-10-19 23:40:28");


CREATE TABLE `employee_evaluations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL COMMENT 'الموظف المُقيَّم',
  `cycle_id` int(10) unsigned NOT NULL,
  `evaluator_id` int(10) unsigned NOT NULL COMMENT 'المُقيّم (مدير أو رئيس مباشر)',
  `evaluator_role` enum('manager','supervisor') NOT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `total_score` decimal(5,2) DEFAULT NULL COMMENT 'المجموع النهائي (0-100)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_eval` (`employee_id`,`cycle_id`,`evaluator_role`),
  KEY `evaluator_id` (`evaluator_id`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `employee_evaluations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_evaluations_ibfk_2` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_evaluations_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO employee_evaluations VALUES("1","3","1","2","manager","approved","60.00","2025-10-14 23:40:26","2025-10-23 00:16:16","2025-10-23 00:16:16");
INSERT INTO employee_evaluations VALUES("2","4","1","2","manager","submitted","55.00","2025-10-18 19:54:00","2025-10-19 23:59:35","");
INSERT INTO employee_evaluations VALUES("3","3","1","5","supervisor","submitted","87.00","2025-10-19 21:56:56","2025-10-19 23:38:39","");
INSERT INTO employee_evaluations VALUES("4","5","1","2","manager","draft","20.00","2025-12-06 23:00:26","2025-12-06 23:00:26","");


CREATE TABLE `evaluation_custom_text_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` int(10) unsigned NOT NULL COMMENT 'دورة التقييم',
  `title_ar` varchar(200) NOT NULL COMMENT 'عنوان الحقل بالعربية',
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'هل الحقل إلزامي؟',
  `order_index` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `evaluation_custom_text_fields_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO evaluation_custom_text_fields VALUES("1","1","الدورات التدريبية التي يحتاجها","0","0");


CREATE TABLE `evaluation_custom_text_responses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(10) unsigned NOT NULL,
  `field_id` int(10) unsigned NOT NULL,
  `response_text` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_custom_response` (`evaluation_id`,`field_id`),
  KEY `field_id` (`field_id`),
  CONSTRAINT `evaluation_custom_text_responses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_custom_text_responses_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `evaluation_custom_text_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO evaluation_custom_text_responses VALUES("1","2","1","78527828");
INSERT INTO evaluation_custom_text_responses VALUES("3","3","1","تانعهم");
INSERT INTO evaluation_custom_text_responses VALUES("7","1","1","كل شي");


CREATE TABLE `evaluation_cycles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `year` year(4) NOT NULL COMMENT 'سنة التقييم',
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'inactive',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `year` (`year`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO evaluation_cycles VALUES("1","2025","active","","","2025-10-13 22:53:19");


CREATE TABLE `evaluation_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` int(10) unsigned NOT NULL COMMENT 'دورة التقييم',
  `title_ar` varchar(200) NOT NULL COMMENT 'عنوان المجال بالعربية',
  `title_en` varchar(200) DEFAULT NULL,
  `max_score` tinyint(3) unsigned NOT NULL DEFAULT 20 COMMENT 'الدرجة القصوى (يجب أن يجمع المجموع 100)',
  `is_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'هل الحقل إلزامي؟',
  `order_index` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض',
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `evaluation_fields_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO evaluation_fields VALUES("1","1","المظهر والقيافة","","10","1","0");
INSERT INTO evaluation_fields VALUES("2","1","العمل","","90","1","0");


CREATE TABLE `evaluation_responses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(10) unsigned NOT NULL,
  `field_id` int(10) unsigned NOT NULL,
  `score` tinyint(3) unsigned NOT NULL DEFAULT 0 CHECK (`score` >= 0),
  `comments` text DEFAULT NULL COMMENT 'تعليقات اختيارية',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_response` (`evaluation_id`,`field_id`),
  KEY `field_id` (`field_id`),
  CONSTRAINT `evaluation_responses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_responses_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `evaluation_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO evaluation_responses VALUES("1","1","1","10","","2025-10-20 23:14:11");
INSERT INTO evaluation_responses VALUES("3","1","2","50","","2025-10-20 23:14:11");
INSERT INTO evaluation_responses VALUES("12","2","1","5","","2025-10-20 23:14:11");
INSERT INTO evaluation_responses VALUES("13","2","2","50","","2025-10-20 23:14:11");
INSERT INTO evaluation_responses VALUES("24","3","1","7","","2025-10-20 23:14:11");
INSERT INTO evaluation_responses VALUES("25","3","2","80","","2025-10-20 23:14:11");
INSERT INTO evaluation_responses VALUES("60","4","1","10","","2025-12-06 23:00:26");
INSERT INTO evaluation_responses VALUES("61","4","2","10","","2025-12-06 23:00:26");


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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO notifications VALUES("1","3","تم رفض تقييمك","تم رفض تقييمك من قبل  في دورة 2025. يمكنك تعديله وإعادة إرساله.","warning","1","2025-10-22 23:44:22");
INSERT INTO notifications VALUES("2","3","تمت الموافقة على تقييمك","تمت الموافقة على تقييمك من قبل  في دورة 2025.","success","0","2025-10-22 23:57:59");
INSERT INTO notifications VALUES("3","4","تمت الموافقة على تقييم موظف","تمت الموافقة على تقييم موسى من قبل .","info","0","2025-10-22 23:57:59");
INSERT INTO notifications VALUES("4","3","تمت الموافقة على تقييمك","تمت الموافقة على تقييمك من قبل  في دورة 2025.","success","0","2025-10-22 23:58:03");
INSERT INTO notifications VALUES("5","4","تمت الموافقة على تقييم موظف","تمت الموافقة على تقييم موسى من قبل .","info","0","2025-10-22 23:58:03");
INSERT INTO notifications VALUES("6","3","تمت الموافقة على تقييمك","تمت الموافقة على تقييمك من قبل  في دورة 2025.","success","0","2025-10-22 23:58:29");
INSERT INTO notifications VALUES("7","4","تمت الموافقة على تقييم موظف","تمت الموافقة على تقييم موسى من قبل .","info","0","2025-10-22 23:58:29");
INSERT INTO notifications VALUES("8","3","تم رفض تقييمك","تم رفض تقييمك من قبل  في دورة 2025. يمكنك تعديله وإعادة إرساله.","warning","0","2025-10-23 00:04:49");
INSERT INTO notifications VALUES("9","3","تم رفض تقييمك","تم رفض تقييمك من قبل  في دورة 2025. يمكنك تعديله وإعادة إرساله.","warning","0","2025-10-23 00:06:19");
INSERT INTO notifications VALUES("10","3","تمت الموافقة على تقييمك","تمت الموافقة على تقييمك من قبل  في دورة 2025.","success","1","2025-10-23 00:16:16");
INSERT INTO notifications VALUES("11","1","تمت الموافقة على تقييم موظف","تمت الموافقة على تقييم موسى من قبل .","info","1","2025-10-23 00:16:16");


CREATE TABLE `strengths_weaknesses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(10) unsigned NOT NULL,
  `type` enum('strength','weakness') NOT NULL COMMENT 'نوع الملاحظة',
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `evaluation_id` (`evaluation_id`),
  CONSTRAINT `strengths_weaknesses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `employee_evaluations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings VALUES("1","company_name","شركة البراق للنقل الجوي");
INSERT INTO system_settings VALUES("2","primary_color","#0d6efd");
INSERT INTO system_settings VALUES("3","secondary_color","#6c757d");
INSERT INTO system_settings VALUES("4","logo_path","logo.png");
INSERT INTO system_settings VALUES("5","template_style","light");
INSERT INTO system_settings VALUES("6","smtp_host","mail.buraq.aero");
INSERT INTO system_settings VALUES("7","smtp_port","465");
INSERT INTO system_settings VALUES("8","smtp_user","hr@buraq.aero");
INSERT INTO system_settings VALUES("9","smtp_pass","buraq@1234");
INSERT INTO system_settings VALUES("10","smtp_secure","ssl");
INSERT INTO system_settings VALUES("11","smtp_from_email","hr@buraq.aero");
INSERT INTO system_settings VALUES("12","smtp_from_name","نظام تقييم الأداء");
INSERT INTO system_settings VALUES("13","auto_send_user","");
INSERT INTO system_settings VALUES("14","auto_send_eval","");


CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'الاسم الكامل',
  `email` varchar(100) NOT NULL COMMENT 'البريد الإلكتروني',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور المشفرة',
  `role` enum('admin','manager','supervisor','employee','evaluator') NOT NULL COMMENT 'الدور',
  `department_id` int(10) unsigned DEFAULT NULL COMMENT 'الإدارة',
  `manager_id` int(10) unsigned DEFAULT NULL COMMENT 'مدير الإدارة (ID)',
  `supervisor_id` int(10) unsigned DEFAULT NULL COMMENT 'الرئيس المباشر (ID)',
  `job_title` varchar(100) DEFAULT NULL COMMENT 'الوظيفة',
  `birth_date` date DEFAULT NULL COMMENT 'تاريخ الميلاد',
  `marital_status` varchar(20) DEFAULT NULL COMMENT 'الحالة الاجتماعية',
  `gender` enum('ذكر','أنثى') DEFAULT NULL COMMENT 'النوع',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'الحالة',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'إجبار تغيير كلمة المرور عند أول دخول',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  KEY `manager_id` (`manager_id`),
  KEY `supervisor_id` (`supervisor_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users VALUES("1","المسؤول الرئيسي","alkobat@buraq.aero","$2y$10$3FJyqBJTJt/Do3QE77wWLOuGpDnyfwOipAXq/E1eZs0MPgBGUGPKO","admin","","","","","","","","active","0","2025-10-13 22:32:04","2025-12-10 23:34:12");
INSERT INTO users VALUES("2","مجدي","hr.manager@buraq.aero","$2y$10$IpvZdJxIM5TR17awNKQ2guyXuPUINQWH9bIU/1RJ2tm.aRKhvs8Pm","manager","1","","","مدير ادارة الموارد البشرية","","","","active","0","2025-10-13 23:42:10","2025-12-10 23:08:56");
INSERT INTO users VALUES("3","موسى","mosa@buraq.aero","$2y$10$ZtMDxEwdPZRQUuRBQBcVzeDWoIwLO7FU.9R8Q2970Njl5PzdHp8s6","employee","1","2","5","موظف","","","","active","0","2025-10-14 00:04:46","2025-12-01 22:29:42");
INSERT INTO users VALUES("4","وصال الهادي العزابي","wesal@buraq.aero","$2y$10$GuMMZ3nvM/tDsnWit4zj0O9CakkP86CNxTLJlxpvT6G4k9wK0T2Tu","evaluator","1","2","","منسق وحدة شئون العاملين","","","","active","0","2025-10-14 22:28:47","2025-12-10 22:59:24");
INSERT INTO users VALUES("5","حاتم عياد بن حامد","hatem@buraq.aero","$2y$10$pfUVPUdgxVnWSs5a0eWzZ.AEMs16JmTBHkC08rn1sIu0K2ptn00lO","supervisor","1","2","","موظف","","","","active","0","2025-10-19 21:55:23","2025-12-10 23:12:59");
INSERT INTO users VALUES("6","خبيبيطة","alkobat@gmail.com","$2y$10$h3ZqYBV6eeKR5zfBZlHsne7YynvN/qh0ohNubDxXE74mbEZnOmkPq","employee","3","","","رئيس قسم الموارد البشرية","","","","active","1","2025-12-09 21:55:33","");

SET FOREIGN_KEY_CHECKS=1;