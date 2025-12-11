# Analytics Data API Documentation

## Overview

The Analytics Data API provides a comprehensive, secure, and cached data layer for the HR Performance Evaluation System's dashboard. It exposes all evaluation KPIs needed for visualization and reporting.

## Components

### 1. AnalyticsService.php (`app/core/AnalyticsService.php`)

A reusable analytics data layer that provides methods for:

- **Global Statistics**: Total evaluations, status counts, completion rates, averages
- **Department Aggregates**: Average scores, completion percentages, rankings
- **Top/Bottom Performers**: Employee rankings with manager info and score deltas
- **Status Distribution**: Data buckets for chart visualizations
- **Trend Analysis**: Monthly and quarterly time-series data
- **Heatmap Matrices**: Department x competency/score band visualizations

### 2. Analytics Data API (`public/admin/api/analytics-data.php`)

REST API endpoint that:
- Authenticates admin/manager sessions
- Validates incoming GET parameters
- Returns JSON responses with proper HTTP codes
- Implements 5-minute caching (APCu or session fallback)
- Provides comprehensive error handling

## Features

### Security
- ✅ Session-based authentication (admin/manager only)
- ✅ All queries use prepared statements (SQL injection protection)
- ✅ Input sanitization and validation
- ✅ Proper error handling without data exposure

### Performance
- ✅ Caching system (5-minute TTL)
- ✅ APCu primary storage with session fallback
- ✅ Efficient SQL queries with proper indexing
- ✅ Flexible data selection (only fetch needed data types)

### Filtering
- **cycle_id**: Filter by evaluation cycle (falls back to active cycle)
- **department_ids[]**: Multiple department filtering
- **evaluator_role**: Manager/Supervisor filtering
- **date_range**: From/to date filtering
- **min/max_score**: Score range filtering

## Usage Examples

### Basic Comprehensive Data
```javascript
// Fetch all analytics data
fetch('/public/admin/api/analytics-data.php')
  .then(response => response.json())
  .then(data => {
    console.log('Global Stats:', data.data.global_stats);
    console.log('Departments:', data.data.departments);
    console.log('Top Performers:', data.data.top_performers);
  });
```

### Filtered Data
```javascript
// Filter by department and date range
fetch('/public/admin/api/analytics-data.php?cycle_id=1&department_ids=1,2&date_from=2025-01-01&date_to=2025-12-31')
  .then(response => response.json())
  .then(data => {
    // Use filtered data
  });
```

### Specific Data Types
```javascript
// Get only status distribution for charts
fetch('/public/admin/api/analytics-data.php?types=status_distribution')
  .then(response => response.json())
  .then(data => {
    console.log('Status Distribution:', data.data.status_distribution);
  });
```

## Response Schema

### Success Response
```json
{
  "success": true,
  "data": {
    "global_stats": { ... },
    "departments": [ ... ],
    "top_performers": [ ... ],
    "bottom_performers": [ ... ],
    "status_distribution": [ ... ],
    "trends": {
      "monthly": [ ... ],
      "quarterly": [ ... ]
    },
    "heatmaps": {
      "competency_matrix": { ... },
      "score_band_matrix": { ... }
    },
    "filters_applied": { ... },
    "generated_at": "2025-12-10 23:30:00"
  },
  "cached": false,
  "cache_timestamp": 1733796600
}
```

### Error Response
```json
{
  "error": "Unauthorized",
  "message": "Access denied. Admin or manager role required."
}
```

## Front-End Integration

The API is designed to work seamlessly with modern JavaScript frameworks:

```javascript
class AnalyticsAPI {
  constructor(baseUrl = '/public/admin/api/analytics-data.php') {
    this.baseUrl = baseUrl;
  }
  
  async getData(filters = {}, dataTypes = ['comprehensive']) {
    const params = new URLSearchParams();
    
    // Add filters
    Object.entries(filters).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        params.append(key, value.join(','));
      } else {
        params.append(key, value);
      }
    });
    
    if (dataTypes.length > 0) {
      params.append('types', dataTypes.join(','));
    }
    
    const response = await fetch(`${this.baseUrl}?${params}`);
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.message || 'API Error');
    }
    
    return data;
  }
  
  async getGlobalStats(filters = {}) {
    return this.getData(filters, ['global_stats']);
  }
  
  async getDepartmentData(filters = {}) {
    return this.getData(filters, ['departments']);
  }
  
  async getTopPerformers(filters = {}, limit = 10) {
    return this.getData({...filters, limit}, ['top_performers']);
  }
}

// Usage
const api = new AnalyticsAPI();

async function loadDashboard() {
  try {
    const data = await api.getData({cycle_id: 1}, ['comprehensive']);
    renderDashboard(data.data);
  } catch (error) {
    console.error('Failed to load analytics:', error);
  }
}
```

## Caching Strategy

- **Primary Storage**: APCu (fast, shared memory)
- **Fallback**: PHP sessions
- **TTL**: 300 seconds (5 minutes)
- **Cache Key**: Based on user role + filters hash
- **Automatic Cleanup**: Expired entries removed on access

## Error Handling

The API provides detailed error responses:

- **401 Unauthorized**: Invalid or missing authentication
- **400 Bad Request**: Invalid filter parameters
- **405 Method Not Allowed**: Non-GET requests
- **500 Internal Server Error**: Database or system errors

## Database Integration

The service integrates seamlessly with the existing database schema:

- **Tables**: employee_evaluations, users, departments, evaluation_cycles, evaluation_fields, evaluation_responses
- **Relationships**: Properly joined using foreign keys
- **Indexes**: Leverages existing database indexes for performance
- **Fallback Logic**: Uses active evaluation cycle when no cycle_id specified

## Performance Considerations

1. **Efficient Queries**: Uses proper JOINs and GROUP BY clauses
2. **Indexed Columns**: Queries leverage existing database indexes
3. **Selective Data**: Only fetches required fields and data types
4. **Caching**: Reduces database load for repeated requests
5. **Pagination Ready**: Can be extended for large datasets

## Future Enhancements

- Export to Excel/PDF endpoints
- Real-time updates via WebSockets
- Advanced filtering (employee roles, job titles)
- Custom dashboard widget support
- Historical data comparison
- Predictive analytics integration