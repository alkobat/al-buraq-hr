<?php
/**
 * سكريبت إعداد مفتاح التشفير
 * يجب تشغيل هذا السكريبت مرة واحدة عند تفعيل نظام التشفير
 * 
 * الاستخدام:
 * php app/setup-encryption.php
 */

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/SecurityManager.php';

$envFile = dirname(__DIR__, 1) . '/.env';
$envExampleFile = dirname(__DIR__, 1) . '/.env.example';

echo "========================================\n";
echo "  إعداد مفتاح التشفير\n";
echo "========================================\n\n";

// توليد مفتاح تشفير عشوائي
$encryptionKey = bin2hex(random_bytes(32));

echo "✓ تم توليد مفتاح التشفير بنجاح\n\n";

// إنشاء أو تحديث ملف .env
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (strpos($envContent, 'ENCRYPTION_KEY') !== false) {
        $envContent = preg_replace('/ENCRYPTION_KEY=.*/i', 'ENCRYPTION_KEY=' . $encryptionKey, $envContent);
    } else {
        $envContent .= "\nENCRYPTION_KEY=" . $encryptionKey;
    }
    file_put_contents($envFile, $envContent);
    echo "✓ تم تحديث ملف .env\n";
} else {
    $envContent = "# مفتاح التشفير (AES-256)\nENCRYPTION_KEY=" . $encryptionKey . "\n";
    file_put_contents($envFile, $envContent);
    echo "✓ تم إنشاء ملف .env\n";
}

// إنشاء ملف .env.example
if (!file_exists($envExampleFile)) {
    $example = "# مفتاح التشفير (AES-256) - يجب توليده باستخدام: php app/setup-encryption.php\nENCRYPTION_KEY=your_64_character_hex_string_here\n";
    file_put_contents($envExampleFile, $example);
    echo "✓ تم إنشاء ملف .env.example\n";
}

echo "\n✓ تم إعداد التشفير بنجاح!\n";
echo "⚠️  احفظ مفتاح التشفير في مكان آمن:\n";
echo "    ENCRYPTION_KEY=" . $encryptionKey . "\n\n";

// اختياري: تشفير كلمة المرور القديمة
echo "هل تريد تشفير كلمة المرور الحالية SMTP؟ (y/n): ";
$response = trim(fgets(STDIN));

if (strtolower($response) === 'y') {
    try {
        $stmt = $pdo->prepare("SELECT id, `key`, `value` FROM system_settings WHERE `key` = 'smtp_pass'");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($setting && !empty($setting['value'])) {
            $encryptedValue = SecurityManager::encrypt($setting['value']);
            
            $updateStmt = $pdo->prepare("UPDATE system_settings SET `value` = ?, `is_encrypted` = 1 WHERE `key` = 'smtp_pass'");
            $updateStmt->execute([$encryptedValue]);

            echo "✓ تم تشفير كلمة المرور بنجاح!\n";
        } else {
            echo "⚠️  كلمة المرور غير موجودة\n";
        }
    } catch (Exception $e) {
        echo "✗ خطأ: " . $e->getMessage() . "\n";
    }
}

echo "\n========================================\n";
echo "✓ تم إكمال الإعداد\n";
echo "========================================\n";
?>
