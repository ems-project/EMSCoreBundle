<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\QuerySearchDataTableType;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\QuerySearchType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\QuerySearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class QuerySearchController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly QuerySearchService $querySearchService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(QuerySearchDataTableType::class);

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

        return $this->render("@$this->templateNamespace/query-search/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $querySearch = new QuerySearch();

        return $this->edit($request, $querySearch, "@$this->templateNamespace/query-search/add.html.twig");
    }

    public function edit(Request $request, QuerySearch $querySearch, ?string $view = null): Response
    {
        if (null == $view) {
            $view = "@$this->templateNamespace/query-search/edit.html.twig";
        }
        $form = $this->createForm(QuerySearchType::class, $querySearch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->querySearchService->update($querySearch);

            return $this->redirectToRoute('ems_core_query_search_index');
        }

        return $this->render($view, [
            'form' => $form->createView(),
        ]);
    }

    public function delete(QuerySearch $querySearch): RedirectResponse
    {
        $this->querySearchService->delete($querySearch);

        return $this->redirectToRoute('ems_core_query_search_index');
    }
}
