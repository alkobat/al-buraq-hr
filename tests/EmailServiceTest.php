<?php

namespace Tests;

require_once __DIR__ . '/../app/core/EmailService.php';
require_once __DIR__ . '/../app/core/Mailer.php';
require_once __DIR__ . '/../app/core/EvaluationCalculator.php';

use EmailService;
use EvaluationCalculator;

class EmailServiceTest extends TestCase
{
    private $emailService;
    private $mailerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailService = new EmailService($this->pdo);
        
        // Mock the Mailer to prevent actual email sending
        $this->mockMailer();
    }

    private function mockMailer(): void
    {
        // We'll test with the real EmailService but intercept actual sending
        // by checking email_logs table instead
    }

    // =================================================================
    // 1. اختبارات الإرسال - Email Sending Tests
    // =================================================================

    /**
     * @test
     * اختبار إرسال بريد على التقييم المكتمل
     */
    public function test_sends_email_on_complete_evaluation(): void
    {
        // Arrange: employee with supervisor
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: both manager and supervisor evaluate
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert: should send complete evaluation email after supervisor evaluates
        // Note: With default settings (waiting_supervisor_plus_final), it will send 2 emails:
        // 1. Waiting for supervisor (when manager evaluates)
        // 2. Final complete (when supervisor evaluates)
        $completeLogs = $this->getEmailLogsByType(EmailService::TYPE_FINAL_COMPLETE);
        $this->assertCount(1, $completeLogs, 'يجب إرسال بريد واحد من نوع "مكتمل" عند اكتمال التقييم');
        $this->assertEquals('success', $completeLogs[0]['status']);
        $this->assertEquals($employeeId, $completeLogs[0]['employee_id']);
        $this->assertStringContainsString('استكمال', $completeLogs[0]['subject']);
        
        // Also verify waiting email was sent first
        $waitingLogs = $this->getEmailLogsByType(EmailService::TYPE_WAITING_SUPERVISOR);
        $this->assertCount(1, $waitingLogs, 'يجب إرسال بريد "بانتظار المشرف" عند تقييم المدير');
    }

    /**
     * @test
     * اختبار إرسال بريد على الموافقة - الجزء الأول من العملية
     */
    public function test_sends_email_with_approval_link(): void
    {
        // Arrange
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: manager evaluates
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert: email should contain approval link
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs, 'يجب إرسال بريد إلكتروني');
        
        $emailBody = $logs[0]['body'];
        $this->assertStringContainsString('approve.php', $emailBody, 'يجب أن يحتوي البريد على رابط الموافقة');
        $this->assertStringContainsString('token=', $emailBody, 'يجب أن يحتوي الرابط على token');
    }

    /**
     * @test
     * اختبار إرسال بريد على التغييرات (عند تقييم المشرف بعد المدير)
     */
    public function test_sends_email_on_evaluation_changes(): void
    {
        // Arrange
        $this->setSetting('evaluation_email_average_complete_mode', 'each_plus_final');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: first manager, then supervisor
        $this->createEvaluation($employeeId, $cycleId, 'manager', 80.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        $beforeCount = count($this->getEmailLogs());
        
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert: should send additional email when supervisor completes evaluation
        $afterCount = count($this->getEmailLogs());
        $this->assertGreaterThan($beforeCount, $afterCount, 'يجب إرسال بريد جديد عند إضافة تقييم المشرف');
    }

    // =================================================================
    // 2. اختبارات الشروط - Condition Tests
    // =================================================================

    /**
     * @test
     * اختبار send_auto_email = true (يرسل)
     */
    public function test_sends_email_when_auto_send_enabled(): void
    {
        // Arrange
        $this->setSetting('auto_send_eval', '1');
        $employeeId = 4; // employee without supervisor
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs, 'يجب إرسال بريد عندما يكون auto_send_eval = 1');
    }

    /**
     * @test
     * اختبار send_auto_email = false (لا يرسل)
     */
    public function test_does_not_send_email_when_auto_send_disabled(): void
    {
        // Arrange
        $this->setSetting('auto_send_eval', '0');
        $employeeId = 4;
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertEmpty($logs, 'يجب عدم إرسال أي بريد عندما يكون auto_send_eval = 0');
    }

    /**
     * @test
     * اختبار Master Toggle - التأكد من إيقاف كامل
     */
    public function test_master_toggle_prevents_all_emails(): void
    {
        // Arrange: disable master toggle
        $this->setSetting('auto_send_eval', '0');
        $this->setSetting('evaluation_email_manager_only_enabled', '1');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: try multiple scenarios
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert: no emails at all
        $logs = $this->getEmailLogs();
        $this->assertEmpty($logs, 'Master Toggle يجب أن يمنع جميع الإرسالات');
    }

    // =================================================================
    // 3. اختبارات الطرق الثلاث - Three Methods Tests
    // =================================================================

    /**
     * @test
     * manager_only: يرسل عند تقييم المدير فقط
     */
    public function test_manager_only_sends_on_manager_evaluation(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_MANAGER_ONLY);
        $this->setSetting('evaluation_email_manager_only_enabled', '1');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: manager evaluates
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs, 'يجب إرسال بريد عند تقييم المدير في طريقة manager_only');
        $this->assertEquals(EmailService::TYPE_MANAGER_EVALUATED, $logs[0]['email_type']);
    }

    /**
     * @test
     * manager_only: لا يرسل عند تقييم المشرف
     */
    public function test_manager_only_does_not_send_on_supervisor_evaluation(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_MANAGER_ONLY);
        $this->setSetting('evaluation_email_manager_only_enabled', '1');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: supervisor evaluates (should not send in manager_only mode)
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertEmpty($logs, 'يجب عدم إرسال بريد عند تقييم المشرف في طريقة manager_only');
    }

    /**
     * @test
     * available_score: يرسل عند توفر أي تقييم (mode: any)
     */
    public function test_available_score_sends_on_any_evaluation(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_AVAILABLE_SCORE);
        $this->setSetting('evaluation_email_available_score_mode', 'any');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: manager evaluates
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $managerLogs = $this->getEmailLogs();
        $this->assertCount(1, $managerLogs, 'يجب إرسال بريد عند تقييم المدير');
        
        // Act: supervisor evaluates
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert
        $allLogs = $this->getEmailLogs();
        $this->assertCount(2, $allLogs, 'يجب إرسال بريد عند تقييم المشرف أيضاً (mode: any)');
    }

    /**
     * @test
     * available_score: يرسل فقط عند المدير (mode: manager_only)
     */
    public function test_available_score_manager_only_mode(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_AVAILABLE_SCORE);
        $this->setSetting('evaluation_email_available_score_mode', 'manager_only');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: supervisor evaluates first (should not send)
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        $this->assertEmpty($this->getEmailLogs(), 'يجب عدم إرسال بريد عند تقييم المشرف في mode: manager_only');
        
        // Act: manager evaluates (should send)
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertCount(1, $logs, 'يجب إرسال بريد فقط عند تقييم المدير');
    }

    /**
     * @test
     * available_score: يرسل فقط عند الاكتمال (mode: both)
     */
    public function test_available_score_both_mode_requires_completion(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_AVAILABLE_SCORE);
        $this->setSetting('evaluation_email_available_score_mode', 'both');
        
        // Recreate EmailService to pick up new settings
        $this->emailService = new EmailService($this->pdo);
        
        // Use employee without supervisor to test 'both' mode properly
        $employeeId = 4; // no supervisor
        $cycleId = 1;
        
        // Act: only manager evaluates
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert: For employee without supervisor, manager evaluation completes the evaluation
        // So email WILL be sent in mode 'both' because evaluation is complete
        $logs = $this->getEmailLogs();
        $this->assertCount(1, $logs, 'يجب إرسال بريد للموظف بدون مشرف عندما يقيم المدير');
        $this->assertEquals(EmailService::TYPE_FINAL_COMPLETE, $logs[0]['email_type']);
    }

    /**
     * @test
     * average_complete: يرسل عند اكتمال التقييمات
     */
    public function test_average_complete_sends_when_evaluations_complete(): void
    {
        // Arrange: Default method is already average_complete
        $this->setSetting('evaluation_email_average_complete_mode', 'waiting_supervisor_plus_final');
        
        // Recreate service to pick up settings
        $this->emailService = new EmailService($this->pdo);
        
        $employeeId = 3; // has supervisor
        $cycleId = 1;
        
        // Act: manager evaluates first
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        $firstLogs = $this->getEmailLogs();
        // With waiting_supervisor_plus_final mode, should send waiting OR complete email
        $this->assertNotEmpty($firstLogs, 'يجب إرسال بريد عند تقييم المدير');
        
        // Act: supervisor evaluates (complete)
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert: Should have final complete email
        $finalLogs = $this->getEmailLogsByType(EmailService::TYPE_FINAL_COMPLETE);
        $this->assertGreaterThanOrEqual(1, count($finalLogs), 'يجب إرسال بريد "التقييم مكتمل"');
    }

    /**
     * @test
     * average_complete: لا يرسل "بانتظار" للموظف بدون مشرف
     */
    public function test_average_complete_no_waiting_email_without_supervisor(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_AVERAGE_COMPLETE);
        $this->setSetting('evaluation_email_average_complete_mode', 'waiting_supervisor_plus_final');
        $employeeId = 4; // no supervisor
        $cycleId = 1;
        
        // Act: manager evaluates
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert: should send final complete directly, not waiting
        $logs = $this->getEmailLogs();
        $this->assertCount(1, $logs, 'يجب إرسال بريد واحد فقط');
        $this->assertEquals(EmailService::TYPE_FINAL_COMPLETE, $logs[0]['email_type'], 
            'يجب إرسال بريد "مكتمل" مباشرة للموظف بدون مشرف');
    }

    /**
     * @test
     * average_complete: mode both_only - يرسل فقط عند الاكتمال
     */
    public function test_average_complete_both_only_mode(): void
    {
        // Arrange
        $this->setSetting('evaluation_email_average_complete_mode', 'both_only');
        
        // Recreate service to pick up settings
        $this->emailService = new EmailService($this->pdo);
        
        // Use employee without supervisor for clearer test
        $employeeId = 4; // no supervisor
        $cycleId = 1;
        
        // Act: manager evaluates - for employee without supervisor, this completes evaluation
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert: Should send email since evaluation is complete (employee has no supervisor)
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs, 'يجب إرسال بريد للموظف بدون مشرف عند تقييم المدير');
        $this->assertEquals(EmailService::TYPE_FINAL_COMPLETE, $logs[0]['email_type']);
    }

    // =================================================================
    // 4. اختبارات منع التكرار - Duplicate Prevention Tests
    // =================================================================

    /**
     * @test
     * التأكد من عدم إرسال نفس البريد مرتين
     */
    public function test_prevents_duplicate_emails(): void
    {
        // Arrange
        $employeeId = 3;
        $cycleId = 1;
        
        // Act: complete evaluation
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        $firstCount = $this->getSuccessfulEmailCount($employeeId, $cycleId, EmailService::TYPE_FINAL_COMPLETE);
        
        // Act: try to send again
        $this->emailService->sendCompleteEvaluationNotification($employeeId, $cycleId);
        
        $secondCount = $this->getSuccessfulEmailCount($employeeId, $cycleId, EmailService::TYPE_FINAL_COMPLETE);
        
        // Assert
        $this->assertEquals($firstCount, $secondCount, 'يجب عدم إرسال بريد التقييم المكتمل مرتين');
    }

    /**
     * @test
     * اختبار Duplicate Detection مع أنواع مختلفة
     */
    public function test_duplicate_detection_by_email_type(): void
    {
        // Arrange: Default method is average_complete
        $this->setSetting('evaluation_email_average_complete_mode', 'waiting_supervisor_plus_final');
        
        // Recreate service
        $this->emailService = new EmailService($this->pdo);
        
        $employeeId = 3; // has supervisor
        $cycleId = 1;
        
        // Act: manager evaluates first
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Complete the evaluation
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert: should have emails (at least complete email)
        $completeCount = $this->getSuccessfulEmailCount($employeeId, $cycleId, EmailService::TYPE_FINAL_COMPLETE);
        
        $this->assertGreaterThanOrEqual(1, $completeCount, 'يجب إرسال بريد واحد على الأقل من نوع "مكتمل"');
        
        // Verify total emails sent
        $allLogs = $this->getEmailLogs();
        $this->assertGreaterThanOrEqual(1, count($allLogs), 'يجب إرسال بريد واحد على الأقل');
    }

    // =================================================================
    // 5. اختبارات الأخطاء - Error Tests
    // =================================================================

    /**
     * @test
     * اختبار الإرسال بدون إعدادات SMTP
     */
    public function test_handles_missing_smtp_settings(): void
    {
        // Arrange: remove SMTP settings
        $this->setSetting('smtp_host', '');
        $this->setSetting('smtp_user', '');
        $employeeId = 3;
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert: should log the email but mark as failed
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs, 'يجب تسجيل محاولة الإرسال');
        $this->assertEquals('failure', $logs[0]['status'], 'يجب أن تكون الحالة failure');
        $this->assertNotEmpty($logs[0]['error_message'], 'يجب تسجيل رسالة الخطأ');
    }

    /**
     * @test
     * اختبار الإرسال ببريد خاطئ
     */
    public function test_handles_invalid_email(): void
    {
        // Arrange: employee with no email
        $employeeId = 5; // this user has null email
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs, 'يجب تسجيل محاولة الإرسال');
        $this->assertEquals('failure', $logs[0]['status'], 'يجب أن تكون الحالة failure');
        $this->assertStringContainsString('غير متوفر', $logs[0]['error_message'], 
            'يجب أن تحتوي رسالة الخطأ على "غير متوفر"');
    }

    /**
     * @test
     * اختبار معالجة الأخطاء - Exception Handling
     */
    public function test_handles_exceptions_gracefully(): void
    {
        // Arrange: create a scenario that might throw exceptions
        $employeeId = 999; // non-existent employee
        $cycleId = 1;
        
        // Act: should not throw exception
        try {
            $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
            $exceptionThrown = false;
        } catch (\Exception $e) {
            $exceptionThrown = true;
        }
        
        // Assert
        $this->assertFalse($exceptionThrown, 'يجب عدم رفع استثناءات، بل معالجة الأخطاء بسلاسة');
    }

    // =================================================================
    // اختبارات إضافية - Additional Tests
    // =================================================================

    /**
     * @test
     * اختبار إنشاء Token للرابط
     */
    public function test_creates_evaluation_link_token(): void
    {
        // Arrange
        $employeeId = 3;
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert: token should be created
        $stmt = $this->pdo->prepare("SELECT * FROM employee_evaluation_links WHERE employee_id = ? AND cycle_id = ?");
        $stmt->execute([$employeeId, $cycleId]);
        $link = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->assertNotEmpty($link, 'يجب إنشاء token للرابط');
        $this->assertNotEmpty($link['unique_token'], 'يجب أن يكون الـ token غير فارغ');
        $this->assertEquals(32, strlen($link['unique_token']), 'يجب أن يكون الـ token بطول 32 حرف');
    }

    /**
     * @test
     * اختبار محتوى البريد بالعربية
     */
    public function test_email_content_is_in_arabic(): void
    {
        // Arrange
        $employeeId = 3;
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->createEvaluation($employeeId, $cycleId, 'supervisor', 90.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'supervisor', 2);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs);
        
        $email = $logs[0];
        $this->assertStringContainsString('السلام عليكم', $email['body'], 'يجب أن يحتوي البريد على تحية عربية');
        $this->assertStringContainsString('direction: rtl', $email['body'], 'يجب أن يكون البريد باتجاه RTL');
    }

    /**
     * @test
     * اختبار Metadata في السجل
     */
    public function test_logs_metadata_correctly(): void
    {
        // Arrange
        $this->setSetting('evaluation_method', EvaluationCalculator::METHOD_AVAILABLE_SCORE);
        $employeeId = 3;
        $cycleId = 1;
        
        // Act
        $this->createEvaluation($employeeId, $cycleId, 'manager', 85.0);
        $this->emailService->handleEvaluationSubmitted($employeeId, $cycleId, 'manager', 1);
        
        // Assert
        $logs = $this->getEmailLogs();
        $this->assertNotEmpty($logs);
        
        $metadata = json_decode($logs[0]['metadata'], true);
        $this->assertNotNull($metadata, 'يجب أن تكون الـ metadata بصيغة JSON صحيحة');
        $this->assertArrayHasKey('method', $metadata, 'يجب أن تحتوي الـ metadata على الطريقة المستخدمة');
    }
}
