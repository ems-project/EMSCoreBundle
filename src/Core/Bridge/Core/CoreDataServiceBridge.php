<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Bridge\Core;

use EMS\CommonBundle\Common\Bridge\Core\CoreBridgeResponse;
use EMS\CommonBundle\Common\Bridge\Core\CoreBridgeTrait;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Contracts\Bridge\Core\CoreDataBridgeInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\NotFoundException;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class CoreDataServiceBridge implements CoreDataBridgeInterface
{
    use CoreBridgeTrait;

    public function __construct(
        private ContentType $contentType,
        private DataService $dataService,
        private RevisionService $revisionService,
    ) {
    }

    #[\Override]
    public function autoSave(int $revisionId, array $rawData): CoreBridgeResponse
    {
        return $this->response(function () use ($revisionId, $rawData) {
            $revision = $this->revisionService->getByRevisionId($revisionId);
            $this->revisionService->autoSave($revision, $rawData);
        });
    }

    #[\Override]
    public function create(array $rawData = []): CoreBridgeResponse
    {
        return $this->response(function () use ($rawData) {
            $revision = $this->revisionService->create(
                contentType: $this->contentType->validate(),
                rawData: $rawData
            );

            return ['revisionId' => $revision->getId()];
        });
    }

    #[\Override]
    public function delete(string $uuid): CoreBridgeResponse
    {
        return $this->response(fn () => $this->dataService->delete($this->contentType->validate(), $uuid));
    }

    #[\Override]
    public function discard(int $revisionId): CoreBridgeResponse
    {
        return $this->response(function () use ($revisionId) {
            $revision = $this->revisionService->getByRevisionId($revisionId);
            $this->dataService->discardDraft($revision);
        });
    }

    #[\Override]
    public function finalize(int $revisionId, array $rawData = []): CoreBridgeResponse
    {
        return $this->response(function () use ($revisionId, $rawData) {
            $revision = $this->dataService->getRevisionById($revisionId, $this->contentType);

            if ([] !== $rawData) {
                $this->revisionService->autoSave($revision, $rawData);
            }

            $revision->autoSaveToRawData();
            $newRevision = $this->dataService->finalizeDraft($revision);

            $this->dataService->refresh($this->contentType->giveEnvironment());

            return ['uuid' => $newRevision->giveOuuid()];
        });
    }

    #[\Override]
    public function getDraft(int $revisionId): CoreBridgeResponse
    {
        return $this->response(function () use ($revisionId) {
            try {
                $revision = $this->revisionService->getByRevisionId($revisionId);
            } catch (\Throwable) {
                throw new NotFoundException('Revision not found');
            }

            if (!$revision->isDraft()) {
                throw new HttpException(Response::HTTP_NOT_ACCEPTABLE, 'not in draft');
            }

            return [
                'id' => $revision->getId(),
                'data' => $revision->getDraftData(),
            ];
        });
    }

    #[\Override]
    public function initDraft(string $uuid): CoreBridgeResponse
    {
        return $this->response(function () use ($uuid) {
            $draft = $this->dataService->initNewDraft($this->contentType->validate(), $uuid);

            return ['revisionId' => $draft->getId()];
        });
    }

    #[\Override]
    public function publish(EMSLink $emsLink, string $environment): CoreBridgeResponse
    {
        return $this->response(fn () => $this->revisionService->publish($emsLink, $environment));
    }

    #[\Override]
    public function publishVersions(string $versionUuid, string $environment): CoreBridgeResponse
    {
        return $this->response(fn () => $this->revisionService->publishVersion(
            $this->contentType->validate(),
            Uuid::fromString($versionUuid),
            $environment
        ));
    }
}
