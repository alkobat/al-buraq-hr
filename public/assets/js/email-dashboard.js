/**
 * Email Dashboard JavaScript
 * Functions for dashboard interactions and utilities
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if using Bootstrap
    initializeTooltips();

    // Auto-refresh dashboard data (optional)
    // autoRefreshDashboard();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Auto-refresh dashboard every 5 minutes
 */
function autoRefreshDashboard(interval = 300000) {
    setInterval(function() {
        location.reload();
    }, interval);
}

/**
 * Format date to readable format
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA') + ' ' + date.toLocaleTimeString('ar-SA');
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text, buttonEl) {
    navigator.clipboard.writeText(text).then(function() {
        const originalText = buttonEl.innerHTML;
        buttonEl.innerHTML = '<i class="fas fa-check"></i> تم النسخ!';
        setTimeout(function() {
            buttonEl.innerHTML = originalText;
        }, 2000);
    }).catch(function(err) {
        console.error('Failed to copy:', err);
    });
}

/**
 * Export table to CSV
 */
function exportTableToCSV(filename) {
    const table = document.querySelector('table');
    if (!table) {
        alert('لم يتم العثور على جدول');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(function(row) {
        let rowData = [];
        const cells = row.querySelectorAll('td, th');

        cells.forEach(function(cell) {
            rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
        });

        csv.push(rowData.join(','));
    });

    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + csv.join('\n');
    downloadFile(csvContent, filename || 'export.csv');
}

/**
 * Download file
 */
function downloadFile(content, filename) {
    const link = document.createElement('a');
    link.setAttribute('href', content);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Filter table by column
 */
function filterTable(columnIndex, searchTerm) {
    const table = document.querySelector('table');
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const cell = row.querySelector(`td:nth-child(${columnIndex + 1})`);
        if (cell && cell.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Show message if no results
    if (visibleCount === 0) {
        const tBody = table.querySelector('tbody');
        if (!tBody.querySelector('.no-results-message')) {
            const messageRow = document.createElement('tr');
            messageRow.className = 'no-results-message';
            messageRow.innerHTML = `<td colspan="100%" class="text-center text-muted py-4">لا توجد نتائج مطابقة</td>`;
            tBody.appendChild(messageRow);
        }
    } else {
        const messageRow = table.querySelector('.no-results-message');
        if (messageRow) {
            messageRow.remove();
        }
    }
}

/**
 * Toggle column visibility
 */
function toggleColumnVisibility(columnIndex) {
    const table = document.querySelector('table');
    if (!table) return;

    const allCells = table.querySelectorAll(`th:nth-child(${columnIndex + 1}), td:nth-child(${columnIndex + 1})`);
    allCells.forEach(function(cell) {
        cell.style.display = cell.style.display === 'none' ? '' : 'none';
    });
}

/**
 * Sort table by column
 */
function sortTable(columnIndex, order = 'asc') {
    const table = document.querySelector('table tbody');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tr'));
    const isNumeric = !isNaN(parseFloat(rows[0]?.querySelector(`td:nth-child(${columnIndex + 1})`)?.textContent));

    rows.sort(function(a, b) {
        const aValue = a.querySelector(`td:nth-child(${columnIndex + 1})`)?.textContent.trim();
        const bValue = b.querySelector(`td:nth-child(${columnIndex + 1})`)?.textContent.trim();

        let comparison = 0;

        if (isNumeric) {
            comparison = parseFloat(aValue) - parseFloat(bValue);
        } else {
            comparison = aValue.localeCompare(bValue, 'ar');
        }

        return order === 'asc' ? comparison : -comparison;
    });

    rows.forEach(function(row) {
        table.appendChild(row);
    });
}

/**
 * Show loading state
 */
function showLoading(buttonEl) {
    buttonEl.disabled = true;
    const originalHTML = buttonEl.innerHTML;
    buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري المعالجة...';
    return originalHTML;
}

/**
 * Hide loading state
 */
function hideLoading(buttonEl, originalHTML) {
    buttonEl.disabled = false;
    buttonEl.innerHTML = originalHTML;
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info', duration = 3000) {
    const toastHTML = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-${type} text-white">
                <i class="fas fa-${getToastIcon(type)} me-2"></i>
                <strong class="me-auto">إشعار</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;

    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    const toastEl = document.createElement('div');
    toastEl.innerHTML = toastHTML;
    toastContainer.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl.querySelector('.toast'));
    toast.show();

    setTimeout(function() {
        toastEl.remove();
    }, duration);
}

/**
 * Get toast icon based on type
 */
function getToastIcon(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

/**
 * Create toast container
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

/**
 * Validate email
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Format number with thousands separator
 */
function formatNumber(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

/**
 * Calculate time difference
 */
function getTimeDifference(date1, date2) {
    const msPerDay = 24 * 60 * 60 * 1000;
    const msPerHour = 60 * 60 * 1000;
    const msPerMinute = 60 * 1000;

    const difference = Math.abs(date1 - date2);

    if (difference >= msPerDay) {
        return Math.floor(difference / msPerDay) + ' يوم';
    } else if (difference >= msPerHour) {
        return Math.floor(difference / msPerHour) + ' ساعة';
    } else if (difference >= msPerMinute) {
        return Math.floor(difference / msPerMinute) + ' دقيقة';
    } else {
        return 'للتو';
    }
}

/**
 * Debounce function for search
 */
function debounce(func, delay) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func(...args), delay);
    };
}

/**
 * Format bytes to human readable format
 */
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Get URL parameters
 */
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

/**
 * Confirm action dialog
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Export functions for use in other files
if (typeof window !== 'undefined') {
    window.emailDashboard = {
        formatDate,
        copyToClipboard,
        exportTableToCSV,
        filterTable,
        toggleColumnVisibility,
        sortTable,
        showLoading,
        hideLoading,
        showToast,
        isValidEmail,
        formatNumber,
        getTimeDifference,
        debounce,
        formatBytes,
        getUrlParameter,
        confirmAction
    };
}
