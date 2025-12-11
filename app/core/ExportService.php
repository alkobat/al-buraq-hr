<?php
/**
 * Export Service - Handles multi-format exports (Excel, PDF, Word) with professional Arabic formatting
 * 
 * Reuses filtered dataset logic with support for sorting, column selection, and grouping
 */

namespace App;

require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\PatternFill;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Inches;
use PhpOffice\PhpWord\Shared\RGBColor;

class ExportService {
    private $pdo;
    private $filters = [];
    private $company_name = '';
    private $logo_path = '';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        // Get system settings
        global $system_settings;
        $this->company_name = $system_settings['company_name'] ?? 'شركة البراق للنقل الجوي';
        $this->logo_path = $system_settings['logo_path'] ?? 'logo.png';
    }
    
    /**
     * Set filter criteria for exports
     */
    public function setFilters($cycle = '', $dept = '', $status = '') {
        $this->filters = [
            'cycle' => $cycle,
            'dept' => $dept,
            'status' => $status
        ];
    }
    
    /**
     * Build SQL WHERE clause and parameters based on filters
     */
    private function buildFilteredQuery() {
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
            $sql_base .= " AND u.department_id = ?";
            $params[] = $this->filters['dept'];
        }
        if (!empty($this->filters['status'])) {
            $sql_base .= " AND e.status = ?";
            $params[] = $this->filters['status'];
        }
        
        return [$sql_base, $params];
    }
    
    /**
     * Get filtered evaluation data with proper column aliases
     */
    public function getFilteredData() {
        list($sql_base, $params) = $this->buildFilteredQuery();
        
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
        " . $sql_base . " ORDER BY e.updated_at DESC";
        
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
    
    /**
     * Export to Excel with professional formatting
     */
    public function exportExcel() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        
        // Get data
        $data = $this->getFilteredData();
        $stats = $this->getSummaryStats();
        
        // ===== SUMMARY SHEET =====
        $summary_sheet = $spreadsheet->createSheet();
        $summary_sheet->setTitle('الملخص');
        $summary_sheet->setRightToLeft(true);
        
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
        $kpi_data = [
            ['الإجمالي', $stats['total_reports'], '0070C0'],
            ['متوسط الدرجات', round($stats['avg_score'], 1) . '%', '70AD47'],
            ['أعلى درجة', $stats['max_score'], '0070C0'],
            ['أقل درجة', $stats['min_score'], 'FFC000'],
            ['موافق عليه', $stats['approved_count'], '70AD47'],
            ['مرفوض', $stats['rejected_count'], 'C55A11'],
            ['بانتظار', $stats['submitted_count'], 'FFC000']
        ];
        
        foreach ($kpi_data as $kpi) {
            $summary_sheet->mergeCells("A{$row}:B{$row}");
            $summary_sheet->setCellValue("A{$row}", $kpi[0]);
            $summary_sheet->getStyle("A{$row}")->getFill()->setFillType(PatternFill::FILL_SOLID)->getStartColor()->setRGB($kpi[2]);
            $summary_sheet->getStyle("A{$row}")->getFont()->setColor(new RGBColor(255, 255, 255))->setBold(true);
            
            $summary_sheet->mergeCells("C{$row}:D{$row}");
            $summary_sheet->setCellValue("C{$row}", $kpi[1]);
            $summary_sheet->getStyle("C{$row}")->getFont()->setSize(12)->setBold(true);
            
            $row++;
        }
        
        $summary_sheet->getColumnDimension('A')->setWidth(20);
        $summary_sheet->getColumnDimension('C')->setWidth(20);
        
        // ===== DETAILS SHEET =====
        $sheet->setTitle('التقييمات');
        
        // Header
        $headers = ['الموظف', 'البريد الإلكتروني', 'الإدارة', 'السنة', 'المُقيّم', 'الدرجة', 'الحالة', 'تاريخ التحديث'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Style header
        $header_fill = new PatternFill(PatternFill::FILL_SOLID, '0070C0');
        $header_font = $sheet->getStyle('A1:H1')->getFont();
        $header_font->setBold(true)->setColor(new RGBColor(255, 255, 255));
        $sheet->getStyle('A1:H1')->setFill($header_fill);
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Add data rows with alternating colors
        $row = 2;
        foreach ($data as $record) {
            $status_text = match($record['status']) {
                'approved' => 'موافق عليه',
                'rejected' => 'مرفوض',
                'submitted' => 'بانتظار الاعتماد',
                'draft' => 'مسودة',
                default => $record['status']
            };
            
            $row_data = [
                $record['name'] ?? '',
                $record['email'] ?? '',
                $record['dept'] ?? '—',
                $record['year'] ?? '',
                $record['evaluator'] ?? '',
                $record['total_score'] ?? '—',
                $status_text,
                $record['updated_at'] ?? ''
            ];
            
            $sheet->fromArray($row_data, null, 'A' . $row);
            
            // Alternating row colors
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                    ->setFillType(PatternFill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
            
            $row++;
        }
        
        // Auto size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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
        </div>
        
        <!-- Summary Section -->
        <div class="section-title">ملخص التقارير</div>
        <div class="kpi-container">
            <div class="kpi-card">
                <div class="kpi-label">إجمالي التقييمات</div>
                <div class="kpi-value">' . $stats['total_reports'] . '</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">متوسط الدرجات</div>
                <div class="kpi-value">' . round($stats['avg_score'], 1) . '%</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">أعلى درجة</div>
                <div class="kpi-value">' . $stats['max_score'] . '</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">أقل درجة</div>
                <div class="kpi-value">' . $stats['min_score'] . '</div>
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
        </div>
        
        <!-- Details Section -->
        <div class="section-title">تفاصيل التقييمات</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="15%">الموظف</th>
                    <th width="15%">البريد الإلكتروني</th>
                    <th width="15%">الإدارة</th>
                    <th width="10%">السنة</th>
                    <th width="15%">المُقيّم</th>
                    <th width="10%">الدرجة</th>
                    <th width="10%">الحالة</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data as $record) {
            $status_text = match($record['status']) {
                'approved' => 'موافق عليه',
                'rejected' => 'مرفوض',
                'submitted' => 'بانتظار',
                'draft' => 'مسودة',
                default => $record['status']
            };
            
            $html .= '<tr>
                <td class="text-right">' . htmlspecialchars($record['name'] ?? '') . '</td>
                <td>' . htmlspecialchars($record['email'] ?? '') . '</td>
                <td>' . htmlspecialchars($record['dept'] ?? '—') . '</td>
                <td>' . ($record['year'] ?? '') . '</td>
                <td>' . htmlspecialchars($record['evaluator'] ?? '') . '</td>
                <td><strong>' . ($record['total_score'] ?? '—') . '</strong></td>
                <td>' . $status_text . '</td>
            </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>تم إنشاء هذا التقرير بواسطة نظام إدارة تقييم الأداء - ' . date('Y-m-d H:i:s') . '</p>
        </div>
        
        </body>
        </html>';
        
        // Generate PDF using mPDF
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
        $phpWord->getSettings()->setThemeFontLang('ar');
        
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
        $section->addHeading('ملخص التقارير', 2, ['rtl' => true]);
        
        // KPI summary table
        $summaryTable = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '0070C0'
        ]);
        $summaryTable->setWidth(100 * 50);
        
        // KPI rows
        $kpi_data = [
            'إجمالي التقييمات' => $stats['total_reports'],
            'متوسط الدرجات' => round($stats['avg_score'], 1) . '%',
            'أعلى درجة' => $stats['max_score'],
            'أقل درجة' => $stats['min_score'],
            'موافق عليه' => $stats['approved_count'],
            'مرفوض' => $stats['rejected_count'],
            'بانتظار الاعتماد' => $stats['submitted_count']
        ];
        
        foreach ($kpi_data as $label => $value) {
            $row = $summaryTable->addRow();
            $row->addCell(2400)->addText($label, ['bold' => true], ['rtl' => true]);
            $row->addCell(2400)->addText((string)$value, ['bold' => true, 'size' => 14], ['alignment' => 'center', 'rtl' => true]);
        }
        
        $section->addTextBreak();
        
        // Details Section
        $section->addHeading('تفاصيل التقييمات', 2, ['rtl' => true]);
        
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000'
        ]);
        $table->setWidth(100 * 50);
        
        // Header row
        $headerRow = $table->addRow();
        $headerRow->getStyle()->setBackgroundColor('0070C0');
        
        $headers = ['الموظف', 'البريد الإلكتروني', 'الإدارة', 'السنة', 'المُقيّم', 'الدرجة', 'الحالة'];
        foreach ($headers as $header) {
            $cell = $headerRow->addCell(1400);
            $cell->addText($header, ['bold' => true, 'color' => 'FFFFFF'], ['alignment' => 'center', 'rtl' => true]);
        }
        
        // Data rows
        $row_num = 0;
        foreach ($data as $record) {
            $status_text = match($record['status']) {
                'approved' => 'موافق عليه',
                'rejected' => 'مرفوض',
                'submitted' => 'بانتظار',
                'draft' => 'مسودة',
                default => $record['status']
            };
            
            $row = $table->addRow();
            
            // Alternating row colors
            if ($row_num % 2 == 0) {
                $row->getStyle()->setBackgroundColor('F2F2F2');
            }
            
            $row->addCell(1400)->addText(htmlspecialchars($record['name'] ?? ''), [], ['rtl' => true]);
            $row->addCell(1400)->addText(htmlspecialchars($record['email'] ?? ''), [], ['rtl' => true]);
            $row->addCell(1400)->addText(htmlspecialchars($record['dept'] ?? '—'), [], ['alignment' => 'center', 'rtl' => true]);
            $row->addCell(1400)->addText((string)($record['year'] ?? ''), [], ['alignment' => 'center', 'rtl' => true]);
            $row->addCell(1400)->addText(htmlspecialchars($record['evaluator'] ?? ''), [], ['rtl' => true]);
            $row->addCell(1400)->addText((string)($record['total_score'] ?? '—'), ['bold' => true], ['alignment' => 'center', 'rtl' => true]);
            $row->addCell(1400)->addText($status_text, [], ['alignment' => 'center', 'rtl' => true]);
            
            $row_num++;
        }
        
        $section->addTextBreak(2);
        
        // Footer
        $footer = $section->addFooter();
        $footer->addText(
            'تم إنشاء هذا التقرير بواسطة نظام إدارة تقييم الأداء - ' . date('Y-m-d H:i:s'),
            ['size' => 10, 'color' => '999999'],
            ['alignment' => 'center', 'rtl' => true]
        );
        
        // Clean output buffer and send file
        if (ob_get_length()) ob_clean();
        
        $filename = 'evaluation_report_' . date('Y-m-d') . '.docx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }
}
?>
