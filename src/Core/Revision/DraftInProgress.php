<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DraftInProgress implements EntityServiceInterface
{
    final public const DISCARD_SELECTED_DRAFT = 'DISCARD_SELECTED_DRAFT';

    public function __construct(
        private readonly RevisionRepository $revisionRepository,
        private readonly UserManager $userManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context && !$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->revisionRepository->getDraftInProgress(
            from: $from,
            size: $size,
            orderField: $orderField,
            orderDirection: $orderDirection,
            searchValue: $searchValue,
            contentType: $context,
            circles: $this->userManager->getAuthenticatedUser()->getCircles(),
            isAdmin: $this->authorizationChecker->isGranted('ROLE_ADMIN')
        );
    }

    public function getEntityName(): string
    {
        return 'draft_in_progress';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context && !$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->revisionRepository->countDraftInProgress(
            searchValue: $searchValue,
            contentType: $context,
            circles: $this->userManager->getAuthenticatedUser()->getCircles(),
            isAdmin: $this->authorizationChecker->isGranted('ROLE_ADMIN')
        );
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->revisionRepository->findOneById(\intval($name));
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
