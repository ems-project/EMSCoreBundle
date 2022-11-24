<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Core\DataTable\TableExporter;
use EMS\CoreBundle\Core\DataTable\TableRenderer;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\DatatableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DatatableController extends AbstractController
{
    private DatatableService $datatableService;
    private TableRenderer $tableRenderer;
    private TableExporter $tableExporter;

    public function __construct(DatatableService $datatableService, TableRenderer $tableRenderer, TableExporter $tableExporter)
    {
        $this->datatableService = $datatableService;
        $this->tableRenderer = $tableRenderer;
        $this->tableExporter = $tableExporter;
    }

    public function ajaxElastica(Request $request, string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return new JsonResponse([
            'data' => $this->tableRenderer->buildRows($table),
            'draw' => $dataTableRequest->getDraw(),
            'recordsFiltered' => $table->count(),
            'recordsTotal' => $table->totalCount(),
        ]);
    }

    public function excelElastica(string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);

        return $this->tableExporter->exportExcel($table);
    }

    public function csvElastica(string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);

        return $this->tableExporter->exportCSV($table);
    }
}
