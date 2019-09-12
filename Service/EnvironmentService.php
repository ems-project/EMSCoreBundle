<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
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
    private $environmentsById = [];

    /**@var UserService $userService*/
    private $userService;

    /** @var AuthorizationCheckerInterface $authorizationChecker*/
    private $authorizationChecker;

    private $singleTypeIndex;

    public function __construct(Registry $doctrine, Session $session, UserService $userService, AuthorizationCheckerInterface $authorizationChecker, $singleTypeIndex)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->userService = $userService;
        $this->authorizationChecker = $authorizationChecker;
        $this->singleTypeIndex = $singleTypeIndex;
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
        return array_filter($this->getEnvironments(), function ($name, $environment) use ($user) {
            /** @var Environment $environment*/
            if (empty($environment->getCircles())) {
                return true;
            }

            return count(array_intersect($user->getCircles(), $environment->getCircles())) >= 1;
        });
    }
}
