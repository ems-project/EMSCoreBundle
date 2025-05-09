<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Handler;

use EMS\CommonBundle\Common\Ai\OpenAiRequest;
use EMS\CommonBundle\Common\Ai\OpenAiService;
use EMS\CoreBundle\Core\Action\ActionService;
use EMS\CoreBundle\Core\Mercure\MercureService;
use EMS\CoreBundle\Core\Messenger\Message\ActionMessage;
use EMS\CoreBundle\Entity\CacheAction;
use EMS\Helpers\Standard\Hash;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ActionHandler
{
    public function __construct(
        private readonly ActionService $actionService,
        private readonly OpenAiService $openAiService,
        private readonly MercureService $mercureService,
    ) {
    }

    public function __invoke(ActionMessage $message): void
    {
        $request = $message->request;
        $requestHash = Hash::array($request);

        $response = $this->actionService->getCacheResponse($requestHash)?->getResponse();

        if (null === $response) {
            $response = $this->openAiService->v1Responses(new OpenAiRequest($request))->toArray();
            $this->actionService->cacheResponse(new CacheAction(
                requestHash: $requestHash,
                request: $request,
                response: $response
            ));
        }

        $this->mercureService->publishForUser([
            'response' => $response,
            'revisionId' => (string) $message->revisionId,
        ], $message->createdBy);
    }
}
