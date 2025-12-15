<?php
/**
 * مشغل الهجرات (Migration Runner)
 * 
 * الاستخدام:
 * php app/run-migrations.php [migrate|rollback]
 */

require_once __DIR__ . '/core/db.php';

$migrationsDir = dirname(__DIR__) . '/migrations';
$command = $argv[1] ?? 'migrate';

echo "========================================\n";
echo "  مشغل الهجرات\n";
echo "========================================\n\n";

if ($command === 'migrate') {
    echo "🔄 تشغيل الهجرات...\n\n";
    
    $files = glob($migrationsDir . '/*.sql');
    sort($files);
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "📋 $filename...\n";
        
        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            echo "   ✓ نجحت\n";
        } catch (Exception $e) {
            echo "   ✗ خطأ: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ اكتملت الهجرات\n";
} else {
    echo "❌ أمر غير معروف: $command\n";
    echo "الأوامر المتاحة: migrate\n";
}

echo "\n========================================\n";
?>
