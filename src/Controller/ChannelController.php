<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
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
    private LoggerInterface $logger;
    private ChannelService $channelService;

    public function __construct(LoggerInterface $logger, ChannelService $channelService)
    {
        $this->logger = $logger;
        $this->channelService = $channelService;
    }

    public function index(Request $request): Response
    {
        $table = new EntityTable($this->channelService);
        $labelColumn = $table->addColumn('channel.index.column.label', 'label');
        $labelColumn->setRouteProperty('defaultRoute');
        $labelColumn->setRouteTarget('channel_%value%');
        $table->addColumn('channel.index.column.name', 'name');
        $table->addColumn('channel.index.column.alias', 'alias');
        $table->addColumn('channel.index.column.public', 'public', [true => 'fa fa-check']);
        $table->addItemGetAction('ems_core_channel_edit', 'channel.actions.edit', 'pencil');
        $table->addItemPostAction('ems_core_channel_delete', 'channel.actions.delete', 'trash', 'channel.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'channel.actions.delete_selected', 'channel.actions.delete_selected_confirm');

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->channelService->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = $request->get($form->getName(), [])['reordered'] ?? [];
                        $this->channelService->reorderByIds(\array_flip(\array_values($newOrder)));
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
}
