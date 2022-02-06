<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\View;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

class ViewManager implements EntityServiceInterface
{
    private ViewRepository $viewRepository;
    private LoggerInterface $logger;

    public function __construct(ViewRepository $viewRepository, LoggerInterface $logger)
    {
        $this->viewRepository = $viewRepository;
        $this->logger = $logger;
    }

    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @return View[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->viewRepository->get($context, $from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'view';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->viewRepository->counter($context, $searchValue);
    }

    public function update(View $view): void
    {
        if (0 === $view->getOrderKey()) {
            $view->setOrderKey($this->viewRepository->counter($view->getContentType()) + 1);
        }
        $this->viewRepository->create($view);
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $channel = $this->viewRepository->getById($id);
            $channel->setOrderKey($counter++);
            $this->viewRepository->create($channel);
        }
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->viewRepository->getByIds($ids) as $view) {
            $this->delete($view);
        }
    }

    public function delete(View $view): void
    {
        $name = $view->getName();
        $this->viewRepository->delete($view);
        $this->logger->warning('log.service.view.delete', [
            'name' => $name,
        ]);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->viewRepository->getById($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }
}
