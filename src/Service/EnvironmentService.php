<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Environment\EnvironmentsRevision;
use EMS\CoreBundle\Entity\Analyzer;
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
        private readonly string $instanceId
    ) {
        $environmentRepository = $doctrine->getRepository(Environment::class);
        if (!$environmentRepository instanceof EnvironmentRepository) {
            throw new \RuntimeException('Not found repository');
        }
        $this->environmentRepository = $environmentRepository;
    }

    public function createEnvironment(string $name, bool $updateReferrers = false): Environment
    {
        if (!$this->validateEnvironmentName($name)) {
            throw new \Exception('An environment name must respects the following regex /^[a-z][a-z0-9\-_]*$/');
        }

        $environment = new Environment();
        $environment->setName($name);
        $environment->setAlias($this->generateAlias($environment));
        $environment->setManaged(true);
        $environment->setUpdateReferrers($updateReferrers);

        try {
            $em = $this->doctrine->getManager();
            $em->persist($environment);
            $em->flush();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

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

        $em = $this->doctrine->getManager();
        $em->persist($environment);
        $em->flush();
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

    /**
     * @return array<mixed>
     */
    public function getEnvironmentsStats(): array
    {
        /** @var EnvironmentRepository $repo */
        $repo = $this->doctrine->getManager()->getRepository(Environment::class);
        $stats = $repo->getEnvironmentsStats();

        foreach ($stats as &$item) {
            $item['deleted'] = $repo->getDeletedRevisionsPerEnvironment($item['environment']);
        }

        return $stats;
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

    public function giveById(int $id): Environment
    {
        $environment = $this->getEnvironmentsById()[$id] ?? false;

        if (!$environment) {
            throw new \RuntimeException(\sprintf('Could not find environment with the id "%d"', $id));
        }

        return $environment;
    }

    /**
     * @deprecated cant find usage of this function, should be removed if proven so!
     */
    public function getById(int $id): Environment|false
    {
        return $this->getEnvironmentsById()[$id] ?? false;
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

        $userPublishEnvironments = new ArrayCollection($circleEnvironments);

        return $userPublishEnvironments->filter(function (Environment $e) {
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

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        return $this->environmentRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'environment';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [
            'environments',
            'Environment',
            'Environments',
        ];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->environmentRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->environmentRepository->findByName($name);
    }

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
        $environment->setAlias($this->generateAlias($environment));

        $this->environmentRepository->create($environment);

        return $environment;
    }

    public function createEntityFromJson(string $json, string $name = null): EntityInterface
    {
        $meta = JsonClass::fromJsonString($json);
        $environment = $meta->jsonDeserialize();
        if (!$environment instanceof Environment) {
            throw new \RuntimeException('Unexpected non Environment object');
        }
        if (null !== $name && $environment->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Unexpected mismatched environment name : %s vs %s', $name, $environment->getName()));
        }
        $environment->setAlias($this->generateAlias($environment));

        $this->environmentRepository->create($environment);

        return $environment;
    }

    protected function generateAlias(Environment $environment): string
    {
        return $this->instanceId.$environment->getName();
    }

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
}
