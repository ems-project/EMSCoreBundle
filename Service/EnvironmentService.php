<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Entity\Analyzer;

class EnvironmentService
{
    /**@var Registry $doctrine */
    private $doctrine;

    /**@var Session $session*/
    private $session;

    /** @var array */
    private $environments = [];

    /** @var array */
    private $notSnapshotEnvironments = [];

    /** @var array */
    private $environmentsById = [];

    /**@var UserService $userService*/
    private $userService;

    /** @var AuthorizationCheckerInterface $authorizationChecker*/
    private $authorizationChecker;

    /**@var Container $container*/
    private $container;

    /** @var Logger */
    private $logger;

    /**@var Client */
    private $client;

    /** @var ContentTypeService
    private $contentTypeService;
     * */

    private $singleTypeIndex;

    public function __construct(
        Registry $doctrine,
        Session $session,
        UserService $userService,
        AuthorizationCheckerInterface $authorizationChecker,
        Container $container,
        Logger $logger,
        Client $client,
        $singleTypeIndex
    ) {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->userService = $userService;
        $this->authorizationChecker = $authorizationChecker;
        $this->container = $container;
        $this->logger = $logger;
        $this->client = $client;
        $this->singleTypeIndex = $singleTypeIndex;
    }

    public function createEnvironment(string $name, $snapshot = false): Environment
    {
        if (!$this->validateEnvironmentName($name)) {
            throw new \Exception('An environment name must respects the following regex /^[a-z][a-z0-9\-_]*$/');
        }

        $environment = new Environment();
        $environment->setName($name);
        $environment->setAlias($this->container->getParameter('ems_core.instance_id') . $environment->getName());
        $environment->setManaged(true);
        $environment->setSnapshot($snapshot);

        try {
            $em = $this->doctrine->getManager();
            $em->persist($environment);
            $em->flush();
        } catch (\Exception $e) {
            dump($e->getMessage());
        }

        $indexName = $environment->getAlias() . AppController::getFormatedTimestamp();
        $this->client->indices()->create([
            'index' => $indexName,
            'body' => $this->getIndexAnalysisConfiguration(),
        ]);

        $contentTypeService = $this->container->get('ems.service.contenttype');
        foreach ($contentTypeService->getAll() as $contentType) {
            $contentTypeService->updateMapping($contentType, $indexName);
        }

        $this->client->indices()->putAlias([
            'index' => $indexName,
            'name' => $environment->getAlias()
        ]);

        $this->logger->notice('log.environment.created', [
            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
        ]);

        return $environment;
    }

    public function validateEnvironmentName(string $name): bool
    {
        return \preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && strlen($name) <= 100;
    }

    public function setSnapshotTag(Environment $environment, bool $value = true): void
    {
        $environment->setSnapshot($value);

        $em = $this->doctrine->getManager();
        $em->persist($environment);
        $em->flush();
    }

    public function getEnvironments(): array
    {
        if ($this->environments !== []) {
            return $this->environments;
        }

        $environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findAll();

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->environments[$environment->getName()] = $environment;
        }

        return $this->environments;
    }

    public function getEnvironmentNames(): array
    {
        return array_keys($this->getEnvironments());
    }

    public function getNotSnapshotEnvironments(): array
    {
        if ($this->notSnapshotEnvironments !== []) {
            return $this->notSnapshotEnvironments;
        }

        $environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findBy(['snapshot' => false]);

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->notSnapshotEnvironments[$environment->getName()] = $environment;
        }

        return $this->notSnapshotEnvironments;
    }

    public function getNotSnapshotEnvironmentsNames(): array
    {
        return array_keys($this->getNotSnapshotEnvironments());
    }

    public function getEnvironmentsById(): array
    {
        if ($this->environmentsById !== []) {
            return $this->environmentsById;
        }

        $environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findAll();
        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $this->environmentsById[$environment->getId()] = $environment;
        }

        return $this->environmentsById;
    }

    public function getNewIndexName(Environment $environment, ContentType $contentType)
    {
        if ($this->singleTypeIndex) {
            return $environment->getAlias() . '_' . $contentType->getName() . AppController::getFormatedTimestamp();
        }
        return $environment->getAlias() . AppController::getFormatedTimestamp();
    }

    public function getIndexAnalysisConfiguration()
    {
        $filters = [];

        /**@var FilterRepository $filterRepository*/
        $filterRepository = $this->doctrine->getRepository('EMSCoreBundle:Filter');
        /**@var Filter $filter*/
        foreach ($filterRepository->findAll() as $filter) {
            $filters[$filter->getName()] = $filter->getOptions();
        }

        $analyzers = [];

        /**@var AnalyzerRepository $analyzerRepository*/
        $analyzerRepository = $this->doctrine->getRepository('EMSCoreBundle:Analyzer');
        /**@var Analyzer $analyzer*/
        foreach ($analyzerRepository->findAll() as $analyzer) {
            $analyzers[$analyzer->getName()] = $analyzer->getOptions();
        }

        $out = [
            'index' => [
                'max_result_window' =>     50000,
                'analysis' => [
                    'filter' => $filters,
                    'analyzer' => $analyzers,
                ]
            ]
        ];

        return $out;
    }

    public function getEnvironmentsStats()
    {
        /**@var EnvironmentRepository $repo*/
        $repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment');
        $stats = $repo->getEnvironmentsStats();

        foreach ($stats as &$item) {
            $item['deleted'] = $repo->getDeletedRevisionsPerEnvironment($item['environment']);
        }

        return $stats;
    }

    /**
     *
     * @param string $name
     * @return Environment|false
     */
    public function getAliasByName($name)
    {
        return $this->getByName($name);
    }

    /**
     *
     * @param string $name
     * @return Environment|false
     */
    public function getByName($name)
    {
        if (isset($this->getEnvironments()[$name])) {
            return $this->getEnvironments()[$name];
        }
        return false;
    }

    /**
     * @param string $id
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
        return array_filter($this->getEnvironments(), function (Environment $environment) {
            return $environment->getManaged();
        });
    }

    /**
     * @deprecated use getEnvironments directly!
     * @return boolean|array
     */
    public function getAll()
    {
        if ($this->getEnvironments() === []) {
            return false;
        }
        return $this->getEnvironments();
    }

    public function getAllInMyCircle()
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return $this->getEnvironments();
        };

        $user = $this->userService->getCurrentUser();
        return array_filter($this->getEnvironments(), function ($environment) use ($user) {
            /** @var Environment $environment*/
            if (empty($environment->getCircles())) {
                return true;
            }

            return count(array_intersect($user->getCircles(), $environment->getCircles())) >= 1;
        });
    }

    public function clearCache()
    {
        $this->environments = [];
        $this->notSnapshotEnvironments = [];
        $this->environmentsById = [];
    }
}
