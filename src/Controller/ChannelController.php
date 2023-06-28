<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\ChannelDataTableType;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\ChannelType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\Channel\ChannelService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ChannelController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ChannelService $channelService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(ChannelDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->channelService->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                        $this->channelService->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.channel.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.channel.unknown_action');
            }

            return $this->redirectToRoute('ems_core_channel_index');
        }

        return $this->render('@EMSCore/channel/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $channel = new Channel();

        return $this->edit($request, $channel, '@EMSCore/channel/add.html.twig');
    }

    public function edit(Request $request, Channel $channel, string $view = '@EMSCore/channel/edit.html.twig'): Response
    {
        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->channelService->update($channel);

            return $this->redirectToRoute('ems_core_channel_index');
        }

        return $this->render($view, [
            'form' => $form->createView(),
        ]);
    }

    public function delete(Channel $channel): Response
    {
        $this->channelService->delete($channel);

        return $this->redirectToRoute('ems_core_channel_index');
    }

    public function menu(): Response
    {
        return $this->render("@$this->templateNamespace/channel/menu.html.twig", [
            'channels' => $this->channelService->getAll(),
        ]);
    }
}
