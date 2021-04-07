<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\QuerySearchType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\QuerySearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
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

    public function index(Request $request): Response
    {
        $table = new EntityTable($this->querySearchService);
        $labelColumn = $table->addColumn('querysearch.index.column.label', 'label');
        $labelColumn->setRouteTarget('querysearch_%value%');
        $table->addColumn('querysearch.index.column.name', 'name');
        $table->addColumn('querysearch.index.column.environments', 'environments');
        $table->addItemGetAction('ems_core_querysearch_edit', 'querysearch.actions.edit', 'pencil');
        $table->addItemPostAction('ems_core_querysearch_delete', 'querysearch.actions.delete', 'trash', 'querysearch.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'querysearch.actions.delete_selected', 'querysearch.actions.delete_selected_confirm');

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->querySearchService->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = $request->get($form->getName(), [])['reordered'] ?? [];
                        $this->querySearchService->reorderByIds(\array_flip(\array_values($newOrder)));
                        break;
                    default:
                        $this->logger->error('log.controller.querysearch.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.querysearch.unknown_action');
            }

            return $this->redirectToRoute('ems_core_querysearch_index');
        }

        return $this->render('@EMSCore/querysearch/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $querySearch = new QuerySearch();

        return $this->edit($request, $querySearch, '@EMSCore/querysearch/add.html.twig');
    }

    public function edit(Request $request, QuerySearch $querySearch, string $view = '@EMSCore/querysearch/edit.html.twig'): Response
    {
        $form = $this->createForm(QuerySearchType::class, $querySearch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->querySearchService->update($querySearch);

            return $this->redirectToRoute('ems_core_querysearch_index');
        }

        return $this->render($view, [
            'form' => $form->createView(),
        ]);
    }

    public function delete(QuerySearch $querySearch): Response
    {
        $this->querySearchService->delete($querySearch);

        return $this->redirectToRoute('ems_core_querysearch_index');
    }
}
