<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Form\ChannelType;
use EMS\CoreBundle\Service\ChannelService;
use EMS\CoreBundle\Twig\Table\ChannelTable;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function index(): Response
    {
        $channels = $this->channelService->getAll();
        $table = new ChannelTable($channels);

        return $this->render('@EMSCore/table/index.html.twig', [
            'table' => $table,
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
}
