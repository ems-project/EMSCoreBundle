<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\QuerySearchType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\QuerySearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class QuerySearchController extends AbstractController
{
    private LoggerInterface $logger;
    private QuerySearchService $querySearchService;

    public function __construct(LoggerInterface $logger, QuerySearchService $querySearchService)
    {
        $this->logger = $logger;
        $this->querySearchService = $querySearchService;
    }

    public function ajaxDataTable(Request $request): Response
    {
        $table = $this->initTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initTable();
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->querySearchService->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                        $this->querySearchService->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.query_search.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.query_search.unknown_action');
            }

            return $this->redirectToRoute('ems_core_query_search_index');
        }

        return $this->render('@EMSCore/query-search/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $querySearch = new QuerySearch();

        return $this->edit($request, $querySearch, '@EMSCore/query-search/add.html.twig');
    }

    public function edit(Request $request, QuerySearch $query_search, string $view = '@EMSCore/query-search/edit.html.twig'): Response
    {
        $form = $this->createForm(QuerySearchType::class, $query_search);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->querySearchService->update($query_search);

            return $this->redirectToRoute('ems_core_query_search_index');
        }

        return $this->render($view, [
            'form' => $form->createView(),
        ]);
    }

    public function delete(QuerySearch $query_search): RedirectResponse
    {
        $this->querySearchService->delete($query_search);

        return $this->redirectToRoute('ems_core_query_search_index');
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->querySearchService, $this->generateUrl('ems_core_query_search'));
        $table->addColumn('query_search.index.column.label', 'label');
        $table->addColumn('query_search.index.column.name', 'name');
        $table->addItemGetAction('ems_core_query_search_edit', 'query_search.actions.edit', 'pencil');
        $table->addItemPostAction('ems_core_query_search_delete', 'query_search.actions.delete', 'trash', 'query_search.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'query_search.actions.delete_selected', 'query_search.actions.delete_selected_confirm');
        $table->setDefaultOrder('label');

        return $table;
    }
}
