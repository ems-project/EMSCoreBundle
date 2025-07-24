<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Service\Channel\ChannelService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class ChannelController extends AbstractController
{
    public function __construct(
        private readonly ChannelService $channelService,
        private readonly string $templateNamespace,
    ) {
    }

    public function menu(): Response
    {
        return $this->render("@$this->templateNamespace/channel/menu.html.twig", [
            'channels' => $this->channelService->getAll(),
        ]);
    }
}
