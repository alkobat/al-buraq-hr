<?php
/**
 * مشغل الميجريشنز المباشر
 * للاتصال بقاعدة البيانات وتطبيق التغييرات
 */

echo "🔧 بدء عملية إنشاء جداول قاعدة البيانات...\n\n";

// إعدادات قاعدة البيانات
$host = 'localhost';
$dbname = 'al_b';
$username = 'mysql';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "✅ تم الاتصال بقاعدة البيانات بنجاح\n\n";
    
    // قراءة ملفات الميجريشنز
    $migrationsDir = __DIR__ . '/../migrations';
    $files = glob($migrationsDir . '/*.sql');
    sort($files);
    
    echo "📋 الملفات المتاحة:\n";
    foreach ($files as $file) {
        echo "   - " . basename($file) . "\n";
    }
    echo "\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "🔄 تطبيق $filename...\n";
        
        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            echo "   ✅ تم التطبيق بنجاح\n";
        } catch (Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    // التحقق من الجداول المنشأة
    echo "🔍 التحقق من الجداول المنشأة:\n";
    $tables = $pdo->query("SHOW TABLES LIKE 'email%'")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "   ❌ لم يتم العثور على جداول email\n";
    } else {
        foreach ($tables as $table) {
            echo "   ✅ $table\n";
        }
    }
    
    echo "\n🎉 انتهت عملية إنشاء الجداول!\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ في الاتصال: " . $e->getMessage() . "\n";
    exit(1);
}
?>