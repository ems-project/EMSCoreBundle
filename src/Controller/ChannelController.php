<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\ChannelDataTableType;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ChannelType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\Channel\ChannelService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class ChannelController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly ChannelService $channelService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(ChannelDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'channel'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->channelService->deleteByIds($table->getSelected()),
                TableType::REORDER_ACTION => $this->channelService->reorderByIds(
                    ids: TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute('ems_core_channel_index');
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-eye',
            'title' => t('type.title_overview', ['type' => 'channel'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'page' => t('key.channels', [], 'emsco-core'),
            ],
        ]);
    }

    public function add(Request $request): Response
    {
        $channel = new Channel();

        return $this->edit($request, $channel, "@$this->templateNamespace/channel/add.html.twig");
    }

    public function edit(Request $request, Channel $channel, ?string $view = null): Response
    {
        if (null === $view) {
            $view = "@$this->templateNamespace/channel/edit.html.twig";
        }
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
