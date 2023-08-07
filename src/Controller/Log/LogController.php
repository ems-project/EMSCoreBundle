<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Log;

use EMS\CommonBundle\Entity\Log;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\DataTable\Type\LogDataTableType;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LogController extends AbstractController
{
    public function __construct(
        private readonly LogManager $logManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(LogDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                match ($action->getName()) {
                    EntityTable::DELETE_ACTION => $this->logManager->deleteByIds($table->getSelected()),
                    default => $this->logger->error('log.controller.log.unknown_action'),
                };
            } else {
                $this->logger->error('log.controller.log.unknown_action');
            }

            return $this->redirectToRoute(Routes::LOG_INDEX);
        }

        return $this->render('@EMSCore/log/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function view(Log $log): Response
    {
        return $this->render('@EMSCore/log/view.html.twig', [
            'log' => $log,
        ]);
    }

    public function delete(Log $log): Response
    {
        $this->logManager->delete($log);

        return $this->redirectToRoute(Routes::LOG_INDEX);
    }
}
