<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Export\Exporter;
use PHPUnit\Framework\TestCase;

final class ExporterTest extends TestCase
{
    private function config(): void
    {
        // Exporter needs no config; helpers not required for CSV.
    }

    public function test_csv_export_contains_headers_and_rows(): void
    {
        $response = (new Exporter())->make('csv', 'report', 'Report', ['Agent', 'Resolved'], [['Alex', 12], ['Sam', 8]]);
        ob_start();
        $response->send();
        $body = (string) ob_get_clean();

        $this->assertStringContainsString('Agent,Resolved', $body);
        $this->assertStringContainsString('Alex,12', $body);
        $this->assertStringContainsString('Sam,8', $body);
    }

    public function test_pdf_export_produces_pdf_bytes(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('dompdf not installed');
        }
        $response = (new Exporter())->make('pdf', 'report', 'Report', ['A'], [['1']]);
        ob_start();
        $response->send();
        $body = (string) ob_get_clean();

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('%PDF', $body);
    }

    public function test_xlsx_export_produces_zip_bytes(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('phpspreadsheet not installed');
        }
        $response = (new Exporter())->make('xlsx', 'report', 'Report', ['A', 'B'], [['1', '2']]);
        ob_start();
        $response->send();
        $body = (string) ob_get_clean();

        // XLSX is a ZIP archive → starts with "PK".
        $this->assertStringStartsWith('PK', $body);
    }
}
