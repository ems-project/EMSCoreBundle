<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Bridge\Core;

use EMS\CommonBundle\Common\Bridge\Core\CoreBridgeResponse;
use EMS\CommonBundle\Common\Bridge\Core\CoreBridgeTrait;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\EMSLinkCollection;
use EMS\CommonBundle\Contracts\Bridge\Core\CoreInfoBridgeInterface;
use EMS\CoreBundle\Service\Revision\RevisionService;

readonly class CoreInfoServiceBridge implements CoreInfoBridgeInterface
{
    use CoreBridgeTrait;

    public function __construct(private RevisionService $revisionService)
    {
    }

    #[\Override]
    public function documents(array $environments, EMSLink ...$emsLinks): CoreBridgeResponse
    {
        return $this->response(fn () => $this->revisionService->getInfos(
            $environments,
            EMSLinkCollection::fromArray(...$emsLinks)
        ));
    }
}
