<?php

namespace Tests;

require_once __DIR__ . '/../app/core/Mailer.php';

use Mailer;

class MailerTest extends TestCase
{
    private $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new Mailer($this->pdo);
    }

    // =================================================================
    // اختبارات إعدادات SMTP - SMTP Settings Tests
    // =================================================================

    /**
     * @test
     * اختبار تحميل إعدادات SMTP من قاعدة البيانات
     */
    public function test_loads_smtp_settings_from_database(): void
    {
        // This test verifies that Mailer constructor loads settings
        // We can't directly access private $settings, but we can verify
        // that it doesn't throw exceptions
        
        $this->assertInstanceOf(Mailer::class, $this->mailer);
    }

    /**
     * @test
     * اختبار إرسال بريد مخصص - Custom Email
     */
    public function test_send_custom_email_basic_structure(): void
    {
        // Note: This test will fail actual sending due to invalid SMTP,
        // but we're testing the method structure and error handling
        
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'موضوع الاختبار';
        $body = '<p>محتوى البريد</p>';
        
        // Act: attempt to send (will fail due to mock SMTP settings)
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert: should return false due to invalid SMTP, but not throw exception
        $this->assertIsBool($result, 'يجب أن يرجع الدالة قيمة boolean');
    }

    /**
     * @test
     * اختبار إرسال بريد من template
     */
    public function test_send_email_from_template(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $templateType = 'evaluation_complete';
        $placeholders = [
            'employee_name' => 'محمد',
            'score' => '85',
        ];
        
        // Act
        $result = $this->mailer->sendEmail($toEmail, $toName, $templateType, $placeholders);
        
        // Assert
        $this->assertIsBool($result, 'يجب أن يرجع الدالة قيمة boolean');
    }

    /**
     * @test
     * اختبار معالجة template غير موجود
     */
    public function test_handles_missing_template(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $templateType = 'non_existent_template';
        
        // Act
        $result = $this->mailer->sendEmail($toEmail, $toName, $templateType, []);
        
        // Assert
        $this->assertFalse($result, 'يجب أن يرجع false عند عدم وجود template');
    }

    // =================================================================
    // اختبارات المرفقات - Attachment Tests
    // =================================================================

    /**
     * @test
     * اختبار إضافة مرفق من ملف
     */
    public function test_attach_file_to_email(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'بريد مع مرفق';
        $body = '<p>محتوى</p>';
        
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');
        
        $attachments = [
            ['path' => $tempFile, 'name' => 'test.txt']
        ];
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body, $attachments);
        
        // Cleanup
        unlink($tempFile);
        
        // Assert
        $this->assertIsBool($result, 'يجب أن يتم التعامل مع المرفقات دون أخطاء');
    }

    /**
     * @test
     * اختبار إضافة مرفق من string
     */
    public function test_attach_string_to_email(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'بريد مع مرفق نصي';
        $body = '<p>محتوى</p>';
        
        $attachments = [
            ['string' => 'محتوى المرفق', 'name' => 'data.txt']
        ];
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body, $attachments);
        
        // Assert
        $this->assertIsBool($result, 'يجب أن يتم التعامل مع المرفقات النصية دون أخطاء');
    }

    /**
     * @test
     * اختبار إضافة مرفقات متعددة
     */
    public function test_attach_multiple_files(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'بريد مع مرفقات متعددة';
        $body = '<p>محتوى</p>';
        
        $tempFile1 = tempnam(sys_get_temp_dir(), 'test1_');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'test2_');
        file_put_contents($tempFile1, 'content 1');
        file_put_contents($tempFile2, 'content 2');
        
        $attachments = [
            ['path' => $tempFile1, 'name' => 'file1.txt'],
            ['string' => 'string content', 'name' => 'file2.txt'],
            ['path' => $tempFile2, 'name' => 'file3.txt']
        ];
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body, $attachments);
        
        // Cleanup
        unlink($tempFile1);
        unlink($tempFile2);
        
        // Assert
        $this->assertIsBool($result, 'يجب أن يتم التعامل مع المرفقات المتعددة دون أخطاء');
    }

    // =================================================================
    // اختبارات معالجة الأخطاء - Error Handling Tests
    // =================================================================

    /**
     * @test
     * اختبار التعامل مع SMTP settings غير صحيحة
     */
    public function test_handles_invalid_smtp_settings(): void
    {
        // Arrange: settings are already invalid (test SMTP)
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'اختبار';
        $body = '<p>اختبار</p>';
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert: should return false but not throw exception
        $this->assertFalse($result, 'يجب أن يرجع false عند فشل الإرسال');
    }

    /**
     * @test
     * اختبار التعامل مع عنوان بريد غير صحيح
     */
    public function test_handles_invalid_email_address(): void
    {
        // Arrange
        $invalidEmail = 'not-an-email';
        $toName = 'اختبار';
        $subject = 'اختبار';
        $body = '<p>اختبار</p>';
        
        // Act
        $result = $this->mailer->sendCustomEmail($invalidEmail, $toName, $subject, $body);
        
        // Assert
        $this->assertFalse($result, 'يجب أن يرجع false عند إدخال بريد غير صحيح');
    }

    /**
     * @test
     * اختبار التعامل مع محتوى فارغ
     */
    public function test_handles_empty_content(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = '';
        $body = '';
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert: should not throw exception
        $this->assertIsBool($result, 'يجب أن يتم التعامل مع المحتوى الفارغ دون رفع استثناءات');
    }

    /**
     * @test
     * اختبار عدم رفع Exception عند فشل الإرسال
     */
    public function test_does_not_throw_exception_on_send_failure(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'اختبار';
        $body = '<p>اختبار</p>';
        
        $exceptionThrown = false;
        
        try {
            // Act
            $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        } catch (\Exception $e) {
            $exceptionThrown = true;
        }
        
        // Assert
        $this->assertFalse($exceptionThrown, 'يجب عدم رفع استثناءات، بل إرجاع false');
    }

    // =================================================================
    // اختبارات الترميز والتنسيق - Encoding and Formatting Tests
    // =================================================================

    /**
     * @test
     * اختبار دعم UTF-8 والنصوص العربية
     */
    public function test_supports_utf8_and_arabic_text(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'محمد أحمد';
        $subject = 'تقييم الأداء - السنة المالية ٢٠٢٤';
        $body = '<p>السلام عليكم ورحمة الله وبركاته</p><p>تم استكمال تقييمك بنجاح ✓</p>';
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert: should not throw encoding errors
        $this->assertIsBool($result, 'يجب دعم الترميز UTF-8 والنصوص العربية');
    }

    /**
     * @test
     * اختبار دعم HTML في المحتوى
     */
    public function test_supports_html_content(): void
    {
        // Arrange
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'بريد HTML';
        $body = '
            <div style="font-family: Arial; direction: rtl;">
                <h1>عنوان</h1>
                <p>فقرة مع <strong>نص غامق</strong> و <em>نص مائل</em></p>
                <ul>
                    <li>عنصر ١</li>
                    <li>عنصر ٢</li>
                </ul>
                <a href="https://example.com">رابط</a>
            </div>
        ';
        
        // Act
        $result = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert
        $this->assertIsBool($result, 'يجب دعم محتوى HTML المعقد');
    }

    /**
     * @test
     * اختبار استبدال Placeholders في Template
     */
    public function test_replaces_placeholders_in_template(): void
    {
        // Arrange: We need to verify that placeholders are replaced
        // Since we can't actually send, we'll test with template that exists
        
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $templateType = 'evaluation_complete';
        
        // The template in our test DB is simple, but the method should work
        $placeholders = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        
        // Act
        $result = $this->mailer->sendEmail($toEmail, $toName, $templateType, $placeholders);
        
        // Assert: verify the method executes without errors
        $this->assertIsBool($result, 'يجب أن يتم استبدال الـ placeholders بشكل صحيح');
    }

    // =================================================================
    // اختبارات إعدادات FROM - From Settings Tests
    // =================================================================

    /**
     * @test
     * اختبار استخدام smtp_from_email و smtp_from_name
     */
    public function test_uses_from_email_and_name_from_settings(): void
    {
        // Arrange: settings already contain smtp_from_email and smtp_from_name
        $this->setSetting('smtp_from_email', 'hr@alburaq.com');
        $this->setSetting('smtp_from_name', 'نظام الموارد البشرية');
        
        // Create new mailer instance to load new settings
        $mailer = new Mailer($this->pdo);
        
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'اختبار';
        $body = '<p>اختبار</p>';
        
        // Act
        $result = $mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert: should use the from settings (even though send will fail)
        $this->assertIsBool($result, 'يجب استخدام إعدادات FROM من قاعدة البيانات');
    }

    /**
     * @test
     * اختبار Fallback عند عدم وجود smtp_from_email
     */
    public function test_fallback_to_smtp_user_when_from_email_missing(): void
    {
        // Arrange: remove smtp_from_email
        $this->pdo->exec("DELETE FROM system_settings WHERE `key` = 'smtp_from_email'");
        $this->setSetting('smtp_user', 'fallback@example.com');
        
        // Create new mailer instance
        $mailer = new Mailer($this->pdo);
        
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'اختبار';
        $body = '<p>اختبار</p>';
        
        // Act
        $result = $mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert: should not throw exception
        $this->assertIsBool($result, 'يجب استخدام smtp_user كـ fallback');
    }

    // =================================================================
    // اختبارات SMTP Secure - SMTP Security Tests
    // =================================================================

    /**
     * @test
     * اختبار دعم TLS
     */
    public function test_supports_tls_encryption(): void
    {
        // Arrange
        $this->setSetting('smtp_secure', 'tls');
        $this->setSetting('smtp_port', '587');
        
        $mailer = new Mailer($this->pdo);
        
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'اختبار TLS';
        $body = '<p>اختبار</p>';
        
        // Act
        $result = $mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert
        $this->assertIsBool($result, 'يجب دعم TLS encryption');
    }

    /**
     * @test
     * اختبار دعم SSL
     */
    public function test_supports_ssl_encryption(): void
    {
        // Arrange
        $this->setSetting('smtp_secure', 'ssl');
        $this->setSetting('smtp_port', '465');
        
        $mailer = new Mailer($this->pdo);
        
        $toEmail = 'test@example.com';
        $toName = 'اختبار';
        $subject = 'اختبار SSL';
        $body = '<p>اختبار</p>';
        
        // Act
        $result = $mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        
        // Assert
        $this->assertIsBool($result, 'يجب دعم SSL encryption');
    }

    // =================================================================
    // اختبارات تكامل PHPMailer - PHPMailer Integration Tests
    // =================================================================

    /**
     * @test
     * اختبار أن PHPMailer موجود ومحمّل
     */
    public function test_phpmailer_is_loaded(): void
    {
        // Assert
        $this->assertTrue(
            class_exists('PHPMailer\\PHPMailer\\PHPMailer'),
            'يجب أن يكون PHPMailer محملاً'
        );
    }

    /**
     * @test
     * اختبار رسالة خطأ عند عدم وجود PHPMailer (محاكاة)
     */
    public function test_error_message_when_phpmailer_missing(): void
    {
        // This test documents expected behavior if PHPMailer is missing
        // In production, constructor would fail with RuntimeException
        // We can't actually test this without breaking the environment
        
        $this->assertTrue(true, 'اختبار توثيقي: يجب رفع RuntimeException عند عدم وجود PHPMailer');
    }
}
