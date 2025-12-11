# Multi-Format Export System Documentation

## Overview

The Al-Buraq HR Performance Evaluation System now includes a comprehensive multi-format export system that allows administrators to export evaluation reports in **Excel (.xlsx)**, **PDF**, and **Word (.docx)** formats with professional Arabic formatting and RTL support.

## Features

### 1. **Excel Export (.xlsx)**
- Professional company header with RTL formatting
- **Summary Sheet** with KPI cards rendered via merged cells:
  - Total evaluations count
  - Average score percentage
  - Highest and lowest scores
  - Status distribution (Approved, Rejected, Pending)
- **Details Sheet** with:
  - All evaluation records in tabular format
  - Alternating row colors for readability
  - Auto-sized columns
  - Proper RTL text direction
  - Professional header styling with color
- File: `evaluation_report_YYYY-MM-DD.xlsx`

### 2. **PDF Export**
- Professional cover page with company branding
- Company logo integration (if available)
- **Summary Section** with KPI cards displayed as styled boxes
- **Details Section** with comprehensive data table
- RTL-aware formatting using mPDF
- Proper font handling for Arabic text
- Professional table styling with borders and alternating colors
- Footer with generation timestamp
- File: `evaluation_report_YYYY-MM-DD.pdf`

### 3. **Word Export (.docx)**
- Professional document structure with:
  - RTL header with company name and branding
  - Main title and metadata
  - **Summary Section** with KPI table
  - **Details Section** with comprehensive data table
  - Professional footer
- Arabic text support with proper RTL formatting
- Alternating row colors in tables
- Formatted headers and footers
- File: `evaluation_report_YYYY-MM-DD.docx`

## Installation

### Prerequisites
Composer is required to install PHP dependencies.

### Setup Steps

1. **Install Composer dependencies:**
   ```bash
   cd /path/to/project
   composer install
   ```

   This will install:
   - `phpoffice/phpspreadsheet` (v1.28+) - Excel export
   - `mpdf/mpdf` (v8.1+) - PDF export
   - `phpoffice/phpword` (v1.1+) - Word export

2. **Verify vendor autoload:**
   ```bash
   ls -la vendor/autoload.php
   ```

3. **Storage directory permissions:**
   ```bash
   chmod 755 storage/uploads
   ```

## Usage

### Accessing the Export Feature

1. Navigate to **Admin Dashboard → التقارير والإحصائيات** (Reports & Statistics)
2. Apply desired filters:
   - **دورة التقييم** (Evaluation Cycle)
   - **الإدارة** (Department)
   - **الحالة** (Status)
3. Click one of the export buttons:
   - **Excel** - Download as .xlsx file
   - **PDF** - Download as .pdf file
   - **Word** - Download as .docx file

### Filtered Data

All three export formats respect the same filters:
- **Evaluation Cycle**: Filter by year of evaluation
- **Department**: Filter by department (single or all)
- **Status**: Filter by evaluation status
  - موافق عليه (Approved)
  - مرفوض (Rejected)
  - بانتظار الاعتماد (Submitted/Pending)
  - مسودة (Draft)

### Data Included in Exports

Each export includes:
1. **Employee Information:**
   - Name
   - Email
   - Department
   - Evaluation Cycle Year

2. **Evaluation Details:**
   - Evaluator Name
   - Score (0-100)
   - Status (with Arabic translation)
   - Last Updated Date

3. **Summary Statistics:**
   - Total number of evaluations
   - Average score
   - Highest score
   - Lowest score
   - Status distribution

## API Reference

### ExportService Class

The `ExportService` class handles all export operations.

#### Location
```
app/core/ExportService.php
```

#### Usage Example
```php
<?php
require_once 'vendor/autoload.php';
require_once 'app/core/db.php';
require_once 'app/core/ExportService.php';

use App\ExportService;

// Create service instance
$exportService = new ExportService();

// Set filters (optional)
$exportService->setFilters(
    $cycle_id,      // Evaluation cycle ID
    $dept_id,       // Department ID
    $status         // Status (approved, rejected, submitted, draft)
);

// Export to Excel
$exportService->exportExcel();

// OR export to PDF
$exportService->exportPdf();

// OR export to Word
$exportService->exportWord();
```

#### Methods

**`setFilters($cycle, $dept, $status)`**
- Sets filter criteria for the export
- Parameters:
  - `$cycle` (string/int): Evaluation cycle ID, empty string for no filter
  - `$dept` (string/int): Department ID, empty string for no filter
  - `$status` (string): Status filter (approved/rejected/submitted/draft)

**`getFilteredData()`**
- Returns array of evaluation records matching current filters
- Returns: Array of records with keys:
  ```php
  [
    'name',           // Employee name
    'email',          // Email address
    'dept',           // Department name (Arabic)
    'year',           // Evaluation year
    'evaluator',      // Evaluator name
    'total_score',    // Score (0-100 or null)
    'status',         // Status code (draft, submitted, approved, rejected)
    'updated_at',     // Last updated timestamp
    'employee_id',    // Employee database ID
    'cycle_id',       // Cycle database ID
    'eval_id',        // Evaluation database ID
    'evaluator_role'  // Role (manager or supervisor)
  ]
  ```

**`getSummaryStats()`**
- Returns summary statistics for current filters
- Returns: Array with keys:
  ```php
  [
    'total_reports',    // Total evaluation count
    'avg_score',        // Average score
    'max_score',        // Highest score
    'min_score',        // Lowest score
    'approved_count',   // Count of approved evaluations
    'rejected_count',   // Count of rejected evaluations
    'submitted_count'   // Count of submitted/pending evaluations
  ]
  ```

**`exportExcel()`**
- Generates and downloads Excel file
- File name: `evaluation_report_YYYY-MM-DD.xlsx`
- Automatically exits after download
- Features:
  - RTL formatting
  - Summary sheet with KPI cards
  - Details sheet with styled table
  - Auto-sized columns

**`exportPdf()`**
- Generates and downloads PDF file
- File name: `evaluation_report_YYYY-MM-DD.pdf`
- Automatically exits after download
- Features:
  - Professional cover page with logo
  - Summary section with KPI cards
  - Details table with RTL support
  - Company branding

**`exportWord()`**
- Generates and downloads Word document
- File name: `evaluation_report_YYYY-MM-DD.docx`
- Automatically exits after download
- Features:
  - RTL document with Arabic headers
  - KPI summary table
  - Professional data table
  - Header and footer

## Security

All export operations include security measures:

1. **Session Authentication:**
   - Only admin users can access export functionality
   - Session validation on each request

2. **Input Validation:**
   - All filter parameters are validated and sanitized
   - Only valid IDs are accepted

3. **Database Security:**
   - All queries use prepared statements
   - Protection against SQL injection

4. **Output Handling:**
   - Output buffer cleaned before file download
   - Proper MIME types set
   - File downloads trigger immediately

## Customization

### Company Information

Export documents use system settings for:
- Company name (default: "شركة البراق للنقل الجوي")
- Logo path (stored in `storage/uploads/`)

To customize:
1. Go to **Admin → الإعدادات** (Settings)
2. Update company name and logo path
3. These changes apply to all subsequent exports

### Styling

#### Excel
Modify colors and styling in `ExportService::exportExcel()`:
- Header fill color: `'0070C0'` (Blue)
- Alternating row color: `'F2F2F2'` (Light gray)
- KPI card colors: Various

#### PDF
Modify HTML/CSS in `ExportService::exportPdf()`:
- Color scheme defined in `<style>` section
- Font: sans-serif (RTL-compatible)

#### Word
Modify formatting in `ExportService::exportWord()`:
- Header color: `'0070C0'`
- Table styling via cell properties
- Font size and colors

## Performance Considerations

### Large Datasets
- For reports with >10,000 records, consider limiting by:
  - Evaluation cycle
  - Department
  - Date range

### Memory Usage
- Excel export: ~2MB per 10,000 records
- PDF export: ~3MB per 10,000 records
- Word export: ~1.5MB per 10,000 records

### Caching
- Exports are generated on-demand
- No caching is performed (fresh data on each export)

## Troubleshooting

### Common Issues

**1. Composer dependencies not installed**
```
Error: vendor/autoload.php not found
Solution: Run `composer install` in project root
```

**2. Arabic text not displaying correctly**
```
Problem: Garbled Arabic text in PDF
Solution: Verify mPDF is installed with `composer show mpdf/mpdf`
```

**3. File download issues**
```
Problem: Corrupted or incomplete file
Solution: Check output buffer - ensure no content echoed before export
Clear any ob_start() calls that might interfere
```

**4. Permission errors**
```
Error: Cannot write to storage directory
Solution: Run `chmod 755 storage/uploads/` and ensure www-user owns the directory
```

### Debug Mode

To debug export issues, add temporary logging:
```php
// In ExportService, before export methods
error_log('Export started: ' . date('Y-m-d H:i:s'));
error_log('Filter cycle: ' . $this->filters['cycle']);
error_log('Filter dept: ' . $this->filters['dept']);
```

## File Structure

```
project/
├── app/
│   └── core/
│       ├── ExportService.php      # Main export service class
│       ├── db.php                 # Database connection
│       └── AnalyticsService.php   # Analytics data layer
├── public/
│   └── admin/
│       ├── reports.php            # Reports page with export UI
│       └── ...
├── storage/
│   └── uploads/                   # Company logo and assets
├── vendor/                        # Composer dependencies
│   ├── phpoffice/
│   │   ├── phpspreadsheet/       # Excel library
│   │   └── phpword/              # Word library
│   └── mpdf/mpdf/                # PDF library
├── composer.json                 # Composer dependencies
└── EXPORT_SYSTEM_DOCUMENTATION.md
```

## Version History

### v1.0.0 (2024-12-11)
- Initial release
- Excel export with summary and details sheets
- PDF export with professional formatting
- Word export with RTL support
- Full Arabic language support
- Filter integration (cycle, department, status)
- KPI cards and summary statistics

## Support

For issues or feature requests:
1. Check the Troubleshooting section above
2. Review the API Reference for usage examples
3. Verify all dependencies are installed: `composer install`

## Dependencies

This export system requires:
- **phpoffice/phpspreadsheet** ^1.28 - Excel file generation
- **mpdf/mpdf** ^8.1 - PDF file generation  
- **phpoffice/phpword** ^1.1 - Word document generation
- **PHP** ^7.4 - Server-side language

All dependencies are automatically installed via Composer.

---

Last Updated: 2024-12-11
