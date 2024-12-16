<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Log;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Entity\Log;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\DataTable\Type\LogDataTableType;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function index(Request $request): Response
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

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-file-text',
            'title' => t('type.title_overview', ['type' => 'log'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'log'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'page' => t('key.logs', [], 'emsco-core'),
            ],
        ]);
    }

    public function view(Log $log): Response
    {
        return $this->render("@$this->templateNamespace/log/view.html.twig", [
            'log' => $log,
        ]);
    }

    public function delete(Log $log): Response
    {
        $this->logManager->delete($log);

        return $this->redirectToRoute(Routes::LOG_INDEX);
    }
}
