<?php
// قراءة بيانات الاتصال من متغيرات البيئة أو استخدام القيم الافتراضية
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'al_b';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // (جديد) تحميل إعدادات النظام العامة لتكون متاحة في التطبيق
    // نستخدم `key` لأنها اسم العمود في جدولك
    $settings_stmt = $pdo->query("SELECT `key`, `value` FROM system_settings");
    $system_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>