<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CoreBundle\Form\Data\TableInterface;
use Symfony\Component\HttpFoundation\Response;

final class TableExporter
{
    private TableRenderer $tableRenderer;
    private SpreadsheetGeneratorServiceInterface $spreadsheetGenerator;

    public function __construct(TableRenderer $tableRenderer, SpreadsheetGeneratorServiceInterface $spreadsheetGenerator)
    {
        $this->tableRenderer = $tableRenderer;
        $this->spreadsheetGenerator = $spreadsheetGenerator;
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
        $rows = $this->tableRenderer->buildAllRows($table);

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
