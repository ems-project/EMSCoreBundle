<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Repository\TemplateRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment as Twig;

final readonly class ActionService implements EntityServiceInterface
{
    public function __construct(
        private TemplateRepository $templateRepository,
        private LoggerInterface $logger,
        private SearchService $searchService,
        private Twig $twig,
        private JobService $jobService,
    ) {
    }

    public function giveById(int $id): Template
    {
        if (null === $action = $this->templateRepository->findOneBy(['id' => $id])) {
            throw new \RuntimeException(\sprintf('Action with id "%s" could not be found.', $id));
        }

        return $action;
    }

    public function executeJobAction(UserInterface $user, Template $jobAction, string $uuid, ?Environment $environment): Job
    {
        try {
            if (RenderOptionType::JOB !== $jobAction->getRenderOption()) {
                throw new \RuntimeException('Expected render option job action.');
            }

            $contentType = $jobAction->giveContentType();
            $environment ??= $contentType->giveEnvironment();

            $document = $this->searchService->getDocument($contentType, $uuid, $environment);

            $command = $this->twig->createTemplate($jobAction->getBody())->render([
                'environment' => $environment->getName(),
                'contentType' => $contentType,
                'object' => $document,
                'source' => $document->getSource(),
            ]);

            $job = $this->jobService->createCommand($user, $command, $jobAction->getTag());

            $this->logger->notice('log.data.job.initialized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $uuid,
                'template_id' => $jobAction->getId(),
                'job_id' => $job->getId(),
                'template_name' => $jobAction->getName(),
                'template_label' => $jobAction->getLabel(),
                'environment' => $environment->getLabel(),
            ]);

            return $job;
        } catch (\Throwable $throwable) {
            $this->logger->error('log.data.job.initialize_failed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $jobAction->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $uuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $throwable->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $throwable,
                'template_name' => $jobAction->getName(),
                'template_label' => $jobAction->getLabel(),
                'environment' => $environment?->getLabel(),
            ]);
            throw $throwable;
        }
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

    public function deleteByIds(string ...$ids): void
    {
        $actions = $this->templateRepository->getByIds(...$ids);

        foreach ($actions as $action) {
            $this->delete($action);
        }
    }

    public function reorderByIds(string ...$ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $action = $this->templateRepository->getById((int) $id);
            $action->setOrderKey($counter++);
            $this->templateRepository->create($action);
        }
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @return Template[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected non-ContentType object');
        }

        return $this->templateRepository->get($from, $size, $orderField, $orderDirection, $searchValue, $context);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'action';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected non-ContentType object');
        }

        return $this->templateRepository->counter($searchValue, $context);
    }

    #[\Override]
    public function getByItemName(string $name): EntityInterface
    {
        return $this->templateRepository->getById((int) $name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
