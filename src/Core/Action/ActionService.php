<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Action;

use EMS\CoreBundle\Core\Messenger\Message\ActionMessage;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\CacheAction;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\CacheActionRepository;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ActionService
{
    public function __construct(
        private readonly CacheActionRepository $repository,
        private readonly UserManager $userManager,
        private readonly MessageBusInterface $bus
    ) {
    }

    public function cacheResponse(CacheAction $cache): void
    {
        $this->repository->save($cache);
    }

    public function getById(UuidInterface $id): CacheAction
    {
        if (null === $action = $this->repository->findOneBy(['id' => $id])) {
            throw new \RuntimeException(\sprintf('Action with the id %s not found.', $id));
        }

        return $action;
    }

    public function getCacheResponse(string $requestHash): ?CacheAction
    {
        return $this->repository->findOneBy(['requestHash' => $requestHash]);
    }

    /** @param array<mixed> $request */
    public function requestFromRevision(Revision $revision, array $request): void
    {
        $user = $this->userManager->getAuthenticatedUser();

        $this->bus->dispatch(new ActionMessage(
            revisionId: $revision->getId(),
            request: $request,
            createdBy: $user->getUsername()
        ));
    }
}
