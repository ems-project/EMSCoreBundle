<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\DatatableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DatatableController extends AbstractController
{
    private DatatableService $datatableService;

    public function __construct(DatatableService $datatableService)
    {
        $this->datatableService = $datatableService;
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
}
