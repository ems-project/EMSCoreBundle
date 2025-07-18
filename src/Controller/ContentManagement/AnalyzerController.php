<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Mapping\AnalyzerManager;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\Mapping\AnalyzerDataTableType;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\AnalyzerType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use function Symfony\Component\Translation\t;

class AnalyzerController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly AnalyzerManager $analyzerManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger
    ) {
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $analyzer = new Analyzer();

        $form = $this->createForm(AnalyzerType::class, $analyzer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->analyzerManager->update($analyzer);

            $this->logger->notice('log.analyzer.created', [
                'analyzer_name' => $analyzer->getName(),
                'analyzer_id' => $analyzer->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
            ]);

            return $this->redirectToRoute(Routes::ANALYZER_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'analyzer'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'analyzer'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'analyzer'], 'emsco-core')
            ),
            'notice' => t('type.notice_message', ['type' => 'analyzer'], 'emsco-core'),
        ]);
    }

    public function delete(Analyzer $analyzer): RedirectResponse
    {
        $this->analyzerManager->delete($analyzer);

        $this->logger->notice('log.analyzer.deleted', [
            'analyzer_name' => $analyzer->getName(),
            'analyzer_id' => $analyzer->getId(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute(Routes::ANALYZER_INDEX);
    }

    public function edit(Analyzer $analyzer, Request $request): Page|RedirectResponse
    {
        $form = $this->createForm(AnalyzerType::class, $analyzer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->analyzerManager->update($analyzer);

            $this->logger->notice('log.analyzer.updated', [
                'analyzer_name' => $analyzer->getName(),
                'analyzer_id' => $analyzer->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);

            return $this->redirectToRoute(Routes::ANALYZER_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'analyzer', 'label' => $analyzer->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'analyzer'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'analyzer', 'label' => $analyzer->getLabel()], 'emsco-core')
            ),
            'notice' => t('type.notice_message', ['type' => 'analyzer'], 'emsco-core'),
        ]);
    }

    public function export(Analyzer $analyzer): Response
    {
        $response = new JsonResponse($analyzer);
        $response->setEncodingOptions(JSON_PRETTY_PRINT);
        $disposition = $response->headers->makeDisposition(
            disposition: ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            filename: $analyzer->getName().'.json'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(AnalyzerDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'analyzer'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->analyzerManager->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->analyzerManager->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ANALYZER_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView()],
            'icon' => 'fa fa-signal',
            'title' => t('type.title_overview', ['type' => 'analyzer'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'analyzer'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.analyzers', [], 'emsco-core'),
            icon: 'fa fa-signal',
            route: 'emsco_analyzer_index',
        );
    }
}
