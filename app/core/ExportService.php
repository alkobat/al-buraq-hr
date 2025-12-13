<?php
/**
 * Export Service - Handles multi-format exports (Excel, PDF, Word) with professional Arabic formatting
 * 
 * Reuses filtered dataset logic with support for sorting, column selection, and grouping
 */

namespace App;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/EvaluationCalculator.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\SimpleType\Jc;
use Mpdf\Mpdf;

class ExportService {
    private $pdo;
    private $filters = [];
    private $columns = [];
    private $sections = [];
    private $company_name = '';
    private $logo_path = '';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        // Get system settings
        global $system_settings;
        $this->company_name = $system_settings['company_name'] ?? 'شركة البراق للنقل الجوي';
        $this->logo_path = $system_settings['logo_path'] ?? 'logo.png';
        
        // Default column preference
        $this->columns = ['name', 'email', 'dept', 'year', 'evaluator', 'score', 'status', 'updated_at'];
        $this->sections = ['summary', 'details'];
    }
    
    /**
     * Set filter criteria for exports
     * Accepts either an array of filters or individual legacy arguments
     */
    public function setFilters($cycleOrFilters = [], $dept = '', $status = '') {
        if (is_array($cycleOrFilters)) {
            $this->filters = array_merge([
                'cycle' => '',
                'dept' => [],
                'status' => [],
                'role' => '',
                'min_score' => '',
                'max_score' => '',
                'start_date' => '',
                'end_date' => '',
                'sort' => 'updated_at_desc'
            ], $cycleOrFilters);
        } else {
            // Legacy support
            $this->filters = [
                'cycle' => $cycleOrFilters,
                'dept' => $dept ? [$dept] : [],
                'status' => $status ? [$status] : [],
                'role' => '',
                'min_score' => '',
                'max_score' => '',
                'start_date' => '',
                'end_date' => '',
                'sort' => 'updated_at_desc'
            ];
        }
    }
    
    /**
     * Set export options (columns and sections)
     */
    public function setOptions($columns = [], $sections = []) {
        if (!empty($columns)) $this->columns = $columns;
        if (!empty($sections)) $this->sections = $sections;
    }
    
    /**
     * Build SQL WHERE clause and parameters based on filters
     */
    private function buildFilteredQuery() {
        // تطبيق منطق طريقة التقييم (manager_only)
        try {
            $method = (new \EvaluationCalculator($this->pdo))->getEvaluationMethod();
            if ($method === 'manager_only') {
                $this->filters['role'] = 'manager';
            }
        } catch (\Exception $e) {
            // Ignore if class not found or other error, fallback to filters
        }

        $sql_base = "
            FROM employee_evaluations e
            JOIN users u ON e.employee_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            JOIN evaluation_cycles c ON e.cycle_id = c.id
            JOIN users ev ON e.evaluator_id = ev.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($this->filters['cycle'])) {
            $sql_base .= " AND e.cycle_id = ?";
            $params[] = $this->filters['cycle'];
        }
        
        if (!empty($this->filters['dept'])) {
            $dept_placeholders = implode(',', array_fill(0, count($this->filters['dept']), '?'));
            $sql_base .= " AND u.department_id IN ($dept_placeholders)";
            $params = array_merge($params, $this->filters['dept']);
        }
        
        if (!empty($this->filters['status'])) {
            $status_placeholders = implode(',', array_fill(0, count($this->filters['status']), '?'));
            $sql_base .= " AND e.status IN ($status_placeholders)";
            $params = array_merge($params, $this->filters['status']);
        }
        
        if (!empty($this->filters['role'])) {
            $sql_base .= " AND e.evaluator_role = ?";
            $params[] = $this->filters['role'];
        }
        
        if (isset($this->filters['min_score']) && $this->filters['min_score'] !== '') {
            $sql_base .= " AND e.total_score >= ?";
            $params[] = $this->filters['min_score'];
        }
        
        if (isset($this->filters['max_score']) && $this->filters['max_score'] !== '') {
            $sql_base .= " AND e.total_score <= ?";
            $params[] = $this->filters['max_score'];
        }
        
        if (!empty($this->filters['start_date'])) {
            $sql_base .= " AND DATE(e.updated_at) >= ?";
            $params[] = $this->filters['start_date'];
        }
        
        if (!empty($this->filters['end_date'])) {
            $sql_base .= " AND DATE(e.updated_at) <= ?";
            $params[] = $this->filters['end_date'];
        }
        
        return [$sql_base, $params];
    }
    
    /**
     * Get filtered evaluation data with proper column aliases
     */
    public function getFilteredData() {
        list($sql_base, $params) = $this->buildFilteredQuery();
        
        $order_clause = " ORDER BY e.updated_at DESC";
        
        if (!empty($this->filters['sort'])) {
            switch ($this->filters['sort']) {
                case 'score_asc':
                    $order_clause = " ORDER BY e.total_score ASC";
                    break;
                case 'score_desc':
                    $order_clause = " ORDER BY e.total_score DESC";
                    break;
                case 'dept':
                    $order_clause = " ORDER BY d.name_ar ASC, u.name ASC";
                    break;
                case 'updated_at_asc':
                    $order_clause = " ORDER BY e.updated_at ASC";
                    break;
                case 'updated_at_desc':
                default:
                    $order_clause = " ORDER BY e.updated_at DESC";
                    break;
            }
        }
        
        $sql = "SELECT 
            u.name,
            u.email,
            d.name_ar as dept,
            c.year,
            ev.name as evaluator,
            e.total_score,
            e.status,
            e.updated_at,
            u.id as employee_id,
            c.id as cycle_id,
            e.id as eval_id,
            e.evaluator_role
        " . $sql_base . $order_clause;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get summary statistics for the filtered data
     */
    public function getSummaryStats() {
        list($sql_base, $params) = $this->buildFilteredQuery();
        
        $sql = "SELECT 
            COUNT(*) as total_reports,
            AVG(e.total_score) as avg_score,
            MAX(e.total_score) as max_score,
            MIN(e.total_score) as min_score,
            COUNT(CASE WHEN e.status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN e.status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(CASE WHEN e.status = 'submitted' THEN 1 END) as submitted_count
        " . $sql_base;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function shouldIncludeColumn($col) {
        return in_array($col, $this->columns);
    }

    private function shouldIncludeSection($sec) {
        return in_array($sec, $this->sections);
    }
    
    /**
     * Export to Excel with professional formatting
     */
    public function exportExcel() {
        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        // ===== SUMMARY SHEET =====
        if ($this->shouldIncludeSection('summary')) {
            $spreadsheet->setActiveSheetIndex($sheetIndex);
            $summary_sheet = $spreadsheet->getActiveSheet();
            $summary_sheet->setTitle('الملخص');
            $summary_sheet->setRightToLeft(true);
            
            $stats = $this->getSummaryStats();
            
            // Company header
            $summary_sheet->mergeCells('A1:D1');
            $summary_sheet->setCellValue('A1', $this->company_name);
            $summary_sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $summary_sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            $summary_sheet->mergeCells('A2:D2');
            $summary_sheet->setCellValue('A2', 'تقرير تقييم الأداء');
            $summary_sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
            
            // KPI Cards with merged cells
            $row = 4;
            
            // (جديد) إضافة طريقة الاحتساب
            try {
                $calc = new \EvaluationCalculator($this->pdo);
                $method_name = $calc->getMethodName();
            } catch (\Exception $e) {
                $method_name = '—';
            }

            $kpi_data = [
                ['طريقة الاحتساب', $method_name, '546E7A'],
                ['الإجمالي', $stats['total_reports'], '0070C0'],
                ['متوسط الدرجات', round($stats['avg_score'] ?? 0, 1) . '%', '70AD47'],
                ['أعلى درجة', $stats['max_score'] ?? 0, '0070C0'],
                ['أقل درجة', $stats['min_score'] ?? 0, 'FFC000'],
                ['موافق عليه', $stats['approved_count'], '70AD47'],
                ['مرفوض', $stats['rejected_count'], 'C55A11'],
                ['بانتظار', $stats['submitted_count'], 'FFC000']
            ];
            
            foreach ($kpi_data as $kpi) {
                $summary_sheet->mergeCells("A{$row}:B{$row}");
                $summary_sheet->setCellValue("A{$row}", $kpi[0]);
                // FIX: Use getFill() and set properties instead of setFill()
                $summary_sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($kpi[2]);
                    
                $summary_sheet->getStyle("A{$row}")->getFont()->setColor(new Color(Color::COLOR_WHITE))->setBold(true);
                
                $summary_sheet->mergeCells("C{$row}:D{$row}");
                $summary_sheet->setCellValue("C{$row}", $kpi[1]);
                $summary_sheet->getStyle("C{$row}")->getFont()->setSize(12)->setBold(true);
                
                $row++;
            }
            
            $summary_sheet->getColumnDimension('A')->setWidth(20);
            $summary_sheet->getColumnDimension('C')->setWidth(20);

            $sheetIndex++;
        }
        
        // ===== DETAILS SHEET =====
        if ($this->shouldIncludeSection('details')) {
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
                $spreadsheet->setActiveSheetIndex($sheetIndex);
            }
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('التقييمات');
            $sheet->setRightToLeft(true);
            
            // Get data
            $data = $this->getFilteredData();

            // Prepare headers and column map
            $headerMap = [
                'name' => 'الموظف',
                'email' => 'البريد الإلكتروني',
                'dept' => 'الإدارة',
                'year' => 'السنة',
                'evaluator' => 'المُقيّم',
                'score' => 'الدرجة',
                'status' => 'الحالة',
                'updated_at' => 'تاريخ التحديث'
            ];
            
            $headers = [];
            $activeColumns = [];
            foreach ($headerMap as $key => $label) {
                if ($this->shouldIncludeColumn($key)) {
                    $headers[] = $label;
                    $activeColumns[] = $key;
                }
            }

            // Write Header
            $sheet->fromArray($headers, null, 'A1');
            
            // Style header
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            
            // FIX: Use getFill() and set properties instead of setFill()
            $sheet->getStyle("A1:{$lastCol}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0070C0');
                
            $header_font = $sheet->getStyle("A1:{$lastCol}1")->getFont();
            $header_font->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Add data rows with alternating colors
            $row = 2;
            foreach ($data as $record) {
                $row_data = [];
                foreach ($activeColumns as $colKey) {
                    if ($colKey === 'status') {
                        $val = match($record['status']) {
                            'approved' => 'موافق عليه',
                            'rejected' => 'مرفوض',
                            'submitted' => 'بانتظار الاعتماد',
                            'draft' => 'مسودة',
                            default => $record['status']
                        };
                    } else if ($colKey === 'score') {
                        $val = $record['total_score'] ?? '—';
                    } else {
                        $val = $record[$colKey] ?? ($colKey == 'dept' ? '—' : '');
                    }
                    $row_data[] = $val;
                }
                
                $sheet->fromArray($row_data, null, 'A' . $row);
                
                // Alternating row colors
                if ($row % 2 == 0) {
                    // FIX: Use getFill() and set properties
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                }
                
                $row++;
            }
            
            // Auto size columns
            foreach (range(1, count($headers)) as $colIndex) {
                $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->getColumnDimension($colStr)->setAutoSize(true);
            }
        }
        
        // Clean output buffer and send file
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="evaluation_report_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Export to PDF with professional formatting and Arabic support
     */
    public function exportPdf() {
        $data = $this->getFilteredData();
        $stats = $this->getSummaryStats();
        
        // Get logo if exists
        $logo_base64 = '';
        $logo_path = '../storage/uploads/' . $this->logo_path;
        if (file_exists($logo_path)) {
            $logo_data = file_get_contents($logo_path);
            $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
        }
        
        // Build HTML content
        $html = '
        <html>
        <head>
            <style>
                body { font-family: sans-serif; direction: rtl; text-align: right; }
                .header { width: 100%; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                .header table { width: 100%; }
                .header td { vertical-align: middle; }
                .company-name { font-size: 16px; font-weight: bold; }
                .title { font-size: 20px; font-weight: bold; text-align: center; }
                .subtitle { font-size: 12px; color: #666; text-align: center; }
                
                .kpi-container { margin: 20px 0; }
                .kpi-card { 
                    display: inline-block; 
                    width: 22%; 
                    border: 1px solid #ddd; 
                    padding: 15px; 
                    margin: 5px; 
                    background-color: #f5f5f5;
                    border-radius: 5px;
                }
                .kpi-label { font-weight: bold; font-size: 11px; color: #333; }
                .kpi-value { font-size: 18px; font-weight: bold; color: #0070C0; margin-top: 5px; }
                
                .section-title { font-size: 14px; font-weight: bold; margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #0070C0; padding-bottom: 5px; }
                
                .data-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .data-table th { background-color: #0070C0; color: white; padding: 8px; text-align: center; border: 1px solid #333; }
                .data-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
                .data-table tr:nth-child(even) { background-color: #f9f9f9; }
                .text-right { text-align: right; }
                
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 10px; color: #666; }
                .page-break { page-break-after: always; }
            </style>
        </head>
        <body>
        
        <!-- Cover Page -->
        <div class="header">
            <table>
                <tr>
                    <td width="30%">
                        <div class="company-name">' . htmlspecialchars($this->company_name) . '</div>
                        <div style="font-size: 12px; color: #666;">إدارة الموارد البشرية</div>
                    </td>
                    <td width="40%" class="text-center">
                        <div class="title">تقرير تقييم الأداء</div>
                        <div class="subtitle">التقرير الشامل للتقييمات</div>
                        <div class="subtitle">تاريخ: ' . date('Y-m-d') . '</div>
                    </td>
                    <td width="30%" style="text-align: left;">
                        ' . ($logo_base64 ? '<img src="' . $logo_base64 . '" style="width: 80px;">' : '') . '
                    </td>
                </tr>
            </table>
        </div>';
        
        // Summary Section
        if ($this->shouldIncludeSection('summary')) {
            $html .= '
            <div class="section-title">ملخص التقارير</div>
            <div class="kpi-container">
                <div class="kpi-card">
                    <div class="kpi-label">إجمالي التقييمات</div>
                    <div class="kpi-value">' . $stats['total_reports'] . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">متوسط الدرجات</div>
                    <div class="kpi-value">' . round($stats['avg_score'] ?? 0, 1) . '%</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">أعلى درجة</div>
                    <div class="kpi-value">' . ($stats['max_score'] ?? 0) . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">أقل درجة</div>
                    <div class="kpi-value">' . ($stats['min_score'] ?? 0) . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">موافق عليه</div>
                    <div class="kpi-value">' . $stats['approved_count'] . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">مرفوض</div>
                    <div class="kpi-value">' . $stats['rejected_count'] . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">بانتظار</div>
                    <div class="kpi-value">' . $stats['submitted_count'] . '</div>
                </div>
            </div>';
        }
        
        // Details Section
        if ($this->shouldIncludeSection('details')) {
            $html .= '<div class="section-title">تفاصيل التقييمات</div>
            <table class="data-table">
                <thead>
                    <tr>';
            
            // Dynamic Headers
            $headerMap = [
                'name' => ['الموظف', '15%'],
                'email' => ['البريد الإلكتروني', '15%'],
                'dept' => ['الإدارة', '15%'],
                'year' => ['السنة', '10%'],
                'evaluator' => ['المُقيّم', '15%'],
                'score' => ['الدرجة', '10%'],
                'status' => ['الحالة', '10%'],
                'updated_at' => ['تاريخ التحديث', '10%']
            ];
            
            foreach ($headerMap as $key => $info) {
                if ($this->shouldIncludeColumn($key)) {
                    $html .= '<th width="' . $info[1] . '">' . $info[0] . '</th>';
                }
            }
            
            $html .= '</tr>
                </thead>
                <tbody>';
            
            foreach ($data as $record) {
                $html .= '<tr>';
                
                foreach ($headerMap as $key => $info) {
                    if (!$this->shouldIncludeColumn($key)) continue;
                    
                    if ($key === 'status') {
                        $val = match($record['status']) {
                            'approved' => 'موافق عليه',
                            'rejected' => 'مرفوض',
                            'submitted' => 'بانتظار',
                            'draft' => 'مسودة',
                            default => $record['status']
                        };
                        $html .= '<td>' . $val . '</td>';
                    } elseif ($key === 'score') {
                         $html .= '<td><strong>' . ($record['total_score'] ?? '—') . '</strong></td>';
                    } elseif ($key === 'name') {
                        $html .= '<td class="text-right">' . htmlspecialchars($record['name'] ?? '') . '</td>';
                    } else {
                        $val = htmlspecialchars($record[$key] ?? '');
                        if ($key == 'dept' && empty($val)) $val = '—';
                        $html .= '<td>' . $val . '</td>';
                    }
                }
                
                $html .= '</tr>';
            }
            
            $html .= '
                </tbody>
            </table>';
        }
        
        $html .= '
        <div class="footer">
            <p>تم إنشاء هذا التقرير بواسطة نظام إدارة تقييم الأداء - ' . date('Y-m-d H:i:s') . '</p>
        </div>
        
        </body>
        </html>';
        
        // Generate PDF using mPDF
        // FIX: Ensure correct options are used
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'sans-serif'
        ]);
        
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);
        
        // Clean output buffer and send file
        if (ob_get_length()) ob_clean();
        
        $filename = 'evaluation_report_' . date('Y-m-d') . '.pdf';
        $mpdf->Output($filename, 'D');
        exit;
    }
    
    /**
     * Export to Word (.docx) with professional formatting
     */
    public function exportWord() {
        $data = $this->getFilteredData();
        $stats = $this->getSummaryStats();
        
        $phpWord = new PhpWord();
        
        // Set RTL for document
        // FIX: Ensure Language object is correct. The constructor is (latin, eastAsia, bidirectional)
        $language = new Language(null, null, 'ar-SA'); 
        $phpWord->getSettings()->setThemeFontLang($language);
        
        // Add section
        $section = $phpWord->addSection([
            'rtl' => true,
            'marginTop' => 600,
            'marginRight' => 1440,
            'marginBottom' => 600,
            'marginLeft' => 1440
        ]);
        
        // Company header
        $header = $section->addHeader();
        $header->addText(
            $this->company_name,
            ['bold' => true, 'size' => 18, 'color' => '0070C0'],
            ['alignment' => 'right', 'rtl' => true]
        );
        $header->addText(
            'إدارة الموارد البشرية',
            ['size' => 12, 'color' => '666666'],
            ['alignment' => 'right', 'rtl' => true]
        );
        $header->addLine();
        
        // Title
        $section->addText(
            'تقرير تقييم الأداء',
            ['bold' => true, 'size' => 18, 'color' => '0070C0'],
            ['alignment' => 'center', 'rtl' => true]
        );
        
        $section->addText(
            'التقرير الشامل للتقييمات - ' . date('Y-m-d'),
            ['size' => 12, 'color' => '666666'],
            ['alignment' => 'center', 'rtl' => true]
        );
        
        $section->addTextBreak();
        
        // Summary Section
        if ($this->shouldIncludeSection('summary')) {
            $section->addHeading('ملخص التقارير', 2);
            
            // KPI summary table
            $summaryTable = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '0070C0',
                'width' => 100 * 50,
                'unit' => 'pct',
                'layout' => 'autofit'
            ]);
            
            // KPI rows
            $kpi_data = [
                'إجمالي التقييمات' => $stats['total_reports'],
                'متوسط الدرجات' => round($stats['avg_score'] ?? 0, 1) . '%',
                'أعلى درجة' => $stats['max_score'] ?? 0,
                'أقل درجة' => $stats['min_score'] ?? 0,
                'موافق عليه' => $stats['approved_count'],
                'مرفوض' => $stats['rejected_count'],
                'بانتظار الاعتماد' => $stats['submitted_count']
            ];
            
            foreach ($kpi_data as $label => $value) {
                $row = $summaryTable->addRow();
                $row->addCell(5000)->addText($label, ['bold' => true], ['rtl' => true]);
                $row->addCell(5000)->addText((string)$value, ['bold' => true, 'size' => 14], ['alignment' => 'center', 'rtl' => true]);
            }
            
            $section->addTextBreak();
        }
        
        // Details Section
        if ($this->shouldIncludeSection('details')) {
            $section->addHeading('تفاصيل التقييمات', 2);
            
            $tableStyle = [
                'borderSize' => 6, 
                'borderColor' => '999999', 
                'cellMargin' => 50
            ];
            $phpWord->addTableStyle('Data Table', $tableStyle);
            $table = $section->addTable('Data Table');
            
            // Headers
            $headerMap = [
                'name' => 'الموظف',
                'email' => 'البريد',
                'dept' => 'الإدارة',
                'year' => 'السنة',
                'evaluator' => 'المُقيّم',
                'score' => 'الدرجة',
                'status' => 'الحالة',
                'updated_at' => 'التحديث'
            ];
            
            $table->addRow();
            foreach ($headerMap as $key => $label) {
                if ($this->shouldIncludeColumn($key)) {
                    $table->addCell(2000, ['bgColor' => '0070C0'])->addText($label, ['bold' => true, 'color' => 'FFFFFF'], ['rtl' => true, 'alignment' => 'center']);
                }
            }
            
            foreach ($data as $record) {
                $table->addRow();
                foreach ($headerMap as $key => $label) {
                    if (!$this->shouldIncludeColumn($key)) continue;

                    if ($key === 'status') {
                        $val = match($record['status']) {
                            'approved' => 'موافق',
                            'rejected' => 'مرفوض',
                            'submitted' => 'انتظار',
                            'draft' => 'مسودة',
                            default => $record['status']
                        };
                    } else if ($key === 'score') {
                         $val = $record['total_score'] ?? '—';
                    } else {
                        $val = $record[$key] ?? ($key == 'dept' ? '—' : '');
                    }
                    
                    $table->addCell(2000)->addText($val, [], ['rtl' => true, 'alignment' => 'center']);
                }
            }
        }
        
        // Clean output buffer and send file
        if (ob_get_length()) ob_clean();
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="evaluation_report_' . date('Y-m-d') . '.docx"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }
}
