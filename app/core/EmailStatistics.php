<?php

class EmailStatistics
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get today's email statistics
     */
    public function getTodayStats()
    {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failed
            FROM email_logs
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)$row['total'];
        $sent = (int)$row['sent'];
        $failed = (int)$row['failed'];
        $rate = $total > 0 ? round(($sent / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'rate' => $rate,
        ];
    }

    /**
     * Get email logs with pagination and filtering
     */
    public function getEmailLogs($page = 1, $limit = 20, $filters = [])
    {
        $offset = ($page - 1) * $limit;
        $query = "SELECT * FROM email_logs WHERE 1=1";
        $params = [];

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }

        // Filter by email type
        if (!empty($filters['email_type'])) {
            $query .= " AND email_type = ?";
            $params[] = $filters['email_type'];
        }

        // Search by recipient email
        if (!empty($filters['to_email'])) {
            $query .= " AND recipient_email LIKE ?";
            $params[] = '%' . $filters['to_email'] . '%';
        }

        // Search by subject
        if (!empty($filters['subject'])) {
            $query .= " AND subject LIKE ?";
            $params[] = '%' . $filters['subject'] . '%';
        }

        // Get total count
        $countQuery = preg_replace('/SELECT \*/', 'SELECT COUNT(*) as cnt', $query);
        $countStmt = $this->pdo->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['cnt'];

        // Get paginated results
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page,
        ];
    }

    /**
     * Get email statistics by type
     */
    public function getStatsByType()
    {
        $stmt = $this->pdo->query("
            SELECT 
                email_type,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failure
            FROM email_logs
            GROUP BY email_type
            ORDER BY count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get email statistics by recipient
     */
    public function getStatsByRecipient($limit = 10)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                recipient_email,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failure
            FROM email_logs
            WHERE recipient_email IS NOT NULL
            GROUP BY recipient_email
            ORDER BY count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get daily email statistics for chart
     */
    public function getDailyStats($days = 30)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failed
            FROM email_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get failed emails with error details
     */
    public function getFailedEmails($limit = 50)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                recipient_email,
                subject,
                email_type,
                error_message,
                created_at
            FROM email_logs
            WHERE status = 'failure'
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get email details by ID
     */
    public function getEmailDetails($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_logs WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get alerts (failed emails, etc.)
     */
    public function getAlerts()
    {
        $alerts = [];

        // Failed emails in last 24 hours
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM email_logs 
            WHERE status = 'failure' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute();
        $failed24h = (int)$stmt->fetchColumn();

        if ($failed24h > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'رسائل فاشلة',
                'message' => "هناك $failed24h رسائل فاشلة في آخر 24 ساعة",
                'count' => $failed24h,
            ];
        }

        // No emails sent today
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM email_logs 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $emailsToday = (int)$stmt->fetchColumn();

        if ($emailsToday === 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'لم يتم إرسال رسائل اليوم',
                'message' => 'لم تتم معالجة أي رسائل بريد في اليوم الحالي',
                'count' => 0,
            ];
        }

        return $alerts;
    }

    /**
     * Get last sent emails
     */
    public function getLastEmails($limit = 10)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                recipient_email,
                subject,
                status,
                email_type,
                created_at
            FROM email_logs
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get retry-able failed emails
     */
    public function getRetryableEmails($limit = 50)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_logs
            WHERE status = 'failure'
            AND recipient_email IS NOT NULL
            AND recipient_email != ''
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get statistics for a date range
     */
    public function getStatsByDateRange($dateFrom, $dateTo)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failed,
                COUNT(DISTINCT recipient_email) as unique_recipients
            FROM email_logs
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)$row['total'];
        $sent = (int)$row['sent'];
        $failed = (int)$row['failed'];
        $rate = $total > 0 ? round(($sent / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'rate' => $rate,
            'unique_recipients' => (int)$row['unique_recipients'],
        ];
    }

    /**
     * Get success/failure rate for chart
     */
    public function getSuccessFailureRate($days = 30)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM email_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY status
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
