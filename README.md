# Al-Buraq Airlines HR Performance Evaluation System

A comprehensive Arabic-language HR performance evaluation system designed for Al-Buraq Airlines.

## Features

### Core Functionality
- **Employee Performance Evaluations**: Comprehensive evaluation system with multiple evaluators
- **Multi-Level Evaluation**: Support for supervisor and manager evaluations
- **Evaluation Cycles**: Manage multiple evaluation cycles (years)
- **Department Management**: Organize evaluations by department
- **User Management**: Admin, manager, supervisor, and employee roles

### Advanced Analytics
- **Analytics Dashboard**: Real-time performance metrics and insights
- **Analytics API**: REST API for dashboard data with filtering and caching
- **Comprehensive Reporting**: Detailed evaluation reports and statistics

### Multi-Format Exports (NEW)
- **Excel Export (.xlsx)**: Professional reports with summary sheets and KPI cards
- **PDF Export**: Printable reports with cover pages and company branding
- **Word Export (.docx)**: Detailed evaluations with RTL Arabic formatting

### Arabic Localization
- **Full RTL Support**: Right-to-left text direction across all formats
- **Arabic Interface**: Complete Arabic language UI
- **Arabic Reporting**: All exports with proper Arabic formatting
- **Company Branding**: Logo and company name integration

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL/MariaDB database
- Composer (for dependency management)

### Setup Steps

1. **Clone the repository**
```bash
git clone <repository-url>
cd al-buraq-hr
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure database**
```bash
# Update database credentials in app/core/db.php or set environment variables
export DB_HOST=127.0.0.1
export DB_NAME=al_b
export DB_USER=root
export DB_PASS=
```

4. **Import database schema**
```bash
mysql -u root al_b < al_b.sql
```

5. **Set permissions**
```bash
chmod 755 storage/uploads
chmod 755 storage/backups
```

## Usage

### Accessing the Application
- **Admin Dashboard**: http://localhost/public/admin/dashboard.php
- **Reports & Statistics**: http://localhost/public/admin/reports.php
- **Analytics Dashboard**: http://localhost/public/admin/analytics-dashboard.php

### Exporting Reports
1. Navigate to **Reports & Statistics**
2. Apply filters (evaluation cycle, department, status)
3. Click **Excel**, **PDF**, or **Word** to download report

### Default Login Credentials
(See system administrator for credentials)

## Project Structure

```
al-buraq-hr/
├── app/
│   └── core/
│       ├── db.php                      # Database connection
│       ├── ExportService.php           # Multi-format export service
│       ├── AnalyticsService.php        # Analytics data layer
│       ├── Logger.php                  # Activity logging
│       └── Mailer.php                  # Email notifications
├── public/
│   ├── admin/
│   │   ├── dashboard.php              # Admin dashboard
│   │   ├── reports.php                # Reports & exports
│   │   ├── analytics-dashboard.php    # Analytics dashboard
│   │   ├── api/                       # REST API endpoints
│   │   ├── users.php                  # User management
│   │   ├── departments.php            # Department management
│   │   ├── cycles.php                 # Evaluation cycles
│   │   └── ...
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   ├── index.php                      # Homepage
│   ├── login.php                      # Login page
│   └── generate_pdf.php               # Individual evaluation PDF
├── storage/
│   ├── uploads/                       # Company logos and assets
│   └── backups/                       # Database backups
├── vendor/                            # Composer dependencies
├── composer.json                      # Composer configuration
├── composer.lock                      # Composer lock file
├── al_b.sql                          # Database schema
├── README.md                          # This file
├── EXPORT_SYSTEM_DOCUMENTATION.md    # Export system documentation
└── ANALYTICS_API_DOCUMENTATION.md    # Analytics API documentation
```

## Configuration

### System Settings
Edit system settings in **Admin → Settings** (الإعدادات):
- Company name (شركة البراق للنقل الجوي)
- Company logo
- Email server settings
- Default evaluation cycle

### Database Configuration
Edit environment variables or `app/core/db.php`:
```php
DB_HOST=127.0.0.1
DB_NAME=al_b
DB_USER=root
DB_PASS=
```

## Dependencies

### PHP Packages (via Composer)
- **phpoffice/phpspreadsheet** ^1.28 - Excel file generation
- **mpdf/mpdf** ^8.1 - PDF generation with Arabic support
- **phpoffice/phpword** ^1.1 - Word document generation

Install all dependencies:
```bash
composer install
```

## Documentation

- **[Export System Documentation](EXPORT_SYSTEM_DOCUMENTATION.md)** - Complete guide for multi-format exports
- **[Analytics API Documentation](ANALYTICS_API_DOCUMENTATION.md)** - REST API reference for analytics data

## Security Features

- **Session Authentication**: All pages require valid user sessions
- **Input Validation**: All user inputs are validated and sanitized
- **Prepared Statements**: All database queries use prepared statements
- **CSRF Protection**: CSRF tokens for critical operations
- **Password Hashing**: Secure password hashing for user accounts
- **Activity Logging**: All user actions are logged
- **Role-Based Access Control**: Fine-grained permission system

## Common Tasks

### Export Reports
```
1. Go to Reports & Statistics (التقارير والإحصائيات)
2. Select filters: Cycle, Department, Status
3. Click Excel/PDF/Word button
4. File downloads automatically
```

### View Analytics
```
1. Go to Analytics Dashboard
2. View global statistics and trends
3. Filter by department or cycle
4. Export data via API or export buttons
```

### Manage Evaluation Cycles
```
1. Go to Evaluation Cycles
2. Create new cycle for evaluation year
3. Add evaluation fields and criteria
4. Set cycle status (active/inactive)
```

### Create Users
```
1. Go to User Management
2. Click "Add New User"
3. Assign role (admin/manager/supervisor/employee)
4. System sends login credentials via email
```

## Troubleshooting

### Composer Dependencies Not Found
```
Error: vendor/autoload.php not found
Solution: Run `composer install` in project root
```

### Arabic Text Not Displaying
```
Problem: Garbled Arabic text
Solution: Verify database charset is utf8mb4
Check browser/file encoding is UTF-8
```

### Export File Download Issues
```
Problem: Empty or corrupted download files
Solution: Check storage/uploads directory permissions
Verify no output before export (no echo statements)
Check error_log for PHP errors
```

### Database Connection Error
```
Error: Cannot connect to database
Solution: Verify database credentials in app/core/db.php
Check MySQL server is running
Verify database 'al_b' exists
```

## Performance Optimization

### Large Report Exports
For reports with >10,000 records:
- Filter by evaluation cycle
- Filter by specific department
- Use PDF for better performance than Word

### Caching
- Analytics API responses cached for 5 minutes
- Use APCu or session storage for caching

### Database
- Create indexes on frequently queried columns
- Archive old evaluation data
- Optimize database queries in reports

## API Reference

### Export Service
```php
use App\ExportService;

$exporter = new ExportService();
$exporter->setFilters($cycle_id, $dept_id, $status);
$exporter->exportExcel();    // Download Excel
$exporter->exportPdf();      // Download PDF
$exporter->exportWord();     // Download Word
```

### Analytics Service
See [ANALYTICS_API_DOCUMENTATION.md](ANALYTICS_API_DOCUMENTATION.md) for complete API reference.

## Support & Maintenance

### Regular Maintenance
- Backup database weekly: `mysqldump -u root al_b > backup.sql`
- Clean old logs: Archive logs older than 30 days
- Update dependencies: `composer update`

### Reporting Issues
- Check error logs: `/var/log/php-errors.log`
- Review activity logs in Admin Dashboard
- Check database for data integrity

## Version Information

- **Current Version**: 1.0.0
- **Last Updated**: December 11, 2024
- **Database Schema**: al_b.sql
- **PHP Compatibility**: 7.4+

## License

© 2024 Al-Buraq Airlines. All rights reserved.

## Contributors

- Development Team
- UI/UX Design
- Arabic Localization
- Database Architecture

---

For more detailed documentation, see the included documentation files in the project root.