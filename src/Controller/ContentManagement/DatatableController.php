<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
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

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function excelElastica(string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);
        $headers = [];
        foreach ($table->getColumns() as $column) {
            $headers[] = $column->getTitleKey();
        }
        $rows = [$headers];

        while ($table->next()) {
            foreach ($table as $line) {
                $row = [];
                foreach ($table->getColumns() as $column) {
                    $row[] = $this->twig->render('@EMSCore/datatable/excel-cell.html.twig', [
                        'line' => $line,
                        'column' => $column,
                    ]);
                }
                $rows[] = $row;
            }
        }

        $spreadsheetConfig = [
            'sheets' => [[
                'name' => 'sheet',
                'rows' => $rows,
        ]], ];

        return $this->spreadsheetGeneratorService->generateSpreadsheet($spreadsheetConfig);
    }
}
