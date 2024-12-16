<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\DataTable\DataTableFormat;
use EMS\CoreBundle\Core\DataTable\TableExporter;
use EMS\CoreBundle\Core\DataTable\TableRenderer;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\DatatableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class DatatableController extends AbstractController
{
    public function __construct(
        private readonly DatatableService $datatableService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly TableRenderer $tableRenderer,
        private readonly TableExporter $tableExporter,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function ajaxData(Request $request, string $hash, ?string $optionsCacheKey = null): Response
    {
        $table = $this->dataTableFactory->createFromHash($hash, $optionsCacheKey);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return new JsonResponse([
            'data' => $this->tableRenderer->buildRows($table),
            'draw' => $dataTableRequest->getDraw(),
            'recordsFiltered' => $table->count(),
            'recordsTotal' => $table->totalCount(),
        ]);
    }

    public function ajaxExport(Request $request, string $format, string $hash, ?string $optionsCacheKey = null): Response
    {
        $table = $this->dataTableFactory->createFromHash($hash, $optionsCacheKey, DataTableFormat::from($format));
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return match ($format) {
            'excel' => $this->tableExporter->exportExcel($table),
            'csv' => $this->tableExporter->exportCSV($table),
            default => throw new \RuntimeException('Invalid format'),
        };
    }

    public function ajaxElastica(Request $request, string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);
        $this->checkAccess($table, $hashConfig);
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
        $this->checkAccess($table, $hashConfig);

        return $this->tableExporter->exportExcel($table);
    }

    public function csvElastica(string $hashConfig): Response
    {
        $table = $this->datatableService->generateDatatableFromHash($hashConfig);
        $this->checkAccess($table, $hashConfig);

        return $this->tableExporter->exportCSV($table);
    }

    private function checkAccess(ElasticaTable $table, string $hashConfig): void
    {
        if ($table->isProtected() && null === $this->tokenStorage->getToken()) {
            throw new AccessDeniedException($hashConfig);
        }
    }
}
