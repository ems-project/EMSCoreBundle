<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FilterRepository;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EnvironmentService implements EntityServiceInterface
{
    /** @var Registry */
    private $doctrine;

    /** @var Session */
    private $session;

    /** @var array */
    private $environments = [];

    /** @var array */
    private $notSnapshotEnvironments = [];

    /** @var array */
    private $environmentsById = [];

    /** @var UserService */
    private $userService;

    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var Logger */
    private $logger;

    /** @var ElasticaService */
    private $elasticaService;

    /** @var string */
    private $instanceId;

    private EnvironmentRepository $environmentRepository;

    public function __construct(
        Registry $doctrine,
        Session $session,
        UserService $userService,
        AuthorizationCheckerInterface $authorizationChecker,
        Logger $logger,
        ElasticaService $elasticaService,
        string $instanceId
    ) {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->userService = $userService;
        $this->authorizationChecker = $authorizationChecker;
        $this->logger = $logger;
        $this->elasticaService = $elasticaService;
        $this->instanceId = $instanceId;

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

    public function getEnvironmentNames(): array
    {
        return \array_keys($this->getEnvironments());
    }

    /**
     * @deprecated  https://github.com/ems-project/EMSCoreBundle/issues/281
     */
    public function getNotSnapshotEnvironments(): array
    {
        if ([] !== $this->notSnapshotEnvironments) {
            return $this->notSnapshotEnvironments;
        }

        $environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findBy(['snapshot' => false]);

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->notSnapshotEnvironments[$environment->getName()] = $environment;
        }

        return $this->notSnapshotEnvironments;
    }

    /**
     * @deprecated  https://github.com/ems-project/EMSCoreBundle/issues/281
     */
    public function getNotSnapshotEnvironmentsNames(): array
    {
        return \array_keys($this->getNotSnapshotEnvironments());
    }

    public function getEnvironmentsById(): array
    {
        if ([] !== $this->environmentsById) {
            return $this->environmentsById;
        }

        $environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findAll();
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
        $filterRepository = $this->doctrine->getRepository('EMSCoreBundle:Filter');
        /** @var Filter $filter */
        foreach ($filterRepository->findAll() as $filter) {
            $filters[$filter->getName()] = $filter->getOptions();
        }

        $analyzers = [];

        /** @var AnalyzerRepository $analyzerRepository */
        $analyzerRepository = $this->doctrine->getRepository('EMSCoreBundle:Analyzer');
        /** @var Analyzer $analyzer */
        foreach ($analyzerRepository->findAll() as $analyzer) {
            $analyzers[$analyzer->getName()] = $analyzer->getOptions($esVersion);
        }

        $settingsSectionLabel = \version_compare($esVersion, '7.0') >= 0 ? 'settings' : 'index';

        return [
            $settingsSectionLabel => [
                'max_result_window' => 50000,
                'analysis' => [
                    'filter' => $filters,
                    'analyzer' => $analyzers,
                ],
            ],
        ];
    }

    public function getEnvironmentsStats()
    {
        /** @var EnvironmentRepository $repo */
        $repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment');
        $stats = $repo->getEnvironmentsStats();

        foreach ($stats as &$item) {
            $item['deleted'] = $repo->getDeletedRevisionsPerEnvironment($item['environment']);
        }

        return $stats;
    }

    /**
     * @param string $name
     *
     * @return Environment|false
     */
    public function getAliasByName($name)
    {
        return $this->getByName($name);
    }

    /**
     * @return Environment|false
     */
    public function getByName(string $name)
    {
        if (isset($this->getEnvironments()[$name])) {
            return $this->getEnvironments()[$name];
        }

        return false;
    }

    public function giveByName(string $name): Environment
    {
        if (false === $environment = $this->getByName($name)) {
            throw new \RuntimeException(\sprintf('Could not find environment named "%s"', $name));
        }

        return $environment;
    }

    /**
     * @param string $id
     *
     * @return Environment|false
     *
     * @deprecated cant find usage of this function, should be removed if proven so!
     */
    public function getById($id)
    {
        if (isset($this->getEnvironmentsById()[$id])) {
            return $this->getEnvironmentsById()[$id];
        }

        return false;
    }

    public function getManagedEnvironement()
    {
        return \array_filter($this->getEnvironments(), function (Environment $environment) {
            return $environment->getManaged();
        });
    }

    /**
     * @return Environment[]
     */
    public function getUnmanagedEnvironments(): array
    {
        return \array_filter($this->getEnvironments(), function (Environment $environment) {
            return !$environment->getManaged();
        });
    }

    /**
     * @deprecated use getEnvironments directly!
     *
     * @return bool|array
     */
    public function getAll()
    {
        if ([] === $this->getEnvironments()) {
            return false;
        }

        return $this->getEnvironments();
    }

    public function getAllInMyCircle()
    {
        if ($this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
            return $this->getEnvironments();
        }

        $user = $this->userService->getCurrentUser();

        return \array_filter($this->getEnvironments(), function ($environment) use ($user) {
            /** @var Environment $environment */
            if (empty($environment->getCircles())) {
                return true;
            }

            return \count(\array_intersect($user->getCircles(), $environment->getCircles())) >= 1;
        });
    }

    /**
     * @deprecated  https://github.com/ems-project/EMSCoreBundle/issues/281
     */
    public function clearCache(): void
    {
        $this->environments = [];
        $this->notSnapshotEnvironments = [];
        $this->environmentsById = [];
    }

    public function updateEnvironment(Environment $environment): void
    {
        $em = $this->doctrine->getManager();
        if (null === $environment->getAlias()) {
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
