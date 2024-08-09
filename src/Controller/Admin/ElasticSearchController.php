<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentOrphanIndexDataTableType;
use EMS\CoreBundle\Exception\NotFoundException;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\IndexService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class ElasticSearchController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly IndexService $indexService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly string $templateNamespace,
    ) {
    }

    public function orphanIndexes(Request $request): Response
    {
        $table = $this->dataTableFactory->create(EnvironmentOrphanIndexDataTableType::class);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->deleteOrphanIndexes(...$table->getSelected()),
                EnvironmentOrphanIndexDataTableType::ACTION_DELETE_ALL => $this->deleteOrphanIndexes(),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core'))
            };

            return $this->redirectToRoute(Routes::ADMIN_ELASTIC_ORPHAN);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-chain-broken',
            'title' => t('key.orphan_indexes', [], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->environments()->add(
                label: t('key.orphan_indexes', [], 'emsco-core'),
                icon: 'fa fa-chain-broken',
                route: Routes::ADMIN_ELASTIC_ORPHAN
            ),
        ]);
    }

    public function deleteOrphanIndex(string $name): RedirectResponse
    {
        try {
            $this->indexService->deleteIndex($name);
            $this->logger->messageNotice(t('log.notice.deleted_orphan_index', ['index' => $name], 'emsco-core'));
        } catch (NotFoundException) {
            $this->logger->messageError(t('log.warning.index_not_found', ['index' => $name], 'emsco-core'));
        } catch (\Throwable $e) {
            $this->logger->messageError(t('log.error.delete_failed', [], 'emsco-core'), [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->redirectToRoute(Routes::ADMIN_ELASTIC_ORPHAN);
    }

    private function deleteOrphanIndexes(string ...$indexes): void
    {
        try {
            if (0 === \count($indexes)) {
                $this->indexService->deleteOrphanIndexes();
            } else {
                $this->indexService->deleteIndexes(...$indexes);
            }

            $this->logger->messageNotice(t('log.notice.deleted_orphan_indexes', [], 'emsco-core'));
        } catch (\Throwable $e) {
            $this->logger->messageError(t('log.error.delete_failed', [], 'emsco-core'), [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
