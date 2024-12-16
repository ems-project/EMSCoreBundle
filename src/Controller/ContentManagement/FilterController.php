<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Mapping\FilterManager;
use EMS\CoreBundle\DataTable\Type\Mapping\FilterDataTableType;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\FilterType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use function Symfony\Component\Translation\t;

class FilterController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly FilterManager $filterManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly string $templateNamespace,
    ) {
    }

    public function add(Request $request): Response
    {
        $filter = new Filter();
        $form = $this->createForm(FilterType::class, $filter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->filterManager->update($filter);

            return $this->redirectToRoute(Routes::FILTER_INDEX);
        }

        return $this->render("@$this->templateNamespace/filter/add.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function delete(Filter $filter): Response
    {
        $this->filterManager->delete($filter);
        $this->logger->notice('log.filter.deleted', ['filter_name' => $filter->getName()]);

        return $this->redirectToRoute(Routes::FILTER_INDEX);
    }

    public function edit(Filter $filter, Request $request): Response
    {
        $form = $this->createForm(FilterType::class, $filter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->filterManager->update($filter);

            return $this->redirectToRoute(Routes::FILTER_INDEX);
        }

        return $this->render("@$this->templateNamespace/filter/edit.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function export(Filter $filter): Response
    {
        $response = new JsonResponse($filter);
        $response->setEncodingOptions(JSON_PRETTY_PRINT);
        $disposition = $response->headers->makeDisposition(
            disposition: ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            filename: $filter->getName().'.json'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(FilterDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'filter'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->filterManager->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->filterManager->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::FILTER_INDEX);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-filter',
            'title' => t('type.title_overview', ['type' => 'filter'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'page' => t('key.filters', [], 'emsco-core'),
            ],
        ]);
    }
}
