<?php

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/EvaluationCalculator.php';

class EmailService
{
    private $pdo;
    private $mailer;
    private $calculator;

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
    }

    /**
     * الدالة الرئيسية التي يتم استدعاؤها بعد إرسال تقييم (Submit) من المدير/المشرف.
     */
    public function handleEvaluationSubmitted($employeeId, $cycleId, $evaluatorRole, $evaluatorId)
    {
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
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logEmail($employeeId, $cycleId, $toEmail ?: null, $subject, $body, $emailType, 'failure', 'البريد الإلكتروني غير متوفر أو غير صالح', $meta);
            return;
        }

        $sent = false;
        try {
            $sent = $this->mailer->sendCustomEmail($toEmail, $toName, $subject, $body);
        } catch (Exception $e) {
            $sent = false;
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', $e->getMessage(), $meta);
            return;
        }

        if ($sent) {
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'success', null, $meta);
        } else {
            $this->logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, 'failure', 'فشل إرسال البريد (Mailer)', $meta);
        }
    }

    private function logEmail($employeeId, $cycleId, $toEmail, $subject, $body, $emailType, $status, $errorMessage = null, $meta = [])
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO email_logs (employee_id, cycle_id, to_email, subject, body, email_type, status, error_message, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $employeeId,
                $cycleId,
                $toEmail,
                $subject,
                $body,
                $emailType,
                $status,
                $errorMessage,
                !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
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
}
