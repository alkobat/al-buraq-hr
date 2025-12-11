<?php
/**
 * Analytics Data API Endpoint
 * 
 * Provides REST API access to analytics data for dashboard consumption
 * Supports filtering and caching for performance optimization
 */

// Enable CORS for cross-origin requests if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Method not allowed',
        'message' => 'Only GET requests are supported'
    ]);
    exit();
}

session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => 'Access denied. Admin or manager role required.'
    ]);
    exit();
}

try {
    require_once '../../../app/core/AnalyticsService.php';
    
    // Initialize analytics service
    $analytics = new AnalyticsService();
    
    // Parse and validate incoming filters from query parameters
    $filters = [];
    
    // Get cycle_id if specified
    if (isset($_GET['cycle_id']) && is_numeric($_GET['cycle_id'])) {
        $filters['cycle_id'] = (int)$_GET['cycle_id'];
    }
    
    // Get department_ids as comma-separated list or array
    if (isset($_GET['department_ids'])) {
        if (is_array($_GET['department_ids'])) {
            $filters['department_ids'] = array_map('intval', $_GET['department_ids']);
        } else {
            $departments = explode(',', $_GET['department_ids']);
            $filters['department_ids'] = array_filter(array_map('intval', $departments));
        }
    }
    
    // Get evaluator_role
    if (isset($_GET['evaluator_role']) && in_array($_GET['evaluator_role'], ['manager', 'supervisor'])) {
        $filters['evaluator_role'] = $_GET['evaluator_role'];
    }
    
    // Get date range
    if (isset($_GET['date_from']) && strtotime($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (isset($_GET['date_to']) && strtotime($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Get score range
    if (isset($_GET['min_score']) && is_numeric($_GET['min_score'])) {
        $filters['min_score'] = (float)$_GET['min_score'];
    }
    
    if (isset($_GET['max_score']) && is_numeric($_GET['max_score'])) {
        $filters['max_score'] = (float)$_GET['max_score'];
    }
    
    // Generate cache key based on filters and user role
    $cache_key = 'analytics_' . $_SESSION['role'] . '_' . md5(serialize($filters));
    
    // Try to get cached data first
    $cached_data = getCachedData($cache_key);
    
    if ($cached_data !== null) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $cached_data,
            'cached' => true,
            'cache_timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Get requested data type(s)
    $data_types = [];
    if (isset($_GET['types'])) {
        $types = explode(',', $_GET['types']);
        $data_types = array_intersect($types, [
            'global_stats', 
            'departments', 
            'top_performers', 
            'bottom_performers', 
            'status_distribution', 
            'trends', 
            'heatmaps',
            'comprehensive'
        ]);
    } else {
        $data_types = ['comprehensive']; // Default to comprehensive data
    }
    
    // Initialize response data
    $response_data = [];
    
    // Fetch requested data types
    if (in_array('comprehensive', $data_types)) {
        // Get comprehensive analytics (includes all data)
        $response_data = $analytics->getComprehensiveAnalytics($filters);
    } else {
        // Get specific data types
        if (in_array('global_stats', $data_types)) {
            $response_data['global_stats'] = $analytics->getGlobalStats($filters);
        }
        
        if (in_array('departments', $data_types)) {
            $response_data['departments'] = $analytics->getDepartmentAggregates($filters);
        }
        
        if (in_array('top_performers', $data_types)) {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $top_bottom = $analytics->getTopBottomEmployees($filters, $limit);
            $response_data['top_performers'] = $top_bottom['top_performers'];
        }
        
        if (in_array('bottom_performers', $data_types)) {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $top_bottom = $analytics->getTopBottomEmployees($filters, $limit);
            $response_data['bottom_performers'] = $top_bottom['bottom_performers'];
        }
        
        if (in_array('status_distribution', $data_types)) {
            $response_data['status_distribution'] = $analytics->getStatusDistribution($filters);
        }
        
        if (in_array('trends', $data_types)) {
            $period = isset($_GET['period']) && in_array($_GET['period'], ['monthly', 'quarterly']) 
                ? $_GET['period'] 
                : 'monthly';
            $response_data['trends'] = [
                $period => $analytics->getTrendData($period, $filters)
            ];
        }
        
        if (in_array('heatmaps', $data_types)) {
            $matrix_type = isset($_GET['matrix_type']) && in_array($_GET['matrix_type'], ['competency', 'score_band'])
                ? $_GET['matrix_type']
                : 'competency';
            $response_data['heatmaps'] = [
                'competency_matrix' => $analytics->getHeatmapMatrix('competency', $filters),
                'score_band_matrix' => $analytics->getHeatmapMatrix('score_band', $filters)
            ];
        }
        
        // Add metadata
        $response_data['filters_applied'] = $filters;
        $response_data['generated_at'] = date('Y-m-d H:i:s');
    }
    
    // Cache the response for 5 minutes
    setCachedData($cache_key, $response_data, 300);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $response_data,
        'cached' => false,
        'cache_timestamp' => time(),
        'query_params' => $_GET
    ], JSON_UNESCAPED_UNICODE);
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Bad Request',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database Error',
        'message' => 'A database error occurred while fetching analytics data'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Get cached data using APCu if available, otherwise use session
 */
function getCachedData(string $key) {
    // Try APCu first (more efficient)
    if (function_exists('apcu_fetch')) {
        $data = apcu_fetch($key, $success);
        if ($success) {
            return $data;
        }
    }
    
    // Fallback to session storage
    if (!isset($_SESSION['analytics_cache'])) {
        $_SESSION['analytics_cache'] = [];
    }
    
    if (isset($_SESSION['analytics_cache'][$key])) {
        $cached = $_SESSION['analytics_cache'][$key];
        if ($cached['expires'] > time()) {
            return $cached['data'];
        } else {
            unset($_SESSION['analytics_cache'][$key]);
        }
    }
    
    return null;
}

/**
 * Store cached data using APCu if available, otherwise use session
 */
function setCachedData(string $key, $data, int $ttl = 300) {
    // Try APCu first (more efficient)
    if (function_exists('apcu_store')) {
        apcu_store($key, $data, $ttl);
    }
    
    // Also store in session as fallback
    if (!isset($_SESSION['analytics_cache'])) {
        $_SESSION['analytics_cache'] = [];
    }
    
    $_SESSION['analytics_cache'][$key] = [
        'data' => $data,
        'expires' => time() + $ttl
    ];
    
    // Clean up expired cache entries
    foreach ($_SESSION['analytics_cache'] as $cache_key => $cached) {
        if ($cached['expires'] <= time()) {
            unset($_SESSION['analytics_cache'][$cache_key]);
        }
    }
}

/**
 * Response Schema Documentation
 * 
 * COMPREHENSIVE ANALYTICS RESPONSE:
 * {
 *   "success": true,
 *   "data": {
 *     "global_stats": {
 *       "total_evaluations": 4,
 *       "approved_count": 1,
 *       "pending_count": 1,
 *       "rejected_count": 1,
 *       "draft_count": 1,
 *       "completion_rate": 25.0,
 *       "overall_average": 85.0,
 *       "total_employees": 4
 *     },
 *     "departments": [
 *       {
 *         "id": 1,
 *         "name_ar": "الموارد البشرية",
 *         "evaluation_count": 2,
 *         "avg_score": 75.5,
 *         "approved_count": 1,
 *         "total_employees": 2,
 *         "completion_rate": 50.0,
 *         "ranking": 1
 *       }
 *     ],
 *     "top_performers": [
 *       {
 *         "id": 1,
 *         "employee_name": "موظف ممتاز",
 *         "department": "قسم التقنية",
 *         "manager_name": "مدير القسم",
 *         "total_score": 95.0,
 *         "status": "approved",
 *         "updated_at": "2025-12-10 22:30:00",
 *         "score_delta": 25.0
 *       }
 *     ],
 *     "bottom_performers": [...],
 *     "status_distribution": [
 *       {
 *         "status": "approved",
 *         "count": 1,
 *         "percentage": 25.0
 *       },
 *       {
 *         "status": "submitted",
 *         "count": 1,
 *         "percentage": 25.0
 *       }
 *     ],
 *     "trends": {
 *       "monthly": [
 *         {
 *           "period": "October 2025",
 *           "evaluation_count": 2,
 *           "avg_score": 75.5,
 *           "approved_count": 1
 *         }
 *       ],
 *       "quarterly": [...]
 *     },
 *     "heatmaps": {
 *       "competency_matrix": {
 *         "matrix_type": "competency",
 *         "categories": ["المظهر", "العمل"],
 *         "data": {
 *           "قسم التقنية": {
 *             "المظهر": 85.0,
 *             "العمل": 90.0
 *           }
 *         }
 *       },
 *       "score_band_matrix": {
 *         "matrix_type": "score_band",
 *         "categories": ["ممتاز (90-100)", "جيد جداً (80-89)"],
 *         "data": {
 *           "قسم التقنية": {
 *             "ممتاز (90-100)": 2,
 *             "جيد جداً (80-89)": 1
 *           }
 *         }
 *       }
 *     },
 *     "filters_applied": {
 *       "cycle_id": 1,
 *       "department_ids": [1, 2],
 *       "evaluator_role": "manager"
 *     },
 *     "generated_at": "2025-12-10 23:30:00"
 *   },
 *   "cached": false,
 *   "cache_timestamp": 1733796600
 * }
 * 
 * ERROR RESPONSE:
 * {
 *   "error": "Unauthorized",
 *   "message": "Access denied. Admin or manager role required."
 * }
 * 
 * QUERY PARAMETERS:
 * - cycle_id: Specific evaluation cycle ID (optional, uses active cycle if not provided)
 * - department_ids: Comma-separated list of department IDs (optional)
 * - evaluator_role: "manager" or "supervisor" (optional)
 * - date_from: Start date in YYYY-MM-DD format (optional)
 * - date_to: End date in YYYY-MM-DD format (optional)
 * - min_score: Minimum score filter (optional)
 * - max_score: Maximum score filter (optional)
 * - types: Comma-separated list of data types (optional, defaults to "comprehensive")
 * - limit: Number of top/bottom performers to return (optional, default 10)
 * - period: "monthly" or "quarterly" for trends (optional, default "monthly")
 * - matrix_type: "competency" or "score_band" for heatmaps (optional, default "competency")
 * 
 * AVAILABLE DATA TYPES:
 * - comprehensive: All analytics data (default)
 * - global_stats: Basic counts and averages
 * - departments: Department-wise aggregates
 * - top_performers: Highest scoring employees
 * - bottom_performers: Lowest scoring employees
 * - status_distribution: Status breakdown for charts
 * - trends: Time-series data
 * - heatmaps: Matrix data for visualizations
 */