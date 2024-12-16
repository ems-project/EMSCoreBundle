<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\View;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

use function Symfony\Component\Translation\t;

class ViewManager implements EntityServiceInterface
{
    public function __construct(
        private readonly ViewRepository $viewRepository,
        private readonly LocalizedLoggerInterface $logger,
    ) {
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

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
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
        $view->setName(Encoder::webalize($view->getName()));
        $this->viewRepository->create($view);
    }

    public function define(View $view, ViewDefinition $definition): void
    {
        $currentDefinition = $this->viewRepository->findOneBy([
            'definition' => $definition->value,
            'contentType' => $view->getContentType(),
        ]);

        if (null !== $currentDefinition) {
            $currentDefinition->setDefinition(null);
            $this->update($view);
        }

        $view->setDefinition($definition);
        $this->update($view);
    }

    public function undefine(View $view): void
    {
        $view->setDefinition(null);
        $this->update($view);
    }

    public function reorderByIds(string ...$ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $channel = $this->viewRepository->getById($id);
            $channel->setOrderKey($counter++);
            $this->viewRepository->create($channel);
        }
    }

    public function deleteByIds(string ...$ids): void
    {
        $views = $this->viewRepository->getByIds(...$ids);

        foreach ($views as $view) {
            $this->delete($view);
        }
    }

    public function delete(View $view): void
    {
        $this->viewRepository->delete($view);
        $this->logger->messageNotice(
            message: t('log.notice.content_type_view_deleted', ['view' => $view->getLabel()], 'emsco-core'),
            context: [
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                EmsFields::LOG_CONTENTTYPE_FIELD => $view->getContentType()->getId(),
            ]
        );
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->viewRepository->getById($name);
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
