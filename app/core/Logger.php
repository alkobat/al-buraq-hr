<?php
class Logger {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * تسجيل عملية جديدة في النظام
     * @param string $action نوع العملية (login, create, update, delete, etc.)
     * @param string $description وصف تفصيلي للعملية
     * @param int|null $userId معرف المستخدم (اختياري، الافتراضي هو المستخدم المسجل)
     */
    public function log($action, $description, $userId = null) {
        try {
            // تحديد المستخدم الحالي إذا لم يتم تمريره
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // جلب بيانات المستخدم الحالية (الاسم والدور)
            $userName = 'نظام / زائر';
            $userRole = 'guest';

            if ($userId) {
                // نحاول جلب البيانات من الجلسة أولاً لتقليل الاستعلامات
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                    $userName = $_SESSION['name'] ?? 'مستخدم';
                    $userRole = $_SESSION['role'] ?? 'unknown';
                } else {
                    // إذا كان المستخدم مختلف عن الجلسة الحالية، نجلبه من القاعدة
                    $stmt = $this->pdo->prepare("SELECT name, role FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $userName = $user['name'];
                        $userRole = $user['role'];
                    }
                }
            }

            // الحصول على IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // إدراج السجل
            $sql = "INSERT INTO activity_logs (user_id, user_name, role, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $userName, $userRole, $action, $description, $ip]);

        } catch (Exception $e) {
            // في حال الفشل، لا نوقف النظام، فقط نتجاهل الخطأ أو نسجله في ملف نصي
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
?>