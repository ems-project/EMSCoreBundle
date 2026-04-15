<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Log;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Entity\Log;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\LogDataTableType;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class LogController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LogManager $logManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(LogDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->logManager->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::LOG_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'logs'],
            'icon' => 'fa fa-file-text',
            'title' => t('type.title_overview', ['type' => 'log'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'log'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function view(Log $log): Response
    {
        return $this->render(\sprintf('@%s/log/view.html.twig', $this->templateNamespace), [
            'log' => $log,
            'subTitle' => t('type.title_sub', ['type' => 'log'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                label: t('key.details', [], 'emsco-core'),
            ),
        ]);
    }

    public function delete(Log $log): Response
    {
        $this->logManager->delete($log);

        return $this->redirectToRoute(Routes::LOG_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.logs', [], 'emsco-core'),
            icon: 'fa fa-file-text',
            route: 'emsco_log_index',
        );
    }
}
