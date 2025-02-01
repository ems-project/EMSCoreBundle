<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Bridge\Core;

use EMS\CommonBundle\Common\Composer\ComposerInfo;
use EMS\CommonBundle\Contracts\Bridge\Core\CoreBridgeInterface;
use EMS\CommonBundle\Contracts\Bridge\Core\CoreDataBridgeInterface;
use EMS\CommonBundle\Contracts\Bridge\Core\CoreInfoBridgeInterface;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;

readonly class CoreServiceBridge implements CoreBridgeInterface
{
    public function __construct(
        private DataService $dataService,
        private RevisionService $revisionService,
        private ComposerInfo $composerInfo,
        private ContentTypeService $contentTypeService,
    ) {
    }

    #[\Override]
    public function versions(): array
    {
        return $this->composerInfo->getVersionPackages();
    }

    #[\Override]
    public function data(string $contentType): CoreDataBridgeInterface
    {
        return new CoreDataServiceBridge(
            contentType: $this->contentTypeService->giveByName($contentType),
            dataService: $this->dataService,
            revisionService: $this->revisionService,
        );
    }

    #[\Override]
    public function info(): CoreInfoBridgeInterface
    {
        return new CoreInfoServiceBridge($this->revisionService);
    }
}
