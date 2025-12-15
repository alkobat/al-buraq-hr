<?php

$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    throw new RuntimeException('الاعتماديات غير مثبتة. تأكد من تشغيل: composer install');
}

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/EvaluationCalculator.php';
require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/EmailValidator.php';

class EmailService
{
    private $pdo;
    private $mailer;
    private $calculator;
    private $rateLimiter;
    private $validator;

    const SETTING_MANAGER_ONLY_ENABLED = 'evaluation_email_manager_only_enabled';
    const SETTING_AVAILABLE_SCORE_MODE = 'evaluation_email_available_score_mode';
    const SETTING_AVERAGE_COMPLETE_MODE = 'evaluation_email_average_complete_mode';

    const TYPE_MANAGER_EVALUATED = 'manager_evaluated';
    const TYPE_SUPERVISOR_EVALUATED = 'supervisor_evaluated';
    const TYPE_AVAILABLE_ANY = 'available_any';
    const TYPE_FINAL_COMPLETE = 'final_complete';
    const TYPE_WAITING_SUPERVISOR = 'waiting_supervisor';
    const TYPE_WAITING_MANAGER = 'waiting_manager';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->mailer = new Mailer($pdo);
        $this->calculator = new EvaluationCalculator($pdo);
        $this->rateLimiter = new RateLimiter($pdo);
        $this->validator = new EmailValidator();
    }

    /**
     * الدالة الرئيسية التي يتم استدعاؤها بعد إرسال تقييم (Submit) من المدير/المشرف.
     */
    public function handleEvaluationSubmitted($employeeId, $cycleId, $evaluatorRole, $evaluatorId)
{
    // 🆕 فحص Master Toggle
    if ($this->getSetting('auto_send_eval', '0') !== '1') {
        return; // إيقاف فوري
    }
        try {
            $method = $this->calculator->getEvaluationMethod();

            // تأكد من وجود رابط صالح دائماً (حتى لو تم تعطيل الإرسال)
            $this->getOrCreateEmployeeEvaluationToken($employeeId, $cycleId);

            if ($method === EvaluationCalculator::METHOD_MANAGER_ONLY) {
                if ($evaluatorRole === 'manager' && $this->getSetting(self::SETTING_MANAGER_ONLY_ENABLED, '0') === '1') {
                    $this->sendEvaluationNotification($employeeId, $cycleId, $method, $evaluatorRole, $evaluatorId);
                }
                return;
            }

            if ($method === EvaluationCalculator::METHOD_AVAILABLE_SCORE) {
                $mode = $this->getSetting(self::SETTING_AVAILABLE_SCORE_MODE, 'any');

                if ($mode === 'manager_only' && $evaluatorRole === 'manager') {
                    $this->sendAvailableScoreNotification($employeeId, $cycleId, 'manager', $evaluatorId);
                } elseif ($mode === 'supervisor_only' && $evaluatorRole === 'supervisor') {
                    $this->sendAvailableScoreNotification($employeeId, $cycleId, 'supervisor', $evaluatorId);
                } elseif ($mode === 'any') {
                    $this->sendAvailableScoreNotification($employeeId, $cycleId, $evaluatorRole, $evaluatorId);
                } elseif ($mode === 'both') {
                    if ($this->calculator->isEvaluationComplete($employeeId, $cycleId)) {
                        $this->sendCompleteEvaluationNotification($employeeId, $cycleId);
                    }
                }

                return;
            }

            if ($method === EvaluationCalculator::METHOD_AVERAGE_COMPLETE) {
                $mode = $this->getSetting(self::SETTING_AVERAGE_COMPLETE_MODE, 'waiting_supervisor_plus_final');

                $hasSupervisor = $this->employeeHasSupervisor($employeeId);
                $isComplete = $this->calculator->isEvaluationComplete($employeeId, $cycleId);

                if ($mode === 'both_only') {
                    if ($isComplete) {
                        $this->sendCompleteEvaluationNotification($employeeId, $cycleId);
                    }
                    return;
                }

                if ($mode === 'each_plus_final') {
                    if ($isComplete) {
                        $this->sendCompleteEvaluationNotification($employeeId, $cycleId);
                    } else {
                        if ($hasSupervisor) {
                            if ($evaluatorRole === 'manager') {
                                $this->sendWaitingForSupervisorNotification($employeeId, $cycleId, $evaluatorId);
                            } elseif ($evaluatorRole === 'supervisor') {
                                $this->sendWaitingForManagerNotification($employeeId, $cycleId, $evaluatorId);
                            }
                        }
                    }
                    return;
                }

                if ($mode === 'waiting_supervisor_plus_final') {
                    if ($isComplete) {
                        $this->sendCompleteEvaluationNotification($employeeId, $cycleId);
                    } else {
                        if ($hasSupervisor && $evaluatorRole === 'manager') {
                            $this->sendWaitingForSupervisorNotification($employeeId, $cycleId, $evaluatorId);
                        }
                    }
                    return;
                }
            }
        } catch (Exception $e) {
            error_log('EmailService error: ' . $e->getMessage());
        }
    }

    // =========================
    // الخيار الأول
    // =========================

    public function sendEvaluationNotification($employeeId, $cycleId, $method, $evaluatorRole, $evaluatorId = null)
    {
        $evaluatorName = null;
        if ($evaluatorId) {
            $evaluator = $this->getUser($evaluatorId);
            $evaluatorName = $evaluator ? $evaluator['name'] : null;
        }

        $this->sendEvaluationNotificationInternal($employeeId, $cycleId, $evaluatorRole, $evaluatorName);
    }

    // =========================
    // الخيار الثاني
    // =========================

    public function sendAvailableScoreNotification($employeeId, $cycleId, $newScoreFrom, $evaluatorId = null)
    {
        $employee = $this->getUser($employeeId);
        if (!$employee) {
            return;
        }

        $evaluator = $evaluatorId ? $this->getUser($evaluatorId) : null;

        $roleLabel = ($newScoreFrom === 'supervisor') ? 'الرئيس المباشر' : 'مدير الإدارة';
        $subject = 'تم تقييمك من قبل ' . $roleLabel;

        $link = $this->buildApprovalLink($employeeId, $cycleId);
        $evaluatorName = $evaluator ? $evaluator['name'] : $roleLabel;

        $body = $this->wrapHtml(
            "<p>السلام عليكم <strong>" . htmlspecialchars($employee['name']) . "</strong>،</p>" .
            "<p>تم تقييم أدائك من قبل <strong>" . htmlspecialchars($roleLabel) . "</strong>: " . htmlspecialchars($evaluatorName) . "</p>" .
            "<p>🔗 <a href=\"" . htmlspecialchars($link) . "\">عرض التقييم والموافقة/الرفض</a></p>" .
            "<p>شكراً لك</p>"
        );

        $type = ($newScoreFrom === 'supervisor') ? self::TYPE_SUPERVISOR_EVALUATED : self::TYPE_MANAGER_EVALUATED;
        $this->sendAndLog($employeeId, $cycleId, $employee['email'], $employee['name'], $subject, $body, $type, [
            'method' => 'available_score',
            'from' => $newScoreFrom,
        ]);
    }

    // =========================
    // الخيار الثالث
    // =========================

    public function sendCompleteEvaluationNotification($employeeId, $cycleId)
    {
        if ($this->hasSuccessfulEmail($employeeId, $cycleId, self::TYPE_FINAL_COMPLETE)) {
            return;
        }

        $employee = $this->getUser($employeeId);
        if (!$employee) {
            return;
        }

        $scores = $this->calculator->getEmployeeScores($employeeId, $cycleId);
        $finalScore = $scores['final_score'];

        if ($finalScore === null) {
            return;
        }

        $subject = 'تم استكمال تقييمك - النتيجة النهائية: ' . $finalScore . '/100';
        $link = $this->buildApprovalLink($employeeId, $cycleId);

        $body = $this->wrapHtml(
            "<p>السلام عليكم <strong>" . htmlspecialchars($employee['name']) . "</strong>،</p>" .
            "<p>تم استكمال تقييمك بنجاح.</p>" .
            "<p><strong>النتيجة النهائية:</strong> " . htmlspecialchars((string)$finalScore) . "/100</p>" .
            "<p>🔗 <a href=\"" . htmlspecialchars($link) . "\">عرض التقييم والموافقة/الرفض</a></p>" .
            "<p>شكراً لك</p>"
        );

        $this->sendAndLog($employeeId, $cycleId, $employee['email'], $employee['name'], $subject, $body, self::TYPE_FINAL_COMPLETE, [
            'method' => $scores['method'] ?? null,
            'status' => $scores['status'] ?? null,
        ]);
    }

    public function sendWaitingForSupervisorNotification($employeeId, $cycleId, $evaluatorId = null)
    {
        $employee = $this->getUser($employeeId);
        if (!$employee) {
            return;
        }

        $evaluator = $evaluatorId ? $this->getUser($evaluatorId) : null;
        $evaluatorName = $evaluator ? $evaluator['name'] : 'مدير الإدارة';

        $subject = 'تم تقييمك من مدير الإدارة - بانتظار تقييم الرئيس المباشر';
        $link = $this->buildApprovalLink($employeeId, $cycleId);

        $body = $this->wrapHtml(
            "<p>السلام عليكم <strong>" . htmlspecialchars($employee['name']) . "</strong>،</p>" .
            "<p>تم تقييم أدائك من قبل مدير الإدارة: <strong>" . htmlspecialchars($evaluatorName) . "</strong></p>" .
            "<p>لا يزال التقييم غير مكتمل - بانتظار تقييم الرئيس المباشر.</p>" .
            "<p>🔗 <a href=\"" . htmlspecialchars($link) . "\">عرض التقييم</a></p>" .
            "<p>شكراً لك</p>"
        );

        $this->sendAndLog($employeeId, $cycleId, $employee['email'], $employee['name'], $subject, $body, self::TYPE_WAITING_SUPERVISOR, [
            'method' => 'average_complete',
        ]);
    }

    public function sendWaitingForManagerNotification($employeeId, $cycleId, $evaluatorId = null)
    {
        $employee = $this->getUser($employeeId);
        if (!$employee) {
            return;
        }

        $evaluator = $evaluatorId ? $this->getUser($evaluatorId) : null;
        $evaluatorName = $evaluator ? $evaluator['name'] : 'الرئيس المباشر';

        $subject = 'تم تقييمك من الرئيس المباشر - بانتظار تقييم مدير الإدارة';
        $link = $this->buildApprovalLink($employeeId, $cycleId);

        $body = $this->wrapHtml(
            "<p>السلام عليكم <strong>" . htmlspecialchars($employee['name']) . "</strong>،</p>" .
            "<p>تم تقييم أدائك من قبل الرئيس المباشر: <strong>" . htmlspecialchars($evaluatorName) . "</strong></p>" .
            "<p>لا يزال التقييم غير مكتمل - بانتظار تقييم مدير الإدارة.</p>" .
            "<p>🔗 <a href=\"" . htmlspecialchars($link) . "\">عرض التقييم</a></p>" .
            "<p>شكراً لك</p>"
        );

        $this->sendAndLog($employeeId, $cycleId, $employee['email'], $employee['name'], $subject, $body, self::TYPE_WAITING_MANAGER, [
            'method' => 'average_complete',
        ]);
    }

    // =========================
    // Helpers
    // =========================

    private function sendEvaluationNotificationInternal($employeeId, $cycleId, $evaluatorRole, $evaluatorName)
    {
        $employee = $this->getUser($employeeId);
        if (!$employee) {
            return;
        }

        $roleLabel = ($evaluatorRole === 'supervisor') ? 'الرئيس المباشر' : 'مدير الإدارة';
        $subject = 'تم تقييمك من قبل ' . $roleLabel;

        $link = $this->buildApprovalLink($employeeId, $cycleId);
        $evaluatorName = $evaluatorName ?: $roleLabel;

        $body = $this->wrapHtml(
            "<p>السلام عليكم <strong>" . htmlspecialchars($employee['name']) . "</strong>،</p>" .
            "<p>تم تقييم أدائك من قبل <strong>" . htmlspecialchars($roleLabel) . "</strong>: " . htmlspecialchars($evaluatorName) . "</p>" .
            "<p>🔗 <a href=\"" . htmlspecialchars($link) . "\">عرض التقييم والموافقة/الرفض</a></p>" .
            "<p>شكراً لك</p>"
        );

        $type = ($evaluatorRole === 'supervisor') ? self::TYPE_SUPERVISOR_EVALUATED : self::TYPE_MANAGER_EVALUATED;
        $this->sendAndLog($employeeId, $cycleId, $employee['email'], $employee['name'], $subject, $body, $type, [
            'method' => $this->calculator->getEvaluationMethod(),
            'role' => $evaluatorRole,
        ]);
    }

    private function sendAndLog($employeeId, $cycleId, $toEmail, $toName, $subject, $body, $emailType, $meta = [])
    {
        $toEmail = trim((string)$toEmail);
        
        $validation = EmailValidator::validate($toEmail);
        if (!$validation['is_valid']) {
            $this->logEmail($employeeId, $cycleId, null, $subject, $body, $emailType, 'failure', 'البريد الإلكتروني غير صالح: ' . $validation['message'], $meta, $toEmail);
            return;
        }

        $spamCheck = EmailValidator::detectSpam($subject, $body);
        if ($spamCheck['is_suspicious']) {
            $spamReasons = implode(', ', $spamCheck['reasons']);
            $meta['spam_detected'] = $spamReasons;
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', 'رسالة مريبة: ' . $spamReasons, $meta, $toEmail);
            return;
        }

        $linkCheck = EmailValidator::findSuspiciousLinks($body);
        if ($linkCheck['has_suspicious_links']) {
            $suspiciousLinks = implode(', ', $linkCheck['links']);
            $meta['suspicious_links'] = $suspiciousLinks;
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', 'روابط مريبة في الرسالة: ' . $suspiciousLinks, $meta, $toEmail);
            return;
        }

        $rateLimitCheck = $this->rateLimiter->checkRateLimit($toEmail, (string)$employeeId);
        if (!$rateLimitCheck['allowed']) {
            $this->rateLimiter->logAttempt($toEmail, false, (string)$employeeId);
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', 'تجاوز حد التصنيف: ' . $rateLimitCheck['reason'], $meta, $toEmail);
            return;
        }

        $sent = false;
        try {
            $body = SecurityManager::sanitizeEmailContent($body);
            $sent = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        } catch (Exception $e) {
            $sent = false;
            $this->rateLimiter->logAttempt($toEmail, false, (string)$employeeId);
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', $e->getMessage(), $meta, $toEmail);
            return;
        }

        if ($sent) {
            $this->rateLimiter->logAttempt($toEmail, true, (string)$employeeId);
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'success', null, $meta, $toEmail);
        } else {
            $this->rateLimiter->logAttempt($toEmail, false, (string)$employeeId);
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', 'فشل إرسال البريد (Mailer)', $meta, $toEmail);
        }
    }

    private function logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, $status, $errorMessage = null, $meta = [], $originalEmail = null)
    {
        try {
            $shouldEncryptEmail = $this->getSetting('encrypt_sensitive_data', '1') === '1';
            $shouldAnonymize = $this->getSetting('anonymize_email_logs', '1') === '1';
            
            $emailHash = null;
            $loggedEmail = null;
            $isEncrypted = 0;
            
            if ($originalEmail) {
                $originalEmail = strtolower(trim($originalEmail));
                $emailHash = SecurityManager::hashEmail($originalEmail);
            } elseif ($toEmail) {
                $toEmail = strtolower(trim($toEmail));
                $emailHash = SecurityManager::hashEmail($toEmail);
            }
            
            if ($shouldEncryptEmail && $toEmail) {
                try {
                    $loggedEmail = SecurityManager::encrypt($toEmail);
                    $isEncrypted = 1;
                } catch (Exception $e) {
                    error_log('Failed to encrypt email: ' . $e->getMessage());
                    $loggedEmail = $shouldAnonymize ? null : $toEmail;
                }
            } elseif ($shouldAnonymize) {
                $loggedEmail = null;
            } else {
                $loggedEmail = $toEmail;
            }
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO email_logs (employee_id, cycle_id, to_email, recipient_email_hash, subject, body, email_type, status, error_message, metadata, is_encrypted) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $employeeId,
                $cycleId,
                $loggedEmail,
                $emailHash,
                $subject,
                $body,
                $emailType,
                $status,
                $errorMessage,
                !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                $isEncrypted,
            ]);
        } catch (Exception $e) {
            error_log('Failed to insert email log: ' . $e->getMessage());
        }
    }

    private function hasSuccessfulEmail($employeeId, $cycleId, $emailType)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_logs WHERE employee_id = ? AND cycle_id = ? AND email_type = ? AND status = ?');
            $stmt->execute([$employeeId, $cycleId, $emailType, 'success']);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getUser($userId)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id, name, email, supervisor_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    private function employeeHasSupervisor($employeeId)
    {
        $user = $this->getUser($employeeId);
        return $user && $user['supervisor_id'] !== null;
    }

    private function getSetting($key, $default = null)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT value FROM system_settings WHERE `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val === false || $val === null || $val === '') ? $default : $val;
        } catch (Exception $e) {
            return $default;
        }
    }

    private function getOrCreateEmployeeEvaluationToken($employeeId, $cycleId)
    {
        try {
            $stmt = $this->pdo->prepare('SELECT unique_token, expires_at FROM employee_evaluation_links WHERE employee_id = ? AND cycle_id = ? LIMIT 1');
            $stmt->execute([$employeeId, $cycleId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
                    $token = bin2hex(random_bytes(16));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
                    $this->pdo->prepare('UPDATE employee_evaluation_links SET unique_token = ?, expires_at = ? WHERE employee_id = ? AND cycle_id = ?')
                        ->execute([$token, $expiresAt, $employeeId, $cycleId]);
                    return $token;
                }

                return $row['unique_token'];
            }

            $token = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

            try {
                $this->pdo->prepare('INSERT INTO employee_evaluation_links (employee_id, cycle_id, unique_token, expires_at) VALUES (?, ?, ?, ?)')
                    ->execute([$employeeId, $cycleId, $token, $expiresAt]);

                return $token;
            } catch (PDOException $e) {
                // في حال حدوث تعارض (مثلاً تم إنشاء الرابط في نفس اللحظة)، نجلب التوكن الحالي.
                $stmt = $this->pdo->prepare('SELECT unique_token FROM employee_evaluation_links WHERE employee_id = ? AND cycle_id = ? LIMIT 1');
                $stmt->execute([$employeeId, $cycleId]);
                $existing = $stmt->fetchColumn();

                if ($existing) {
                    return $existing;
                }

                throw $e;
            }
        } catch (Exception $e) {
            error_log('Failed to create evaluation link token: ' . $e->getMessage());
            return null;
        }
    }

    private function buildApprovalLink($employeeId, $cycleId)
    {
        $token = $this->getOrCreateEmployeeEvaluationToken($employeeId, $cycleId);
        if (!$token) {
            return '#';
        }

        return $this->buildPublicUrl('approve.php', ['token' => $token]);
    }

    private function buildPublicUrl($file, $queryParams = [])
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $publicPath = '';

        $pos = strpos($scriptName, '/public/');
        if ($pos !== false) {
            $publicPath = substr($scriptName, 0, $pos + strlen('/public'));
        } else {
            $publicPath = rtrim(dirname($scriptName), '/');
        }

        $url = $protocol . '://' . $host . rtrim($publicPath, '/') . '/' . ltrim($file, '/');

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }

    private function wrapHtml($body)
    {
        return '<div style="font-family:Tahoma, Arial; direction: rtl; text-align: right;">' . $body . '</div>';
    }

    /**
     * حذف سجلات البريد القديمة (GDPR Compliance)
     * @param int $daysOld عدد أيام حفظ السجلات
     * @return int عدد السجلات المحذوفة
     */
    public function cleanupOldEmailLogs($daysOld = 90)
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM email_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $stmt->execute([$daysOld]);
            
            $deletedCount = $stmt->rowCount();
            error_log("Deleted $deletedCount old email logs (older than $daysOld days)");
            
            return $deletedCount;
        } catch (Exception $e) {
            error_log('Failed to cleanup old email logs: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * الحصول على سجل بريد الموظف (مع فك التشفير إذا لزم الأمر)
     * @param int $employeeId معرف الموظف
     * @param int|null $limit حد أقصى لعدد السجلات
     * @return array سجلات البريد
     */
    public function getEmployeeEmailLogs($employeeId, $limit = 50)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, employee_id, cycle_id, to_email, recipient_email_hash, subject, email_type, status, error_message, is_encrypted, created_at 
                 FROM email_logs WHERE employee_id = ? ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$employeeId, $limit]);
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($logs as &$log) {
                if ($log['is_encrypted'] && $log['to_email']) {
                    try {
                        $log['to_email'] = SecurityManager::decrypt($log['to_email']);
                    } catch (Exception $e) {
                        $log['to_email'] = '[مشفر - فشل فك التشفير]';
                    }
                }
            }
            
            return $logs;
        } catch (Exception $e) {
            error_log('Failed to get employee email logs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * حذف بيانات البريد للموظف (حق GDPR)
     * @param int $employeeId معرف الموظف
     * @return bool هل تم الحذف بنجاح
     */
    public function deleteEmployeeEmailData($employeeId)
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE email_logs SET to_email = NULL, recipient_email_hash = NULL WHERE employee_id = ?"
            );
            $stmt->execute([$employeeId]);
            
            $updatedCount = $stmt->rowCount();
            error_log("Anonymized email data for employee $employeeId ($updatedCount records)");
            
            return true;
        } catch (Exception $e) {
            error_log('Failed to delete employee email data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على إحصائيات البريد
     * @return array إحصائيات
     */
    public function getEmailStats()
    {
        try {
            $stats = [
                'total_sent' => 0,
                'total_failed' => 0,
                'total_logs' => 0,
                'rate_limit_violations' => 0,
                'spam_detected' => 0,
            ];

            $totalStmt = $this->pdo->query("SELECT COUNT(*) as count FROM email_logs");
            $stats['total_logs'] = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['count'];

            $sentStmt = $this->pdo->query("SELECT COUNT(*) as count FROM email_logs WHERE status = 'success'");
            $stats['total_sent'] = (int)$sentStmt->fetch(PDO::FETCH_ASSOC)['count'];

            $failedStmt = $this->pdo->query("SELECT COUNT(*) as count FROM email_logs WHERE status = 'failure'");
            $stats['total_failed'] = (int)$failedStmt->fetch(PDO::FETCH_ASSOC)['count'];

            $rateLimitStmt = $this->pdo->query("SELECT COUNT(*) as count FROM email_logs WHERE error_message LIKE '%تجاوز حد التصنيف%'");
            $stats['rate_limit_violations'] = (int)$rateLimitStmt->fetch(PDO::FETCH_ASSOC)['count'];

            $spamStmt = $this->pdo->query("SELECT COUNT(*) as count FROM email_logs WHERE error_message LIKE '%رسالة مريبة%'");
            $stats['spam_detected'] = (int)$spamStmt->fetch(PDO::FETCH_ASSOC)['count'];

            return $stats;
        } catch (Exception $e) {
            error_log('Failed to get email stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * إنشاء نسخة احتياطية من سجل بريد الموظف (GDPR Data Export)
     * @param int $employeeId معرف الموظف
     * @return array|null بيانات البريد أو null
     */
    public function exportEmployeeEmailData($employeeId)
    {
        try {
            $logs = $this->getEmployeeEmailLogs($employeeId, 1000);
            
            if (empty($logs)) {
                return null;
            }

            return [
                'employee_id' => $employeeId,
                'exported_at' => date('Y-m-d H:i:s'),
                'email_logs' => $logs,
            ];
        } catch (Exception $e) {
            error_log('Failed to export employee email data: ' . $e->getMessage());
            return null;
        }
    }
}
