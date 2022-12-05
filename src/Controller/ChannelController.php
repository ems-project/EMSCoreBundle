<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ChannelType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\Channel\ChannelService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ChannelController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly ChannelService $channelService)
    {
    }

    public function ajaxDataTable(Request $request): Response
    {
        $table = $this->initTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initTable();

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
        return $this->render('@EMSCore/channel/menu.html.twig', [
            'channels' => $this->channelService->getAll(),
        ]);
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->channelService, $this->generateUrl('ems_core_channel_ajax_data_table'));
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('channel.index.column.label', 'label');
        $column = $table->addColumn('channel.index.column.name', 'name');
        $column->setPathCallback(fn (Channel $channel, string $baseUrl = '') => $baseUrl.$channel->getEntryPath(), '_blank');
        $table->addColumn('channel.index.column.alias', 'alias');
        $table->addColumnDefinition(new BoolTableColumn('channel.index.column.public', 'public'));
        $table->addItemGetAction('ems_core_channel_edit', 'channel.actions.edit', 'pencil');
        $table->addItemPostAction('ems_core_channel_delete', 'channel.actions.delete', 'trash', 'channel.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'channel.actions.delete_selected', 'channel.actions.delete_selected_confirm');
        $table->setDefaultOrder('label');

        return $table;
    }
}
