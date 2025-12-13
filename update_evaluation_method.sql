-- إضافة إعداد طريقة احتساب التقييم إلى جدول system_settings
INSERT INTO system_settings (`key`, value) 
VALUES ('evaluation_method', 'manager_only')
ON DUPLICATE KEY UPDATE value = value;
