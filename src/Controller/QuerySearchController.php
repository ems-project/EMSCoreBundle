<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\QuerySearchDataTableType;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\QuerySearchType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\QuerySearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class QuerySearchController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly QuerySearchService $querySearchService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(QuerySearchDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'query_search'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->querySearchService->deleteByIds($table->getSelected()),
                TableType::REORDER_ACTION => $this->querySearchService->reorderByIds(
                    ids: TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute('ems_core_query_search_index');
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-list-alt',
            'title' => t('type.title_overview', ['type' => 'query_search'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'page' => t('key.query_searches', [], 'emsco-core'),
            ],
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
