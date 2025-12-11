<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $pdo;
    private $settings;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        // جلب الإعدادات
        $stmt = $this->pdo->query("SELECT `key`, `value` FROM system_settings");
        $this->settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function getMailer() {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->settings['smtp_user'];
        $mail->Password   = $this->settings['smtp_pass'];
        $mail->SMTPSecure = $this->settings['smtp_secure'];
        $mail->Port       = $this->settings['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($this->settings['smtp_from_email'], $this->settings['smtp_from_name']);
        $mail->isHTML(true);
        return $mail;
    }

    /**
     * إرسال بريد إلكتروني (يدعم المرفقات)
     * @param array $attachments مصفوفة المرفقات: ['path' => '...', 'name' => '...'] أو ['string' => $data, 'name' => '...']
     */
    public function sendEmail($toEmail, $toName, $templateType, $placeholders = [], $attachments = []) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE type = ?");
            $stmt->execute([$templateType]);
            $template = $stmt->fetch();

            if (!$template) return false;

            $subject = $template['subject'];
            $body = $template['body'];

            foreach ($placeholders as $key => $value) {
                $body = str_replace('{' . $key . '}', $value, $body);
            }

            $mail = $this->getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // إضافة المرفقات
            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    if (isset($att['string'])) {
                        // مرفق من متغير نصي (مثل PDF مولد)
                        $mail->addStringAttachment($att['string'], $att['name']);
                    } elseif (isset($att['path'])) {
                        // مرفق من ملف على السيرفر
                        $mail->addAttachment($att['path'], $att['name'] ?? '');
                    }
                }
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function sendCustomEmail($toEmail, $toName, $subject, $messageBody, $attachments = []) {
        try {
            $mail = $this->getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $messageBody;

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
        } catch (Exception $e) {
            return false;
        }
    }
}