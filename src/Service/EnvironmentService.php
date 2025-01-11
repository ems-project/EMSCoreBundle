<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Environment\EnvironmentsRevision;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FilterRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EnvironmentService implements EntityServiceInterface
{
    /** @var array<string, Environment> */
    private array $environments = [];
    /** @var array<string, Environment> */
    private array $notSnapshotEnvironments = [];
    /** @var array<int, Environment> */
    private array $environmentsById = [];

    private readonly EnvironmentRepository $environmentRepository;

    public function __construct(
        private readonly Registry $doctrine,
        private readonly UserService $userService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly LoggerInterface $logger,
        private readonly ElasticaService $elasticaService,
        private readonly AliasService $aliasService,
        private readonly string $instanceId,
    ) {
        $environmentRepository = $doctrine->getRepository(Environment::class);
        if (!$environmentRepository instanceof EnvironmentRepository) {
            throw new \RuntimeException('Not found repository');
        }
        $this->environmentRepository = $environmentRepository;
    }

    public function createEnvironment(string $name, string $color = 'default', bool $updateReferrers = false): Environment
    {
        if (!$this->validateEnvironmentName($name)) {
            throw new \Exception('An environment name must respects the following regex /^[a-z][a-z0-9\-_]*$/');
        }

        $environment = new Environment();
        $environment->setName($name);
        $environment->setColor($color);
        $environment->setAlias($this->generateAlias($environment));
        $environment->setManaged(true);
        $environment->setUpdateReferrers($updateReferrers);
        $environment->setOrderKey($this->count(context: ['managed' => true]));
        $this->environmentRepository->save($environment);

        $this->logger->notice('log.environment.created', [
            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
        ]);

        return $environment;
    }

    public function validateEnvironmentName(string $name): bool
    {
        return \preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && \strlen($name) <= 100;
    }

    public function setSnapshotTag(Environment $environment, bool $value = true): void
    {
        $environment->setSnapshot($value);

        $this->environmentRepository->save($environment);
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(): array
    {
        if ([] !== $this->environments) {
            return $this->environments;
        }

        $environments = $this->environmentRepository->findAll();

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->environments[$environment->getName()] = $environment;
        }

        return $this->environments;
    }

    public function getEnvironmentsByRevision(Revision $revision): EnvironmentsRevision
    {
        $userPublishEnvironments = $this->getUserPublishEnvironments();

        $publishRole = $revision->giveContentType()->role(ContentTypeRoles::PUBLISH);
        $hasPublishRole = $this->authorizationChecker->isGranted($publishRole);

        return new EnvironmentsRevision($revision, $userPublishEnvironments, $hasPublishRole);
    }

    /**
     * @return ReadableCollection<int, Environment>
     */
    public function getPublishedForRevision(Revision $revision, bool $excludeDefault = false): ReadableCollection
    {
        $environments = $this->environmentRepository->findAllPublishedForRevision($revision);

        if ($excludeDefault) {
            $defaultEnvironment = $revision->giveContentType()->giveEnvironment();

            return $environments->filter(fn (Environment $e) => $e->getName() !== $defaultEnvironment->getName());
        }

        return $environments;
    }

    /**
     * @return string[]
     */
    public function getEnvironmentNames(): array
    {
        return \array_keys($this->getEnvironments());
    }

    /**
     * @return ArrayCollection<int, int>
     */
    public function getDefaultEnvironmentIds(): ArrayCollection
    {
        return $this->environmentRepository->findDefaultEnvironmentIds();
    }

    /**
     * @deprecated  https://github.com/ems-project/EMSCoreBundle/issues/281
     *
     * @return array<string, Environment>
     */
    public function getNotSnapshotEnvironments(): array
    {
        if ([] !== $this->notSnapshotEnvironments) {
            return $this->notSnapshotEnvironments;
        }

        $environments = $this->doctrine->getManager()->getRepository(Environment::class)->findBy(['snapshot' => false]);

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->notSnapshotEnvironments[$environment->getName()] = $environment;
        }

        return $this->notSnapshotEnvironments;
    }

    /**
     * @deprecated  https://github.com/ems-project/EMSCoreBundle/issues/281
     *
     * @return string[]
     */
    public function getNotSnapshotEnvironmentsNames(): array
    {
        return \array_keys($this->getNotSnapshotEnvironments());
    }

    /**
     * @return array<int, Environment>
     */
    public function getEnvironmentsById(): array
    {
        if ([] !== $this->environmentsById) {
            return $this->environmentsById;
        }

        $environments = $this->doctrine->getManager()->getRepository(Environment::class)->findAll();
        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->environmentsById[$environment->getId()] = $environment;
        }

        return $this->environmentsById;
    }

    /**
     * @return array<mixed>
     */
    public function getIndexAnalysisConfiguration(): array
    {
        $esVersion = $this->elasticaService->getVersion();
        $filters = [];

        /** @var FilterRepository $filterRepository */
        $filterRepository = $this->doctrine->getRepository(Filter::class);
        /** @var Filter $filter */
        foreach ($filterRepository->findAll() as $filter) {
            $filters[$filter->getName()] = $filter->getOptions();
        }

        $analyzers = [];

        /** @var AnalyzerRepository $analyzerRepository */
        $analyzerRepository = $this->doctrine->getRepository(Analyzer::class);
        /** @var Analyzer $analyzer */
        foreach ($analyzerRepository->findAll() as $analyzer) {
            $analyzers[$analyzer->getName()] = $analyzer->getOptions($esVersion);
        }

        return [
            'settings' => [
                'max_result_window' => 50000,
                'analysis' => [
                    'filter' => $filters,
                    'analyzer' => $analyzers,
                ],
            ],
        ];
    }

    public function getAliasByName(string $name): Environment|false
    {
        return $this->getByName($name);
    }

    public function getByName(string $name): Environment|false
    {
        return $this->getEnvironments()[$name] ?? false;
    }

    public function giveByName(string $name): Environment
    {
        if (false === $environment = $this->getByName($name)) {
            throw new \RuntimeException(\sprintf('Could not find environment named "%s"', $name));
        }

        return $environment;
    }

    /**
     * @return Environment[]
     */
    public function getManagedEnvironement(): array
    {
        return \array_filter($this->getEnvironments(), fn (Environment $environment) => $environment->getManaged());
    }

    /**
     * @return Environment[]
     */
    public function getUnmanagedEnvironments(): array
    {
        return \array_filter($this->getEnvironments(), fn (Environment $environment) => !$environment->getManaged());
    }

    /**
     * @return Collection<int, Environment>
     */
    public function getUserPublishEnvironments(): Collection
    {
        if ($this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
            $circleEnvironments = $this->getEnvironments();
        } else {
            $user = $this->userService->getCurrentUser();
            $circleEnvironments = \array_filter($this->getEnvironments(), function ($environment) use ($user) {
                if (empty($environment->getCircles())) {
                    return true;
                }

                return \count(\array_intersect($user->getCircles(), $environment->getCircles())) >= 1;
            });
        }

        return (new ArrayCollection($circleEnvironments))->filter(function (Environment $e) {
            $role = $e->getRolePublish();

            return null === $role || $this->authorizationChecker->isGranted($role);
        });
    }

    public function clearCache(): self
    {
        $this->environments = [];
        $this->notSnapshotEnvironments = [];
        $this->environmentsById = [];

        return $this;
    }

    public function updateEnvironment(Environment $environment): void
    {
        $em = $this->doctrine->getManager();
        if ('' === $environment->getAlias()) {
            $environment->setAlias($this->generateAlias($environment));
        }
        $em->persist($environment);
        $em->flush();
    }

    public function reorderByIds(string ...$ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $environment = $this->environmentRepository->getById($id);
            $environment->setOrderKey($counter++);
            $this->environmentRepository->save($environment);
        }
    }

    public function deleteByIds(string ...$ids): void
    {
        foreach ($this->environmentRepository->getByIds(...$ids) as $environment) {
            $this->delete($environment);
        }
    }

    public function delete(Environment $environment): bool
    {
        if (0 !== $environment->getRevisions()->count()) {
            $this->logger->error('log.environment.not_empty', [EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName()]);

            return false;
        }

        $linked = false;
        /** @var ContentType $contentType */
        foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType) {
            if (!$contentType->getDeleted()) {
                $linked = true;
                break;
            }
        }

        if ($linked) {
            $this->logger->error('log.environment.is_default', [EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName()]);

            return false;
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentType $contentType */
        foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType) {
            $contentType->getFieldType()->setContentType();
            $em->persist($contentType->getFieldType());
            $em->remove($contentType);
        }
        $this->environmentRepository->delete($environment);
        $this->logger->notice('log.environment.deleted', [
            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
        ]);

        return true;
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->environmentRepository->makeQueryBuilder(
            isManaged: \is_array($context) ? ($context['managed'] ?? false) : null,
            searchValue: $searchValue
        );
        $qb
            ->select('e')
            ->setFirstResult($from)
            ->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('e.%s', $orderField), $orderDirection);
        }

        $environments = $qb->getQuery()->getResult();

        if (\is_array($context) && ($context['stats'] ?? false) === true) {
            $this->applyStats(...$environments);
        }

        return $environments;
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'environment';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'environments',
            'Environment',
            'Environments',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->environmentRepository
            ->makeQueryBuilder(
                isManaged: \is_array($context) ? ($context['managed'] ?? false) : null,
                searchValue: $searchValue
            )
            ->select('count(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->environmentRepository->findByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof Environment) {
            throw new \RuntimeException('unexpected non Environment entity');
        }
        $name = $entity->getName();
        $meta = JsonClass::fromJsonString($json);
        $environment = $meta->jsonDeserialize($entity);
        if (!$environment instanceof Environment) {
            throw new \RuntimeException('Unexpected non Environment object');
        }
        if ($environment->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Unexpected mismatched environment name : %s vs %s', $name, $environment->getName()));
        }
        if ($environment->getManaged()) {
            $environment->setAlias($this->generateAlias($environment));
        }

        $this->environmentRepository->save($environment);

        return $environment;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $meta = JsonClass::fromJsonString($json);
        $environment = $meta->jsonDeserialize();
        if (!$environment instanceof Environment) {
            throw new \RuntimeException('Unexpected non Environment object');
        }
        if (null !== $name && $environment->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Unexpected mismatched environment name : %s vs %s', $name, $environment->getName()));
        }
        if ($environment->getManaged()) {
            $environment->setAlias($this->generateAlias($environment));
        }

        $this->environmentRepository->save($environment);

        return $environment;
    }

    protected function generateAlias(Environment $environment): string
    {
        return $this->instanceId.$environment->getName();
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $environment = $this->getByItemName($name);
        if (null === $environment) {
            throw new \RuntimeException(\sprintf('Environment %s not found', $name));
        }
        if (!$environment instanceof Environment) {
            throw new \RuntimeException('Unexpected non Environment object');
        }
        $id = $environment->getId();
        $this->environmentRepository->delete($environment);

        return \strval($id);
    }

    private function applyStats(Environment ...$environments): void
    {
        $stats = $this->getStats();

        foreach ($environments as $environment) {
            $environment->setCounter($stats['revisions'][$environment->getId()] ?? 0);
            $environment->setDeletedRevision($stats['revisions_deleted'][$environment->getId()] ?? 0);

            try {
                if ($this->aliasService->hasAlias($environment->getAlias())) {
                    $alias = $this->aliasService->getAlias($environment->getAlias());
                    $environment->setIndexes($alias['indexes']);
                    $environment->setTotal($alias['total']);
                }
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @return array{'revisions': array<int, int>, 'revisions_deleted': array<int, int>}
     */
    private function getStats(): array
    {
        static $stats = null;

        if (null === $stats) {
            $stats = [
                'revisions' => $this->environmentRepository->countRevisionsById(),
                'revisions_deleted' => $this->environmentRepository->countRevisionsById(deleted: true),
            ];
        }

        return $stats;
    }
}
