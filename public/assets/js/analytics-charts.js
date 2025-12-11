/**
 * Analytics Charts Manager
 * Handles initialization and updates of charts on the analytics dashboard.
 * Requires Chart.js v4+ and chartjs-chart-matrix plugin.
 */

const AnalyticsCharts = (function() {
    // Chart instances storage
    const charts = {
        status: null,
        trends: null,
        department: null,
        radar: null,
        heatmap: null
    };

    // Configuration defaults
    Chart.defaults.font.family = "'Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'";
    Chart.defaults.color = '#6c757d';
    Chart.defaults.scale.grid.color = '#e9ecef';
    
    // RTL Support for Tooltips and Legends
    Chart.defaults.plugins.tooltip.rtl = true;
    Chart.defaults.plugins.tooltip.textDirection = 'rtl';
    Chart.defaults.plugins.legend.rtl = true;
    Chart.defaults.plugins.legend.textDirection = 'rtl';

    /**
     * Initialize all charts
     */
    function init() {
        // Initial setup if needed
    }

    /**
     * Destroy all existing charts to prevent memory leaks
     */
    function destroyCharts() {
        Object.keys(charts).forEach(key => {
            if (charts[key]) {
                charts[key].destroy();
                charts[key] = null;
            }
        });
    }

    /**
     * Update all charts with new data
     * @param {Object} data - The full API response data
     */
    function updateCharts(data) {
        if (!data) return;

        updateStatusChart(data.status_distribution);
        updateTrendsChart(data.trends);
        updateDepartmentChart(data.departments);
        updateRadarChart(data.heatmaps?.competency_matrix);
        updateHeatmapChart(data.heatmaps);
    }

    /**
     * 1. Status Distribution Doughnut Chart
     */
    function updateStatusChart(data) {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;

        if (charts.status) charts.status.destroy();

        if (!data || data.length === 0) {
            showFallback(ctx, 'لا توجد بيانات');
            return;
        }

        const labels = data.map(item => translateStatus(item.status));
        const values = data.map(item => item.count);
        const colors = data.map(item => getStatusColor(item.status));

        charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    }

    /**
     * 2. Trends Line Chart
     */
    function updateTrendsChart(data) {
        const ctx = document.getElementById('trendsChart');
        if (!ctx) return;

        if (charts.trends) charts.trends.destroy();

        // Check if we have monthly or quarterly data
        const trendData = data?.monthly || data?.quarterly || [];
        
        if (trendData.length === 0) {
            showFallback(ctx, 'لا توجد بيانات اتجاهات');
            return;
        }

        const labels = trendData.map(item => item.period);
        const scores = trendData.map(item => item.avg_score);
        const counts = trendData.map(item => item.evaluation_count);

        charts.trends = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'متوسط الأداء',
                        data: scores,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        yAxisID: 'y',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'عدد التقييمات',
                        data: counts,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        yAxisID: 'y1',
                        borderDash: [5, 5],
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'النقاط' },
                        min: 0,
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'العدد' },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }

    /**
     * 3. Department Comparison Horizontal Bar Chart
     */
    function updateDepartmentChart(data) {
        const ctx = document.getElementById('departmentChart');
        if (!ctx) return;

        if (charts.department) charts.department.destroy();

        if (!data || data.length === 0) {
            showFallback(ctx, 'لا توجد بيانات إدارات');
            return;
        }

        // Sort by average score
        const sortedData = [...data].sort((a, b) => b.avg_score - a.avg_score);
        
        const labels = sortedData.map(item => item.name_ar);
        const scores = sortedData.map(item => item.avg_score);
        const completionRates = sortedData.map(item => item.completion_rate);

        charts.department = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'متوسط الأداء',
                        data: scores,
                        backgroundColor: function(context) {
                            const value = context.raw;
                            if (value >= 90) return '#198754'; // Success (Green)
                            if (value >= 80) return '#0d6efd'; // Primary (Blue)
                            if (value >= 70) return '#ffc107'; // Warning (Yellow)
                            return '#dc3545'; // Danger (Red)
                        },
                        borderRadius: 4
                    },
                    {
                        label: 'معدل الإنجاز %',
                        data: completionRates,
                        backgroundColor: '#ffc107',
                        borderRadius: 4,
                        hidden: true // Hidden by default, toggleable
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    /**
     * 4. Competency Radar Chart
     */
    function updateRadarChart(matrixData) {
        const ctx = document.getElementById('radarChart');
        if (!ctx) return;

        if (charts.radar) charts.radar.destroy();

        if (!matrixData || !matrixData.categories || !matrixData.data) {
            showFallback(ctx, 'لا توجد بيانات جدارات');
            return;
        }

        const categories = matrixData.categories; // Competencies
        const departments = Object.keys(matrixData.data);
        
        // Limit to top 3 departments to avoid clutter + Company Average if possible
        const topDepartments = departments.slice(0, 3); 
        
        const datasets = topDepartments.map((dept, index) => {
            const data = categories.map(cat => matrixData.data[dept][cat] || 0);
            const color = getChartColor(index);
            
            return {
                label: dept,
                data: data,
                fill: true,
                backgroundColor: color.alpha,
                borderColor: color.solid,
                pointBackgroundColor: color.solid,
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: color.solid
            };
        });

        charts.radar = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: categories,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: {
                            display: true
                        },
                        suggestedMin: 0,
                        suggestedMax: 100
                    }
                }
            }
        });
    }

    /**
     * 5. Heatmap (Performance Matrix)
     */
    function updateHeatmapChart(heatmapsData) {
        const ctx = document.getElementById('heatmapChart');
        if (!ctx) return;

        if (charts.heatmap) charts.heatmap.destroy();
        
        // Prefer competency matrix, fall back to score band
        const matrixData = heatmapsData?.competency_matrix;

        if (!matrixData || !matrixData.categories || !matrixData.data) {
            showFallback(ctx, 'لا توجد بيانات للمصفوفة');
            return;
        }

        const competencies = matrixData.categories;
        const departments = Object.keys(matrixData.data);
        
        const dataPoints = [];
        
        departments.forEach((dept, y) => {
            competencies.forEach((comp, x) => {
                const value = matrixData.data[dept][comp] || 0;
                dataPoints.push({
                    x: comp,
                    y: dept,
                    v: value
                });
            });
        });

        charts.heatmap = new Chart(ctx, {
            type: 'matrix',
            data: {
                datasets: [{
                    label: 'Matrix',
                    data: dataPoints,
                    backgroundColor(c) {
                        const value = c.dataset.data[c.dataIndex].v;
                        const alpha = (value - 50) / 50; // Normalize 50-100 to 0-1 (roughly)
                        return `rgba(13, 110, 253, ${Math.max(0.1, alpha)})`;
                    },
                    borderColor(c) {
                         const value = c.dataset.data[c.dataIndex].v;
                         const alpha = (value - 50) / 50;
                         return `rgba(13, 110, 253, ${Math.max(0.2, alpha)})`;
                    },
                    borderWidth: 1,
                    width: ({chart}) => (chart.chartArea || {}).width / competencies.length - 2,
                    height: ({chart}) => (chart.chartArea || {}).height / departments.length - 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title() { return ''; },
                            label(context) {
                                const v = context.raw;
                                return `${v.x} - ${v.y}: ${v.v.toFixed(1)}%`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'category',
                        labels: competencies,
                        ticks: {
                            display: true
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'category',
                        labels: departments,
                        offset: true,
                        ticks: {
                            display: true
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /**
     * Helpers
     */
    function translateStatus(status) {
        const map = {
            'draft': 'مسودة',
            'pending': 'معلق',
            'submitted': 'تم الإرسال',
            'approved': 'معتمد',
            'rejected': 'مرفوض'
        };
        return map[status] || status;
    }

    function getStatusColor(status) {
        const map = {
            'draft': '#6c757d',      // Gray
            'pending': '#ffc107',    // Warning
            'submitted': '#0dcaf0',  // Info
            'approved': '#198754',   // Success
            'rejected': '#dc3545'    // Danger
        };
        return map[status] || '#6c757d';
    }

    function getChartColor(index) {
        const colors = [
            { solid: 'rgba(13, 110, 253, 1)', alpha: 'rgba(13, 110, 253, 0.2)' }, // Blue
            { solid: 'rgba(25, 135, 84, 1)', alpha: 'rgba(25, 135, 84, 0.2)' },   // Green
            { solid: 'rgba(255, 193, 7, 1)', alpha: 'rgba(255, 193, 7, 0.2)' },   // Yellow
            { solid: 'rgba(220, 53, 69, 1)', alpha: 'rgba(220, 53, 69, 0.2)' },   // Red
            { solid: 'rgba(13, 202, 240, 1)', alpha: 'rgba(13, 202, 240, 0.2)' }  // Cyan
        ];
        return colors[index % colors.length];
    }

    function showFallback(canvas, message) {
        const parent = canvas.parentElement;
        // Check if fallback already exists
        const existing = parent.querySelector('.chart-fallback');
        if (existing) existing.remove();
        
        // Hide canvas (or keep it but overlay?)
        // Better to clear context? 
        // We just don't create a chart.
        
        const div = document.createElement('div');
        div.className = 'chart-fallback d-flex justify-content-center align-items-center h-100 text-muted';
        div.innerHTML = `<div class="text-center"><i class="fas fa-chart-bar fa-2x mb-2"></i><br>${message}</div>`;
        
        // Ensure parent has relative positioning
        parent.style.position = 'relative';
        div.style.position = 'absolute';
        div.style.top = '0';
        div.style.left = '0';
        div.style.width = '100%';
        
        parent.appendChild(div);
    }

    // Public API
    return {
        init: init,
        update: updateCharts,
        destroy: destroyCharts
    };

})();

// Export for global usage
window.AnalyticsCharts = AnalyticsCharts;
