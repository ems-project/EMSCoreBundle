<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\TemplateRepository;
use Psr\Log\LoggerInterface;

final class ActionService implements EntityServiceInterface
{
    public function __construct(private readonly TemplateRepository $templateRepository, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @return Template[]
     */
    public function getAll(ContentType $contentType): array
    {
        return $this->templateRepository->getAll($contentType);
    }

    public function update(Template $template): void
    {
        $this->templateRepository->create($template);
    }

    public function delete(Template $template): void
    {
        $name = $template->getName();
        $label = $template->getLabel();
        $this->templateRepository->delete($template);
        $this->logger->warning('log.service.action.delete', [
            'name' => $name,
            'label' => $label,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->templateRepository->getByIds($ids) as $channel) {
            $this->delete($channel);
        }
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $action = $this->templateRepository->getById((int) $id);
            $action->setOrderKey($counter++);
            $this->templateRepository->create($action);
        }
    }

    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @param mixed $context
     *
     * @return Template[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected non-ContentType object');
        }

        return $this->templateRepository->get($from, $size, $orderField, $orderDirection, $searchValue, $context);
    }

    public function getEntityName(): string
    {
        return 'action';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected non-ContentType object');
        }

        return $this->templateRepository->counter($searchValue, $context);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->templateRepository->getById((int) $name);
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
