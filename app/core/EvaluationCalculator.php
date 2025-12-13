<?php
/**
 * EvaluationCalculator
 * 
 * يوفر هذا الكلاس وظائف لحساب التقييم النهائي بطرق مختلفة
 * ويدير إعدادات طريقة الحساب من قاعدة البيانات
 */

class EvaluationCalculator {
    private $pdo;
    
    const METHOD_MANAGER_ONLY = 'manager_only';
    const METHOD_AVERAGE = 'average';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * حساب التقييم النهائي بناءً على الطريقة المحددة
     * 
     * @param float|null $managerScore تقييم المدير
     * @param float|null $supervisorScore تقييم المشرف
     * @param string|null $method الطريقة (إذا كانت null، يتم جلبها من الإعدادات)
     * @param int|null $employeeId معرف الموظف (للتحقق من وجود مشرف)
     * @return float|null التقييم النهائي أو null إذا لم تتوفر البيانات الكافية
     */
    public function calculateFinalScore($managerScore = null, $supervisorScore = null, $method = null, $employeeId = null) {
        // جلب الطريقة من الإعدادات إذا لم يتم تحديدها
        if ($method === null) {
            $method = $this->getEvaluationMethod();
        }
        
        // التحقق من صحة الطريقة
        if (!in_array($method, [self::METHOD_MANAGER_ONLY, self::METHOD_AVERAGE])) {
            $method = self::METHOD_MANAGER_ONLY;
        }
        
        // تحويل القيم إلى float
        $managerScore = $managerScore !== null ? (float)$managerScore : null;
        $supervisorScore = $supervisorScore !== null ? (float)$supervisorScore : null;
        
        // حساب التقييم النهائي حسب الطريقة
        if ($method === self::METHOD_MANAGER_ONLY) {
            // الطريقة الافتراضية: تقييم المدير فقط
            return $managerScore;
        } elseif ($method === self::METHOD_AVERAGE) {
            // الطريقة الجديدة: متوسط التقييمين
            
            // التحقق من وجود رئيس مباشر إذا توفر معرف الموظف
            $hasSupervisor = false;
            if ($employeeId) {
                $stmt = $this->pdo->prepare("SELECT supervisor_id FROM users WHERE id = ?");
                $stmt->execute([$employeeId]);
                $supId = $stmt->fetchColumn();
                $hasSupervisor = !empty($supId);
            }
            
            // الحالة 1: الموظف ليس لديه رئيس مباشر
            // يتم اعتماد تقييم المدير فقط
            if ($employeeId && !$hasSupervisor) {
                return $managerScore;
            }
            
            // الحالة 2: الموظف لديه رئيس مباشر (أو لم يتم تحديد الموظف فنفترض وجود مشرف للدقة)
            // إذا توفر كلا التقييمين، نحسب المتوسط
            if ($managerScore !== null && $supervisorScore !== null) {
                return round(($managerScore + $supervisorScore) / 2, 2);
            }
            
            // إذا فقد أحد التقييمين وكان الموظف معروفاً وله مشرف، نرجع null (غير مكتمل)
            if ($employeeId && $hasSupervisor) {
                return null; 
            }
            
            // fallback (للتوافق مع القديم في حال عدم تمرير employeeId)
            if ($managerScore !== null) {
                return $managerScore;
            } elseif ($supervisorScore !== null) {
                return $supervisorScore;
            }
        }
        
        return null;
    }
    
    /**
     * جلب طريقة احتساب التقييم الحالية من قاعدة البيانات
     * 
     * @return string الطريقة الحالية (manager_only أو average)
     */
    public function getEvaluationMethod() {
        try {
            $stmt = $this->pdo->prepare("SELECT value FROM system_settings WHERE `key` = ?");
            $stmt->execute(['evaluation_method']);
            $method = $stmt->fetchColumn();
            
            // إذا لم يكن الإعداد موجوداً، نرجع القيمة الافتراضية
            if (!$method) {
                return self::METHOD_MANAGER_ONLY;
            }
            
            // التحقق من صحة القيمة
            if (!in_array($method, [self::METHOD_MANAGER_ONLY, self::METHOD_AVERAGE])) {
                return self::METHOD_MANAGER_ONLY;
            }
            
            return $method;
        } catch (PDOException $e) {
            // في حالة حدوث خطأ، نرجع القيمة الافتراضية
            error_log("Error getting evaluation method: " . $e->getMessage());
            return self::METHOD_MANAGER_ONLY;
        }
    }
    
    /**
     * تعيين طريقة احتساب التقييم في قاعدة البيانات
     * 
     * @param string $method الطريقة (manager_only أو average)
     * @return bool true في حالة النجاح، false في حالة الفشل
     * @throws InvalidArgumentException إذا كانت الطريقة غير صالحة
     */
    public function setEvaluationMethod($method) {
        // التحقق من صحة الطريقة
        if (!in_array($method, [self::METHOD_MANAGER_ONLY, self::METHOD_AVERAGE])) {
            throw new InvalidArgumentException("طريقة غير صالحة: يجب أن تكون 'manager_only' أو 'average'");
        }
        
        try {
            // التحقق من وجود الإعداد
            $stmt = $this->pdo->prepare("SELECT id FROM system_settings WHERE `key` = ?");
            $stmt->execute(['evaluation_method']);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                // تحديث الإعداد الموجود
                $stmt = $this->pdo->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?");
                $stmt->execute([$method, 'evaluation_method']);
            } else {
                // إضافة إعداد جديد
                $stmt = $this->pdo->prepare("INSERT INTO system_settings (`key`, value) VALUES (?, ?)");
                $stmt->execute(['evaluation_method', $method]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error setting evaluation method: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب تقييمات موظف معين في دورة معينة
     * 
     * @param int $employeeId معرف الموظف
     * @param int $cycleId معرف دورة التقييم
     * @return array مصفوفة تحتوي على manager_score و supervisor_score و final_score
     */
    public function getEmployeeScores($employeeId, $cycleId) {
        try {
            // جلب تقييم المدير
            $stmt = $this->pdo->prepare("
                SELECT total_score 
                FROM employee_evaluations 
                WHERE employee_id = ? AND cycle_id = ? AND evaluator_role = 'manager'
                AND status != 'draft'
            ");
            $stmt->execute([$employeeId, $cycleId]);
            $managerScore = $stmt->fetchColumn();
            
            // جلب تقييم المشرف
            $stmt = $this->pdo->prepare("
                SELECT total_score 
                FROM employee_evaluations 
                WHERE employee_id = ? AND cycle_id = ? AND evaluator_role = 'supervisor'
                AND status != 'draft'
            ");
            $stmt->execute([$employeeId, $cycleId]);
            $supervisorScore = $stmt->fetchColumn();
            
            // حساب التقييم النهائي
            // نمرر employeeId لتفعيل منطق التحقق من المشرف
            $finalScore = $this->calculateFinalScore($managerScore, $supervisorScore, null, $employeeId);
            
            return [
                'manager_score' => $managerScore !== false ? (float)$managerScore : null,
                'supervisor_score' => $supervisorScore !== false ? (float)$supervisorScore : null,
                'final_score' => $finalScore,
                'method' => $this->getEvaluationMethod()
            ];
        } catch (PDOException $e) {
            error_log("Error getting employee scores: " . $e->getMessage());
            return [
                'manager_score' => null,
                'supervisor_score' => null,
                'final_score' => null,
                'method' => $this->getEvaluationMethod()
            ];
        }
    }
    
    /**
     * الحصول على اسم الطريقة بالعربية
     * 
     * @param string|null $method الطريقة (إذا كانت null، يتم جلبها من الإعدادات)
     * @return string اسم الطريقة بالعربية
     */
    public function getMethodName($method = null) {
        if ($method === null) {
            $method = $this->getEvaluationMethod();
        }
        
        switch ($method) {
            case self::METHOD_MANAGER_ONLY:
                return 'تقييم مدير الإدارة فقط';
            case self::METHOD_AVERAGE:
                return 'متوسط تقييمي المدير والمشرف';
            default:
                return 'غير معروف';
        }
    }
}
