<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CoreBundle\Form\Data\TableInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class TableExporter
{
    public function __construct(private TableRenderer $tableRenderer, private SpreadsheetGeneratorServiceInterface $spreadsheetGenerator)
    {
    }

    public function exportExcel(TableInterface $table): Response
    {
        return $this->buildSpreadsheet($table, SpreadsheetGeneratorServiceInterface::XLSX_WRITER);
    }

    public function exportCSV(TableInterface $table): Response
    {
        return $this->buildSpreadsheet($table, SpreadsheetGeneratorServiceInterface::CSV_WRITER);
    }

    private function buildSpreadsheet(TableInterface $table, string $spreadsheetWriter): Response
    {
        $headers = $this->tableRenderer->buildHeaders($table);
        $rows = $this->tableRenderer->buildAllRows(table: $table, export: true);

        return $this->spreadsheetGenerator->generateSpreadsheet([
            SpreadsheetGeneratorServiceInterface::SHEETS => [[
                'name' => $table->getExportSheetName(),
                'rows' => [$headers, ...$rows],
            ]],
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => $table->getExportFileName(),
            SpreadsheetGeneratorServiceInterface::CONTENT_DISPOSITION => $table->getExportDisposition(),
            SpreadsheetGeneratorServiceInterface::WRITER => $spreadsheetWriter,
        ]);
    }
}
