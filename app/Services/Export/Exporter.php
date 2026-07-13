<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Core\Response;

/**
 * Builds downloadable exports of tabular report data in CSV, XLSX or PDF.
 * CSV is dependency-free; XLSX uses PhpSpreadsheet; PDF uses dompdf. Each
 * returns a Response with the right content-type and download headers.
 */
final class Exporter
{
    /**
     * @param string[] $headers
     * @param array<int,array<int,scalar>> $rows
     */
    public function make(string $format, string $filename, string $title, array $headers, array $rows): Response
    {
        return match ($format) {
            'xlsx'  => $this->xlsx($filename, $title, $headers, $rows),
            'pdf'   => $this->pdf($filename, $title, $headers, $rows),
            default => $this->csv($filename, $headers, $rows),
        };
    }

    /** @param string[] $headers @param array<int,array<int,scalar>> $rows */
    private function csv(string $filename, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');
        // Explicit separator/enclosure/escape — the escape default changes in
        // PHP 8.4; "" disables backslash escaping for standards-compliant CSV.
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        return (new Response($body))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');
    }

    /** @param string[] $headers @param array<int,array<int,scalar>> $rows */
    private function xlsx(string $filename, string $title, array $headers, array $rows): Response
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return $this->csv($filename, $headers, $rows); // graceful fallback
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31));

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        foreach (range('A', chr(ord('A') + max(0, count($headers) - 1))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->getFont()->setBold(true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $body = (string) ob_get_clean();

        return (new Response($body))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"');
    }

    /** @param string[] $headers @param array<int,array<int,scalar>> $rows */
    private function pdf(string $filename, string $title, array $headers, array $rows): Response
    {
        $html = $this->tableHtml($title, $headers, $rows);

        if (!class_exists(\Dompdf\Dompdf::class)) {
            return (new Response($html))->header('Content-Type', 'text/html; charset=utf-8');
        }

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (new Response($dompdf->output()))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"');
    }

    /** @param string[] $headers @param array<int,array<int,scalar>> $rows */
    private function tableHtml(string $title, array $headers, array $rows): string
    {
        $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans, sans-serif;color:#0f172a;font-size:11px}'
            . 'h1{font-size:16px;margin:0 0 4px} .sub{color:#64748b;margin:0 0 16px;font-size:10px}'
            . 'table{width:100%;border-collapse:collapse} th{background:#f1f5f9;text-align:left;padding:6px 8px;border-bottom:2px solid #cbd5e1;font-size:10px}'
            . 'td{padding:6px 8px;border-bottom:1px solid #e2e8f0}'
            . '</style></head><body>';
        $html .= '<h1>' . $esc($title) . '</h1><p class="sub">NexusDesk report · generated ' . date('Y-m-d H:i') . '</p>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) { $html .= '<th>' . $esc($h) . '</th>'; }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) { $html .= '<td>' . $esc($cell) . '</td>'; }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        return $html;
    }
}
