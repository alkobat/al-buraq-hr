# Testing Multi-Format Exports

This document provides testing instructions for the multi-format export functionality.

## Prerequisites

1. **Install Composer dependencies**:
   ```bash
   cd /path/to/project
   composer install
   ```

2. **Verify database connection**:
   - Check that the `al_b` database is set up
   - Verify database credentials in `app/core/db.php`
   - Confirm database has sample data for testing

3. **Admin access**:
   - Log in as admin user
   - Navigate to **Reports & Statistics** page

## Testing Steps

### 1. Excel Export Test

1. **Navigate to Reports**:
   - URL: `http://localhost/public/admin/reports.php`

2. **Apply filters** (optional):
   - Select an evaluation cycle
   - Select a department
   - Select a status (e.g., "approved")

3. **Click Excel button**:
   - Button labeled: `<i class="fas fa-file-excel"></i> Excel`
   - Location: Top right of page

4. **Verify download**:
   - File should download as `evaluation_report_YYYY-MM-DD.xlsx`
   - Open in Excel/LibreOffice Calc

5. **Check content**:
   - **Summary sheet**: 
     - Company name and header
     - KPI cards (Total, Average, Max, Min, Status counts)
   - **Details sheet**:
     - Header row with columns: الموظف, البريد الإلكتروني, etc.
     - Data rows matching selected filters
     - Alternating row colors (white/light gray)
     - RTL text direction

### 2. PDF Export Test

1. **Navigate to Reports**:
   - URL: `http://localhost/public/admin/reports.php`

2. **Apply filters** (optional):
   - Test with and without filters

3. **Click PDF button**:
   - Button labeled: `<i class="fas fa-file-pdf"></i> PDF`
   - Location: Top right of page (red button)

4. **Verify download**:
   - File should download as `evaluation_report_YYYY-MM-DD.pdf`
   - Open in PDF viewer

5. **Check content**:
   - **Cover page**:
     - Company name: "شركة البراق للنقل الجوي"
     - Report title: "تقرير تقييم الأداء"
     - Current date
     - Company logo (if configured)
   - **Summary section**:
     - KPI cards with statistics
     - Blue styled boxes for each metric
   - **Details table**:
     - Professional table with headers
     - Data rows with proper Arabic text
     - RTL formatting visible

### 3. Word Export Test

1. **Navigate to Reports**:
   - URL: `http://localhost/public/admin/reports.php`

2. **Apply filters** (optional):
   - Test with different filter combinations

3. **Click Word button**:
   - Button labeled: `<i class="fas fa-file-word"></i> Word`
   - Location: Top right of page (blue button)

4. **Verify download**:
   - File should download as `evaluation_report_YYYY-MM-DD.docx`
   - Open in Microsoft Word or LibreOffice Writer

5. **Check content**:
   - **Header**:
     - Company name in RTL format
     - "إدارة الموارد البشرية" subtitle
   - **Title section**:
     - "تقرير تقييم الأداء"
     - Date and subtitle
   - **Summary table**:
     - KPI data in table format
     - Professional styling
   - **Details table**:
     - All evaluation records
     - Proper columns and formatting
     - Alternating row colors
   - **Footer**:
     - Generation timestamp

## Filter Testing

Test each export with various filter combinations:

### Test Case 1: No Filters
- Click export without selecting any filters
- Should show all evaluations
- Statistics should reflect all data

### Test Case 2: Single Filter - Cycle
- Select evaluation cycle (e.g., 2024)
- Leave department and status empty
- Should show evaluations from that cycle only

### Test Case 3: Single Filter - Department
- Select department (e.g., "الموارد البشرية")
- Leave cycle and status empty
- Should show evaluations from that department only

### Test Case 4: Single Filter - Status
- Select status (e.g., "موافق عليه")
- Leave cycle and department empty
- Should show evaluations with that status only

### Test Case 5: Multiple Filters
- Select cycle, department, AND status
- All three should be applied together
- Should show only matching records

### Test Case 6: Empty Results
- Select filters that match no records
- Exports should work but contain minimal/no data
- Should not cause errors

## Data Validation Tests

### Test Case 1: Employee Names
- Verify all employee names display correctly
- Check Arabic names are properly formatted
- Ensure no character corruption

### Test Case 2: Department Names
- Check department Arabic names (name_ar field)
- Verify all departments display correctly
- Ensure proper sorting/grouping

### Test Case 3: Evaluation Scores
- Verify scores display as percentages (0-100)
- Check formatting is consistent
- Ensure null scores show as "—"

### Test Case 4: Status Translation
- Verify status translations:
  - `draft` → مسودة
  - `submitted` → بانتظار الاعتماد
  - `approved` → موافق عليه
  - `rejected` → مرفوض
- Check all statuses are properly translated

### Test Case 5: Summary Statistics
- Verify total count matches visible records
- Check average calculation is correct
- Validate min/max scores
- Confirm status counts match data

## Performance Tests

### Test Case 1: Small Dataset (< 100 records)
- All exports should complete in < 2 seconds
- File sizes should be reasonable:
  - Excel: < 1MB
  - PDF: < 2MB
  - Word: < 1MB

### Test Case 2: Medium Dataset (100-1000 records)
- Exports should complete in < 5 seconds
- File sizes should be proportional

### Test Case 3: Large Dataset (> 1000 records)
- Test with all evaluations across all cycles
- Should still complete without errors
- Monitor memory usage

## Error Handling Tests

### Test Case 1: Database Connection Error
- Temporarily disconnect database
- Click export button
- Should show error message (not blank page)
- Error should be in Arabic

### Test Case 2: Invalid Filter Parameters
- Manually modify URL to use invalid IDs
- Example: `?export=excel&cycle=99999&dept=-1`
- Should handle gracefully
- Should show appropriate error or no data

### Test Case 3: Missing Dependencies
- Comment out require_once for ExportService
- Click export button
- Should show clear error about missing class
- Should not crash server

### Test Case 4: Output Already Sent
- If page has any echo before export
- Should still work (ob_clean() should handle it)
- Or show clear error message

## Accessibility Tests

### Test Case 1: RTL Text Rendering
- All exports should properly display RTL text
- No left-to-right corruption
- Tables should align correctly with RTL

### Test Case 2: Arabic Font Support
- Open each export file
- Verify all Arabic text is readable
- Check special characters (ء, ة, ى, etc.)

### Test Case 3: Special Characters
- Test with employee names containing special characters
- Verify proper encoding in all formats
- Check diacritical marks if present

## Cross-Browser Testing

### Test Scenario: Different Browsers
Test exports work in:
- Chrome/Chromium
- Firefox
- Safari (if on Mac)
- Edge

Expected behavior:
- Files download with correct names
- Files can be opened in appropriate applications
- No browser errors in console

## Post-Export Testing

### Excel Files
1. Open in Excel 2016+ or LibreOffice Calc
2. Verify RTL formatting
3. Check column widths
4. Verify colors render correctly
5. Test copy/paste functionality

### PDF Files
1. Open in Adobe Reader or browser PDF viewer
2. Verify Arabic text quality
3. Check page breaks
4. Test printing from PDF
5. Verify all tables are visible

### Word Documents
1. Open in Microsoft Word 2016+ or LibreOffice
2. Verify RTL formatting
3. Check page layout
4. Edit document to ensure it's not read-only
5. Print to verify formatting

## Test Report Template

```
Test Date: _______________
Tester Name: ______________
Build Version: ____________

Export Type: [ ] Excel  [ ] PDF  [ ] Word

Filter Test:
  [ ] No filters
  [ ] Cycle only: __________
  [ ] Department only: _____
  [ ] Status only: ________
  [ ] All filters combined

Results:
  [ ] File downloaded successfully
  [ ] File can be opened
  [ ] Content is correct
  [ ] Formatting is correct
  [ ] Arabic text displays properly
  [ ] All statistics are accurate

Issues Found:
_________________________________
_________________________________

Pass: [ ] PASS  [ ] FAIL

Notes:
_________________________________
_________________________________
```

## Automated Testing (Future)

Recommended automated tests to implement:

```php
// Example test structure
class ExportServiceTest extends TestCase {
    
    public function testExcelExportWithFilters() {
        $service = new ExportService();
        $service->setFilters(1, 2, 'approved');
        $data = $service->getFilteredData();
        $this->assertNotEmpty($data);
        // Assert all records match filters
    }
    
    public function testPdfExportDownloadHeaders() {
        // Verify correct MIME type is set
        // Verify correct filename
    }
    
    public function testWordExportStructure() {
        // Verify document structure
        // Verify RTL settings
    }
}
```

## Reporting Bugs

If you find any issues:

1. **Document the issue**:
   - Describe what happened
   - Note any error messages
   - Specify which export type (Excel/PDF/Word)
   - Note any filters applied

2. **Collect information**:
   - Browser and version
   - PHP version
   - Database query results (if applicable)
   - File size and content

3. **Test again**:
   - Try with different filters
   - Try with different data
   - Confirm issue is reproducible

4. **Report to development team**:
   - Include all documented information
   - Attach problematic export files
   - Provide steps to reproduce

---

Last Updated: 2024-12-11
