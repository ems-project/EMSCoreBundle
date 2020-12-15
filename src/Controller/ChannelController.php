<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Service\ChannelService;
use EMS\CoreBundle\Twig\Table\ChannelTable;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
