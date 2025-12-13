<?php
/**
 * Analytics Service - Reusable analytics data layer for HR evaluation system
 * 
 * Provides comprehensive analytics data for dashboard visualization
 * Uses prepared statements for security and falls back to active cycle
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/EvaluationCalculator.php';

class AnalyticsService {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Get active evaluation cycle ID (fallback when no filter provided)
     */
    private function getActiveCycleId(): ?int {
        $stmt = $this->pdo->query("SELECT id FROM evaluation_cycles WHERE status = 'active' ORDER BY year DESC LIMIT 1");
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * Sanitize and validate filters
     */
    private function sanitizeFilters(array $filters): array {
        $sanitized = [];
        
        // cycle_id - can be null (will use active cycle)
        if (isset($filters['cycle_id']) && is_numeric($filters['cycle_id'])) {
            $sanitized['cycle_id'] = (int)$filters['cycle_id'];
        }
        
        // department_ids - array of integers
        if (isset($filters['department_ids']) && is_array($filters['department_ids'])) {
            $sanitized['department_ids'] = array_filter(array_map('intval', $filters['department_ids']));
        }
        
        // evaluator_role - must be manager or supervisor
        if (isset($filters['evaluator_role']) && in_array($filters['evaluator_role'], ['manager', 'supervisor'])) {
            $sanitized['evaluator_role'] = $filters['evaluator_role'];
        }
        
        // date range
        if (isset($filters['date_from']) && strtotime($filters['date_from'])) {
            $sanitized['date_from'] = date('Y-m-d', strtotime($filters['date_from']));
        }
        
        if (isset($filters['date_to']) && strtotime($filters['date_to'])) {
            $sanitized['date_to'] = date('Y-m-d', strtotime($filters['date_to']));
        }
        
        // min/max score
        if (isset($filters['min_score']) && is_numeric($filters['min_score'])) {
            $sanitized['min_score'] = (float)$filters['min_score'];
        }
        
        if (isset($filters['max_score']) && is_numeric($filters['max_score'])) {
            $sanitized['max_score'] = (float)$filters['max_score'];
        }
        
        return $sanitized;
    }
    
    /**
     * Build WHERE clause based on filters
     */
    private function buildWhereClause(array $filters): array {
        $where = ["1=1"]; // Always true condition
        $params = [];
        
        // Use active cycle if no cycle_id provided
        $cycle_id = $filters['cycle_id'] ?? $this->getActiveCycleId();
        if ($cycle_id) {
            $where[] = "e.cycle_id = ?";
            $params[] = $cycle_id;
        }
        
        // Department filter
        if (!empty($filters['department_ids'])) {
            $placeholders = str_repeat('?,', count($filters['department_ids']) - 1) . '?';
            $where[] = "u.department_id IN ($placeholders)";
            $params = array_merge($params, $filters['department_ids']);
        }
        
        // Evaluator role filter
        if (!empty($filters['evaluator_role'])) {
            $where[] = "e.evaluator_role = ?";
            $params[] = $filters['evaluator_role'];
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "e.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "e.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Score range filter
        if (isset($filters['min_score'])) {
            $where[] = "e.total_score >= ?";
            $params[] = $filters['min_score'];
        }
        
        if (isset($filters['max_score'])) {
            $where[] = "e.total_score <= ?";
            $params[] = $filters['max_score'];
        }
        
        return ['where_clause' => implode(' AND ', $where), 'params' => $params];
    }
    
    /**
     * Get global statistics
     */
    public function getGlobalStats(array $filters = []): array {
        $filters = $this->sanitizeFilters($filters);
        
        // تطبيق منطق طريقة التقييم (manager_only)
        $method = (new EvaluationCalculator($this->pdo))->getEvaluationMethod();
        if ($method === 'manager_only') {
            $filters['evaluator_role'] = 'manager';
        }

        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        
        // Get basic counts and averages
        $sql = "
            SELECT 
                COUNT(*) as total_evaluations,
                SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN e.status = 'submitted' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN e.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN e.status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                AVG(e.total_score) as overall_average,
                COUNT(DISTINCT u.id) as total_employees
            FROM employee_evaluations e
            JOIN users u ON e.employee_id = u.id
            WHERE {$where['where_clause']}
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        // Calculate completion rate
        $total_employees = (int)$result['total_employees'];
        $approved_count = (int)$result['approved_count'];
        $completion_rate = $total_employees > 0 ? round(($approved_count / $total_employees) * 100, 1) : 0;
        
        return [
            'total_evaluations' => (int)$result['total_evaluations'],
            'approved_count' => $approved_count,
            'pending_count' => (int)$result['pending_count'],
            'rejected_count' => (int)$result['rejected_count'],
            'draft_count' => (int)$result['draft_count'],
            'completion_rate' => $completion_rate,
            'overall_average' => round((float)$result['overall_average'], 1),
            'total_employees' => $total_employees
        ];
    }
    
    /**
     * Get department aggregates
     */
    public function getDepartmentAggregates(array $filters = []): array {
        $filters = $this->sanitizeFilters($filters);
        
        // تطبيق منطق طريقة التقييم (manager_only)
        $method = (new EvaluationCalculator($this->pdo))->getEvaluationMethod();
        if ($method === 'manager_only') {
            $filters['evaluator_role'] = 'manager';
        }

        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        
        $sql = "
            SELECT 
                d.id,
                d.name_ar,
                COUNT(e.id) as evaluation_count,
                AVG(e.total_score) as avg_score,
                SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                COUNT(DISTINCT u.id) as total_employees
            FROM departments d
            LEFT JOIN users u ON d.id = u.department_id
            LEFT JOIN employee_evaluations e ON u.id = e.employee_id AND {$where['where_clause']}
            WHERE d.status = 'active'
            GROUP BY d.id, d.name_ar
            HAVING evaluation_count > 0
            ORDER BY avg_score DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $departments = $stmt->fetchAll();
        
        // Calculate completion percentage and add ranking
        foreach ($departments as $index => &$dept) {
            $completion_rate = $dept['total_employees'] > 0 
                ? round(($dept['approved_count'] / $dept['total_employees']) * 100, 1) 
                : 0;
            
            $dept['completion_rate'] = $completion_rate;
            $dept['avg_score'] = round((float)$dept['avg_score'], 1);
            $dept['ranking'] = $index + 1;
        }
        
        return $departments;
    }
    
    /**
     * Get top and bottom performing employees
     */
    public function getTopBottomEmployees(array $filters = [], int $limit = 10): array {
        $filters = $this->sanitizeFilters($filters);
        
        // تطبيق منطق طريقة التقييم (manager_only)
        $method = (new EvaluationCalculator($this->pdo))->getEvaluationMethod();
        if ($method === 'manager_only') {
            $filters['evaluator_role'] = 'manager';
        }

        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        
        // Top performers
        $sql = "
            SELECT 
                u.id,
                u.name as employee_name,
                d.name_ar as department,
                manager.name as manager_name,
                e.total_score,
                e.status,
                e.updated_at
            FROM employee_evaluations e
            JOIN users u ON e.employee_id = u.id
            JOIN departments d ON u.department_id = d.id
            LEFT JOIN users manager ON u.manager_id = manager.id
            WHERE {$where['where_clause']} AND e.status = 'approved'
            ORDER BY e.total_score DESC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $params_top = array_merge($params, [$limit]);
        $stmt->execute($params_top);
        $top_performers = $stmt->fetchAll();
        
        // Bottom performers
        $sql = "
            SELECT 
                u.id,
                u.name as employee_name,
                d.name_ar as department,
                manager.name as manager_name,
                e.total_score,
                e.status,
                e.updated_at
            FROM employee_evaluations e
            JOIN users u ON e.employee_id = u.id
            JOIN departments d ON u.department_id = d.id
            LEFT JOIN users manager ON u.manager_id = manager.id
            WHERE {$where['where_clause']} AND e.status = 'approved'
            ORDER BY e.total_score ASC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $params_bottom = array_merge($params, [$limit]);
        $stmt->execute($params_bottom);
        $bottom_performers = $stmt->fetchAll();
        
        // Calculate score deltas
        foreach ($top_performers as &$employee) {
            $employee['score_delta'] = $employee['total_score'] - 70; // vs company average benchmark
        }
        
        foreach ($bottom_performers as &$employee) {
            $employee['score_delta'] = $employee['total_score'] - 70; // vs company average benchmark
        }
        
        return [
            'top_performers' => $top_performers,
            'bottom_performers' => $bottom_performers
        ];
    }
    
    /**
     * Get status distribution for charts
     */
    public function getStatusDistribution(array $filters = []): array {
        $filters = $this->sanitizeFilters($filters);
        
        // تطبيق منطق طريقة التقييم (manager_only)
        $method = (new EvaluationCalculator($this->pdo))->getEvaluationMethod();
        if ($method === 'manager_only') {
            $filters['evaluator_role'] = 'manager';
        }

        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        
        $sql = "
            SELECT 
                e.status,
                COUNT(*) as count,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM employee_evaluations e2 WHERE {$where['where_clause']})), 1) as percentage
            FROM employee_evaluations e
            WHERE {$where['where_clause']}
            GROUP BY e.status
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $distribution = $stmt->fetchAll();
        
        // Ensure all status types are represented
        $statuses = ['approved', 'submitted', 'rejected', 'draft'];
        $result = [];
        
        foreach ($statuses as $status) {
            $found = false;
            foreach ($distribution as $item) {
                if ($item['status'] === $status) {
                    $result[] = [
                        'status' => $status,
                        'count' => (int)$item['count'],
                        'percentage' => (float)$item['percentage']
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $result[] = [
                    'status' => $status,
                    'count' => 0,
                    'percentage' => 0.0
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get trend data (monthly/quarterly)
     */
    public function getTrendData(string $period = 'monthly', array $filters = []): array {
        $filters = $this->sanitizeFilters($filters);
        
        // تطبيق منطق طريقة التقييم (manager_only)
        $method = (new EvaluationCalculator($this->pdo))->getEvaluationMethod();
        if ($method === 'manager_only') {
            $filters['evaluator_role'] = 'manager';
        }

        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        
        $date_format = $period === 'quarterly' ? '%Y-%m' : '%Y-%m';
        $period_label = $period === 'quarterly' ? 'quarter' : 'month';
        
        $sql = "
            SELECT 
                DATE_FORMAT(e.updated_at, '$date_format') as period,
                COUNT(*) as evaluation_count,
                AVG(e.total_score) as avg_score,
                SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_count
            FROM employee_evaluations e
            WHERE {$where['where_clause']}
            GROUP BY DATE_FORMAT(e.updated_at, '$date_format')
            ORDER BY period ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $trends = $stmt->fetchAll();
        
        // Format period labels
        foreach ($trends as &$trend) {
            if ($period === 'quarterly') {
                $trend['period'] = $this->formatQuarter($trend['period']);
            } else {
                $trend['period'] = date('F Y', strtotime($trend['period'] . '-01'));
            }
            $trend['avg_score'] = round((float)$trend['avg_score'], 1);
        }
        
        return $trends;
    }
    
    /**
     * Get heatmap matrix data (department x competency or score band)
     */
    public function getHeatmapMatrix(string $matrix_type = 'score_band', array $filters = []): array {
        $filters = $this->sanitizeFilters($filters);
        
        // تطبيق منطق طريقة التقييم (manager_only)
        $method = (new EvaluationCalculator($this->pdo))->getEvaluationMethod();
        if ($method === 'manager_only') {
            $filters['evaluator_role'] = 'manager';
        }

        $where = $this->buildWhereClause($filters);
        $params = $where['params'];
        
        if ($matrix_type === 'competency') {
            // Department x Competency matrix
            $sql = "
                SELECT 
                    d.name_ar as department,
                    ef.title_ar as competency,
                    AVG(er.score / ef.max_score * 100) as avg_percentage,
                    COUNT(*) as response_count
                FROM evaluation_responses er
                JOIN employee_evaluations e ON er.evaluation_id = e.id
                JOIN evaluation_fields ef ON er.field_id = ef.id
                JOIN users u ON e.employee_id = u.id
                JOIN departments d ON u.department_id = d.id
                WHERE {$where['where_clause']} AND e.status = 'approved'
                GROUP BY d.id, ef.id, d.name_ar, ef.title_ar
                ORDER BY d.name_ar, ef.order_index
            ";
        } else {
            // Department x Score Band matrix
            $sql = "
                SELECT 
                    d.name_ar as department,
                    CASE 
                        WHEN e.total_score >= 90 THEN 'ممتاز (90-100)'
                        WHEN e.total_score >= 80 THEN 'جيد جداً (80-89)'
                        WHEN e.total_score >= 70 THEN 'جيد (70-79)'
                        WHEN e.total_score >= 60 THEN 'مقبول (60-69)'
                        ELSE 'ضعيف (<60)'
                    END as score_band,
                    COUNT(*) as employee_count,
                    AVG(e.total_score) as avg_score
                FROM employee_evaluations e
                JOIN users u ON e.employee_id = u.id
                JOIN departments d ON u.department_id = d.id
                WHERE {$where['where_clause']} AND e.status = 'approved'
                GROUP BY d.id, score_band, d.name_ar
                ORDER BY d.name_ar, avg_score DESC
            ";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $matrix_data = $stmt->fetchAll();
        
        // Reorganize data for heatmap visualization
        $heatmap = [];
        $categories = [];
        
        foreach ($matrix_data as $row) {
            if ($matrix_type === 'competency') {
                $x = $row['competency'];
                $y = $row['department'];
                $value = round((float)$row['avg_percentage'], 1);
            } else {
                $x = $row['score_band'];
                $y = $row['department'];
                $value = (int)$row['employee_count'];
            }
            
            if (!in_array($x, $categories)) {
                $categories[] = $x;
            }
            
            $heatmap[$y][$x] = $value;
        }
        
        return [
            'matrix_type' => $matrix_type,
            'categories' => $categories,
            'data' => $heatmap
        ];
    }
    
    /**
     * Format quarter from YYYY-MM format
     */
    private function formatQuarter(string $period): string {
        list($year, $month) = explode('-', $period);
        $quarter = ceil($month / 3);
        return "Q{$quarter} {$year}";
    }
    
    /**
     * Get comprehensive analytics data
     */
    public function getComprehensiveAnalytics(array $filters = []): array {
        // Basic filters
        $global_stats = $this->getGlobalStats($filters);
        $department_data = $this->getDepartmentAggregates($filters);
        $top_bottom = $this->getTopBottomEmployees($filters);
        $status_distribution = $this->getStatusDistribution($filters);
        $trend_monthly = $this->getTrendData('monthly', $filters);
        $trend_quarterly = $this->getTrendData('quarterly', $filters);
        
        // Heatmap matrices
        $heatmap_competency = $this->getHeatmapMatrix('competency', $filters);
        $heatmap_scoreband = $this->getHeatmapMatrix('score_band', $filters);
        
        return [
            'global_stats' => $global_stats,
            'departments' => $department_data,
            'top_performers' => $top_bottom['top_performers'],
            'bottom_performers' => $top_bottom['bottom_performers'],
            'status_distribution' => $status_distribution,
            'trends' => [
                'monthly' => $trend_monthly,
                'quarterly' => $trend_quarterly
            ],
            'heatmaps' => [
                'competency_matrix' => $heatmap_competency,
                'score_band_matrix' => $heatmap_scoreband
            ],
            'filters_applied' => $this->sanitizeFilters($filters),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}