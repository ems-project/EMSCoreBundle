<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\TemplateRepository;
use Psr\Log\LoggerInterface;

final class ActionService implements EntityServiceInterface
{
    private TemplateRepository $templateRepository;
    private LoggerInterface $logger;

    public function __construct(TemplateRepository $templateRepository, LoggerInterface $logger)
    {
        $this->templateRepository = $templateRepository;
        $this->logger = $logger;
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
        if (0 === $template->getOrderKey()) {
            $template->setOrderKey($this->templateRepository->counter($template->getContentType()) + 1);
        }
        $this->templateRepository->create($template);
    }

    public function delete(Template $template): void
    {
        $name = $template->getName();
        $this->templateRepository->delete($template);
        $this->logger->warning('log.service.action.delete', [
            'name' => $name,
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
     * @param array<string, int> $ids
     */
    public function reorderByIds(array $ids): void
    {
        foreach ($this->templateRepository->getByIds(\array_keys($ids)) as $channel) {
            $channel->setOrderKey($ids[$channel->getId()] ?? 0);
            $this->templateRepository->create($channel);
        }
    }

    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @return Template[]
     */
    public function get(int $from, int $size): array
    {
        return $this->templateRepository->get($from, $size);
    }

    public function getEntityName(): string
    {
        return 'channel';
    }

    /**
     * @param mixed $context
     */
    public function count($context = null): int
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected non-ContentType object');
        }

        return $this->templateRepository->counter($context);
    }
}
