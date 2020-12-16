<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Form\ChannelType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\ChannelService;
use EMS\CoreBundle\Twig\Table\ChannelTable;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ChannelController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ChannelService
     */
    private $channelService;

    public function __construct(LoggerInterface $logger, ChannelService $channelService)
    {
        $this->logger = $logger;
        $this->channelService = $channelService;
    }

    public function index(Request $request): Response
    {
        $channels = $this->channelService->getAll();
        $table = new ChannelTable($channels);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $deleteAction = $form->get(ChannelTable::DELETE_ACTION);
            $reorderAction = $form->get(TableType::REORDER_ACTION);
            if ($deleteAction instanceof SubmitButton && $deleteAction->isClicked()) {
                $this->channelService->deleteByIds($table->getSelected());
            } elseif ($reorderAction instanceof SubmitButton && $reorderAction->isClicked()) {
                $newOrder = $request->get('table', [])['reordered'] ?? [];
                $this->channelService->reorderByIds(\array_flip(\array_values($newOrder)));
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
