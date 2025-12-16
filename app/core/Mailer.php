<?php

use PHPMailer\PHPMailer\PHPMailer;

$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!class_exists(PHPMailer::class) && file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once __DIR__ . '/SecurityManager.php';

class Mailer
{
    private $pdo;
    private $settings;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $stmt = $this->pdo->query("SELECT `setting_key`, `setting_value`, `is_encrypted` FROM email_settings");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            
            if ($row['is_encrypted'] == 1 && $value) {
                try {
                    $value = SecurityManager::decrypt($value);
                } catch (Exception $e) {
                    error_log('Failed to decrypt setting ' . $row['setting_key'] . ': ' . $e->getMessage());
                    $value = '';
                }
            }
            
            $this->settings[$row['setting_key']] = $value;
        }
    }

    private function getMailer(): PHPMailer
    {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer غير مثبت. تأكد من تشغيل: composer install');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->settings['smtp_host'] ?? '';
        $mail->SMTPAuth = true;
        $mail->Username = $this->settings['smtp_user'] ?? '';
        $mail->Password = $this->settings['smtp_pass'] ?? '';
        $mail->SMTPSecure = $this->settings['smtp_secure'] ?? '';
        $mail->Port = (int)($this->settings['smtp_port'] ?? 0);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            $this->settings['smtp_from_email'] ?? ($this->settings['smtp_user'] ?? ''),
            $this->settings['smtp_from_name'] ?? ''
        );
        $mail->isHTML(true);

        return $mail;
    }

    /**
     * إرسال بريد إلكتروني (يدعم المرفقات)
     * @param array $attachments مصفوفة المرفقات: ['path' => '...', 'name' => '...'] أو ['string' => $data, 'name' => '...']
     */
    public function sendEmail($toEmail, $toName, $templateType, $placeholders = [], $attachments = [])
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE type = ?");
            $stmt->execute([$templateType]);
            $template = $stmt->fetch();

            if (!$template) {
                return false;
            }

            $subject = $template['subject'];
            $body = $template['body'];

            foreach ($placeholders as $key => $value) {
                $body = str_replace('{' . $key . '}', $value, $body);
                $subject = str_replace('{' . $key . '}', $value, $subject);
            }

            $mail = $this->getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body = $body;

            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    if (isset($att['string'])) {
                        $mail->addStringAttachment($att['string'], $att['name']);
                    } elseif (isset($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? '');
                    }
                }
            }

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendCustomEmail($toEmail, $toName, $subject, $messageBody, $attachments = [])
    {
        try {
            $mail = $this->getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body = $messageBody;

            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    if (isset($att['string'])) {
                        $mail->addStringAttachment($att['string'], $att['name']);
                    } elseif (isset($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? '');
                    }
                }
            }

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }
}
