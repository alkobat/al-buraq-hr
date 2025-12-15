<?php

class RateLimiter
{
    private $pdo;
    
    private const MESSAGES_PER_HOUR = 100;
    private const MESSAGES_PER_RECIPIENT_DAILY = 5;
    private const RATE_LIMIT_TABLE = 'email_rate_limit_logs';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    /**
     * التحقق من تجاوز حد التصنيف
     * @param string $recipientEmail البريد الإلكتروني للمستقبل
     * @param string $senderId معرف المرسل (user ID أو system)
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public function checkRateLimit($recipientEmail, $senderId = 'system')
    {
        try {
            $recipientEmail = strtolower(trim($recipientEmail));
            
            $checkHourly = $this->checkHourlyLimit($senderId);
            if (!$checkHourly['allowed']) {
                return $checkHourly;
            }

            $checkDaily = $this->checkDailyLimit($recipientEmail);
            if (!$checkDaily['allowed']) {
                return $checkDaily;
            }

            return ['allowed' => true, 'reason' => null];
        } catch (Exception $e) {
            error_log('RateLimiter error: ' . $e->getMessage());
            return ['allowed' => false, 'reason' => 'خطأ في التحقق من حد التصنيف'];
        }
    }

    /**
     * تسجيل محاولة الإرسال
     * @param string $recipientEmail البريد الإلكتروني للمستقبل
     * @param bool $success هل نجح الإرسال
     * @param string $senderId معرف المرسل
     */
    public function logAttempt($recipientEmail, $success = true, $senderId = 'system')
    {
        try {
            $recipientEmail = strtolower(trim($recipientEmail));
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO " . self::RATE_LIMIT_TABLE . " (recipient_email, sender_id, success, attempted_at) 
                 VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([
                $recipientEmail,
                $senderId,
                $success ? 1 : 0
            ]);
        } catch (Exception $e) {
            error_log('Failed to log rate limit attempt: ' . $e->getMessage());
        }
    }

    /**
     * التحقق من حد الإرسال في الساعة
     * @param string $senderId معرف المرسل
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    private function checkHourlyLimit($senderId)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM " . self::RATE_LIMIT_TABLE . "
                 WHERE sender_id = ? AND success = 1 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->execute([$senderId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)$result['count'];

            if ($count >= self::MESSAGES_PER_HOUR) {
                return [
                    'allowed' => false,
                    'reason' => 'تم تجاوز حد الرسائل المسموح به في الساعة (' . self::MESSAGES_PER_HOUR . ' رسالة)'
                ];
            }

            return ['allowed' => true, 'reason' => null];
        } catch (Exception $e) {
            error_log('Hourly limit check error: ' . $e->getMessage());
            return ['allowed' => true, 'reason' => null];
        }
    }

    /**
     * التحقق من حد الإرسال لنفس المستقبل يومياً
     * @param string $recipientEmail البريد الإلكتروني للمستقبل
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    private function checkDailyLimit($recipientEmail)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM " . self::RATE_LIMIT_TABLE . "
                 WHERE LOWER(recipient_email) = LOWER(?) AND success = 1 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
            $stmt->execute([$recipientEmail]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)$result['count'];

            if ($count >= self::MESSAGES_PER_RECIPIENT_DAILY) {
                return [
                    'allowed' => false,
                    'reason' => 'تم تجاوز حد الرسائل لهذا المستقبل اليوم (' . self::MESSAGES_PER_RECIPIENT_DAILY . ' رسائل)'
                ];
            }

            return ['allowed' => true, 'reason' => null];
        } catch (Exception $e) {
            error_log('Daily limit check error: ' . $e->getMessage());
            return ['allowed' => true, 'reason' => null];
        }
    }

    /**
     * الحصول على إحصائيات الإرسال
     * @param string $recipientEmail البريد الإلكتروني للمستقبل
     * @return array إحصائيات
     */
    public function getStats($recipientEmail)
    {
        try {
            $recipientEmail = strtolower(trim($recipientEmail));
            
            $hourly = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM " . self::RATE_LIMIT_TABLE . "
                 WHERE sender_id = 'system' AND success = 1 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $hourly->execute();
            $hourlyCount = (int)$hourly->fetch(PDO::FETCH_ASSOC)['count'];

            $daily = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM " . self::RATE_LIMIT_TABLE . "
                 WHERE LOWER(recipient_email) = LOWER(?) AND success = 1 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
            $daily->execute([$recipientEmail]);
            $dailyCount = (int)$daily->fetch(PDO::FETCH_ASSOC)['count'];

            return [
                'hourly_sent' => $hourlyCount,
                'hourly_limit' => self::MESSAGES_PER_HOUR,
                'daily_to_recipient' => $dailyCount,
                'daily_limit' => self::MESSAGES_PER_RECIPIENT_DAILY,
            ];
        } catch (Exception $e) {
            error_log('Failed to get rate limit stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * حذف السجلات القديمة (GDPR compliance)
     * @param int $daysOld عدد أيام حفظ السجلات
     */
    public function deleteOldLogs($daysOld = 90)
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM " . self::RATE_LIMIT_TABLE . "
                 WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $stmt->execute([$daysOld]);
            
            $deletedRows = $this->pdo->lastInsertId();
            error_log("Deleted $deletedRows old rate limit logs (older than $daysOld days)");
        } catch (Exception $e) {
            error_log('Failed to delete old rate limit logs: ' . $e->getMessage());
        }
    }

    /**
     * التأكد من وجود جدول السجلات
     */
    private function ensureTableExists()
    {
        try {
            $this->pdo->query("SELECT 1 FROM " . self::RATE_LIMIT_TABLE . " LIMIT 1");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'no such table') !== false || 
                strpos($e->getMessage(), 'doesn\'t exist') !== false) {
                $this->createTable();
            }
        }
    }

    /**
     * إنشاء جدول السجلات
     */
    private function createTable()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS " . self::RATE_LIMIT_TABLE . " (
                `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `recipient_email` varchar(150) NOT NULL,
                `sender_id` varchar(50) DEFAULT 'system',
                `success` tinyint(1) DEFAULT 1,
                `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_recipient_time` (`recipient_email`, `attempted_at`),
                KEY `idx_sender_time` (`sender_id`, `attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            error_log('Email rate limit table created successfully');
        } catch (Exception $e) {
            error_log('Failed to create rate limit table: ' . $e->getMessage());
        }
    }
}
