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
    const METHOD_AVAILABLE_SCORE = 'available_score';
    const METHOD_AVERAGE_COMPLETE = 'average_complete';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * حساب التقييم النهائي بناءً على الطريقة المحددة
     * 
     * @param int|null $employeeId معرف الموظف (مطلوب للطريقة average_complete فقط)
     * @param float|null $managerScore تقييم المدير
     * @param float|null $supervisorScore تقييم المشرف
     * @param string|null $method الطريقة (إذا كانت null، يتم جلبها من الإعدادات)
     * @return array مصفوفة تحتوي على 'score' و 'status' و 'note'
     */
    public function calculateFinalScore($employeeId = null, $managerScore = null, $supervisorScore = null, $method = null) {
        // جلب الطريقة من الإعدادات إذا لم يتم تحديدها
        if ($method === null) {
            $method = $this->getEvaluationMethod();
        }
        
        // التحقق من صحة الطريقة
        if (!in_array($method, [self::METHOD_MANAGER_ONLY, self::METHOD_AVAILABLE_SCORE, self::METHOD_AVERAGE_COMPLETE])) {
            $method = self::METHOD_MANAGER_ONLY;
        }
        
        // تحويل القيم إلى float
        $managerScore = $managerScore !== null ? (float)$managerScore : null;
        $supervisorScore = $supervisorScore !== null ? (float)$supervisorScore : null;
        
        // حساب التقييم النهائي حسب الطريقة
        switch ($method) {
            case self::METHOD_MANAGER_ONLY:
                return $this->calculateManagerOnly($managerScore);
            
            case self::METHOD_AVAILABLE_SCORE:
                return $this->calculateAvailableScore($managerScore, $supervisorScore);
            
            case self::METHOD_AVERAGE_COMPLETE:
                return $this->calculateAverageComplete($employeeId, $managerScore, $supervisorScore);
            
            default:
                return $this->calculateManagerOnly($managerScore);
        }
    }

    /**
     * الطريقة الأولى: تقييم مدير الإدارة فقط
     * 
     * @param float|null $managerScore تقييم المدير
     * @return array
     */
    private function calculateManagerOnly($managerScore = null) {
        return [
            'score' => $managerScore,
            'status' => 'complete',
            'note' => 'يتم استخدام تقييم مدير الإدارة فقط'
        ];
    }

    /**
     * الطريقة الثانية: استخدام التقييم الموجود
     * - إذا قيّم كلا المدير والمشرف: النتيجة = (المدير + المشرف) / 2
     * - إذا قيّم المدير فقط: النتيجة = تقييم المدير
     * - إذا قيّم المشرف فقط: النتيجة = تقييم المشرف
     * - إذا لم يقيّم أحد: النتيجة = NULL
     * 
     * @param float|null $managerScore تقييم المدير
     * @param float|null $supervisorScore تقييم المشرف
     * @return array
     */
    private function calculateAvailableScore($managerScore = null, $supervisorScore = null) {
        if ($managerScore !== null && $supervisorScore !== null) {
            // كلا التقييمين موجودان
            return [
                'score' => round(($managerScore + $supervisorScore) / 2, 2),
                'status' => 'complete',
                'note' => 'يتم استخدام متوسط تقييمات المدير والمشرف'
            ];
        } elseif ($managerScore !== null) {
            // المدير فقط قيّم
            return [
                'score' => $managerScore,
                'status' => 'partial',
                'note' => 'يتم استخدام تقييم المدير فقط (لم يقدم المشرف تقييمه بعد)'
            ];
        } elseif ($supervisorScore !== null) {
            // المشرف فقط قيّم
            return [
                'score' => $supervisorScore,
                'status' => 'partial',
                'note' => 'يتم استخدام تقييم المشرف فقط (لم يقدم المدير تقييمه بعد)'
            ];
        }
        
        // لا توجد تقييمات
        return [
            'score' => null,
            'status' => 'incomplete',
            'note' => 'لم يتم تقديم أي تقييمات بعد'
        ];
    }

    /**
     * الطريقة الثالثة: متوسط المدير والمشرف (مع التحقق من الاكتمال)
     * 
     * - إذا كان الموظف بدون رئيس مباشر (supervisor_id = NULL):
     *   النتيجة = تقييم المدير فقط (لا يوجد رئيس مباشر أصلاً)
     * 
     * - إذا كان الموظف لديه رئيس مباشر:
     *   - إذا قيّم كلاهما: النتيجة = (المدير + المشرف) / 2
     *   - إذا لم يقيّم أحدهما: النتيجة = NULL، الحالة = 'incomplete'
     * 
     * @param int|null $employeeId معرف الموظف
     * @param float|null $managerScore تقييم المدير
     * @param float|null $supervisorScore تقييم المشرف
     * @return array
     */
    private function calculateAverageComplete($employeeId = null, $managerScore = null, $supervisorScore = null) {
        // جلب معلومات الموظف للتحقق من وجود رئيس مباشر
        $hasSupervisor = false;
        if ($employeeId !== null) {
            try {
                $stmt = $this->pdo->prepare("SELECT supervisor_id FROM users WHERE id = ?");
                $stmt->execute([$employeeId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $hasSupervisor = ($result && $result['supervisor_id'] !== null);
            } catch (PDOException $e) {
                error_log("Error checking supervisor: " . $e->getMessage());
            }
        }
        
        // إذا لم يكن لدى الموظف رئيس مباشر، استخدم تقييم المدير فقط
        if (!$hasSupervisor) {
            return [
                'score' => $managerScore,
                'status' => 'complete',
                'note' => 'الموظف بدون رئيس مباشر - يتم استخدام تقييم مدير الإدارة فقط'
            ];
        }
        
        // إذا كان للموظف رئيس مباشر، يجب أن يقيّم كلاهما
        if ($managerScore !== null && $supervisorScore !== null) {
            // كلا التقييمين موجودان
            return [
                'score' => round(($managerScore + $supervisorScore) / 2, 2),
                'status' => 'complete',
                'note' => 'يتم استخدام متوسط تقييمات المدير والمشرف'
            ];
        } else {
            // أحد التقييمات ناقص
            $missingNote = '';
            if ($managerScore === null && $supervisorScore === null) {
                $missingNote = 'لم يتم تقديم أي تقييمات بعد';
            } elseif ($managerScore === null) {
                $missingNote = 'التقييم غير مكتمل - في انتظار تقييم مدير الإدارة';
            } else {
                $missingNote = 'التقييم غير مكتمل - في انتظار تقييم الرئيس المباشر';
            }
            
            return [
                'score' => null,
                'status' => 'incomplete',
                'note' => $missingNote
            ];
        }
    }
    
    /**
     * جلب طريقة احتساب التقييم الحالية من قاعدة البيانات
     * 
     * @return string الطريقة الحالية (manager_only أو available_score أو average_complete)
     */
    public function getEvaluationMethod() {
        try {
            $stmt = $this->pdo->prepare("SELECT value FROM system_settings WHERE `key` = ?");
            $stmt->execute(['evaluation_method']);
            $method = $stmt->fetchColumn();
            
            // إذا لم يكن الإعداد موجوداً، نرجع القيمة الافتراضية
            if (!$method) {
                return self::METHOD_AVERAGE_COMPLETE;
            }
            
            // التحقق من صحة القيمة
            if (!in_array($method, [self::METHOD_MANAGER_ONLY, self::METHOD_AVAILABLE_SCORE, self::METHOD_AVERAGE_COMPLETE])) {
                return self::METHOD_AVERAGE_COMPLETE;
            }
            
            return $method;
        } catch (PDOException $e) {
            // في حالة حدوث خطأ، نرجع القيمة الافتراضية
            error_log("Error getting evaluation method: " . $e->getMessage());
            return self::METHOD_AVERAGE_COMPLETE;
        }
    }
    
    /**
     * تعيين طريقة احتساب التقييم في قاعدة البيانات
     * 
     * @param string $method الطريقة (manager_only أو available_score أو average_complete)
     * @return bool true في حالة النجاح، false في حالة الفشل
     * @throws InvalidArgumentException إذا كانت الطريقة غير صالحة
     */
    public function setEvaluationMethod($method) {
        // التحقق من صحة الطريقة
        if (!in_array($method, [self::METHOD_MANAGER_ONLY, self::METHOD_AVAILABLE_SCORE, self::METHOD_AVERAGE_COMPLETE])) {
            throw new InvalidArgumentException("طريقة غير صالحة: يجب أن تكون 'manager_only' أو 'available_score' أو 'average_complete'");
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
     * @return array مصفوفة تحتوي على manager_score و supervisor_score و final_score و status و note
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
            $result = $this->calculateFinalScore($employeeId, $managerScore, $supervisorScore);
            
            return [
                'manager_score' => $managerScore !== false ? (float)$managerScore : null,
                'supervisor_score' => $supervisorScore !== false ? (float)$supervisorScore : null,
                'final_score' => $result['score'],
                'status' => $result['status'],
                'note' => $result['note'],
                'method' => $this->getEvaluationMethod()
            ];
        } catch (PDOException $e) {
            error_log("Error getting employee scores: " . $e->getMessage());
            return [
                'manager_score' => null,
                'supervisor_score' => null,
                'final_score' => null,
                'status' => 'error',
                'note' => 'حدث خطأ أثناء جلب البيانات',
                'method' => $this->getEvaluationMethod()
            ];
        }
    }
    
    /**
     * التحقق مما إذا كان تقييم الموظف مكتملًا حسب طريقة الاحتساب الحالية
     * 
     * @param int $employeeId
     * @param int $cycleId
     * @return bool
     */
    public function isEvaluationComplete($employeeId, $cycleId) {
        $scores = $this->getEmployeeScores($employeeId, $cycleId);
        return ($scores['status'] ?? '') === 'complete' && ($scores['final_score'] ?? null) !== null;
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
            case self::METHOD_AVAILABLE_SCORE:
                return 'استخدام التقييم الموجود';
            case self::METHOD_AVERAGE_COMPLETE:
                return 'متوسط المدير والمشرف (مع التحقق من الاكتمال)';
            default:
                return 'غير معروف';
        }
    }

    /**
     * الحصول على وصف الطريقة بالعربية
     * 
     * @param string|null $method الطريقة (إذا كانت null، يتم جلبها من الإعدادات)
     * @return string وصف الطريقة بالعربية
     */
    public function getMethodDescription($method = null) {
        if ($method === null) {
            $method = $this->getEvaluationMethod();
        }
        
        switch ($method) {
            case self::METHOD_MANAGER_ONLY:
                return 'التقييم النهائي = تقييم المدير (الطريقة الافتراضية)';
            case self::METHOD_AVAILABLE_SCORE:
                return 'يتم استخدام التقييم الموجود: متوسط إذا قيم كلاهما، أو أيهما متاح';
            case self::METHOD_AVERAGE_COMPLETE:
                return 'يتم التأكد من اكتمال التقييمات: متوسط إذا قيم كلاهما، وغير مكتمل إذا نقص أحدهما (مع استثناء للموظفين بدون رئيس)';
            default:
                return 'غير معروف';
        }
    }
}
