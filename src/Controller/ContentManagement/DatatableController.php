<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\DatatableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class DatatableController extends AbstractController
{
    private DatatableService $datatableService;
    private Environment $twig;
    private SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService;

    public function __construct(DatatableService $datatableService, Environment $twig, SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService)
    {
        $this->datatableService = $datatableService;
        $this->twig = $twig;
        $this->spreadsheetGeneratorService = $spreadsheetGeneratorService;
    }

    public function ajaxElastica(Request $request, string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);
        $template = $this->twig->createTemplate($table->getRowTemplate());
        $rows = [];
        foreach ($table as $line) {
            $row = \json_decode($template->render([
                'table' => $table,
                'line' => $line,
            ]));
            if (!\is_array($row)) {
                throw new \RuntimeException('Unexpected non array object');
            }
            $rows[] = $row;
        }

        return new JsonResponse([
            'data' => $rows,
            'draw' => $dataTableRequest->getDraw(),
            'recordsFiltered' => $table->count(),
            'recordsTotal' => $table->totalCount(),
        ]);
    }

    public function excelElastica(string $hashConfig): Response
    {
        return $this->spreadsheetElastica($hashConfig, SpreadsheetGeneratorServiceInterface::XLSX_WRITER);
    }

    public function csvElastica(string $hashConfig): Response
    {
        return $this->spreadsheetElastica($hashConfig, SpreadsheetGeneratorServiceInterface::CSV_WRITER);
    }

    /**
     * @return string[][]
     */
    private function buildTableRows(ElasticaTable $table): array
    {
        $headers = [];
        foreach ($table->getColumns() as $column) {
            $headers[] = $column->getTitleKey();
        }
        $rows = [$headers];
        $template = $this->twig->createTemplate($table->getRowTemplate());

        while ($table->next()) {
            foreach ($table as $line) {
                $row = \json_decode($template->render([
                    'table' => $table,
                    'line' => $line,
                ]));
                if (!\is_array($row)) {
                    throw new \RuntimeException('Unexpected non array object');
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function spreadsheetElastica(string $hashConfig, string $spreadsheetWriter): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);
        $rows = $this->buildTableRows($table);

        return $this->spreadsheetGeneratorService->generateSpreadsheet([
            SpreadsheetGeneratorServiceInterface::SHEETS => [[
                'name' => $table->getSheetName(),
                'rows' => $rows,
            ]],
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => $table->getFilename(),
            SpreadsheetGeneratorServiceInterface::CONTENT_DISPOSITION => $table->getDisposition(),
            SpreadsheetGeneratorServiceInterface::WRITER => $spreadsheetWriter,
        ]);
    }
}
