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

    private $environments;

    /**@var UserService $userService*/
    private $userService;

    /** @var AuthorizationCheckerInterface $authorizationChecker*/
    private $authorizationChecker;

    private $singleTypeIndex;
    private $byId;

    public function __construct(Registry $doctrine, Session $session, UserService $userService, AuthorizationCheckerInterface $authorizationChecker, $singleTypeIndex)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->userService = $userService;
        $this->authorizationChecker = $authorizationChecker;
        $this->singleTypeIndex = $singleTypeIndex;
        $this->environments = false;
        $this->byId = false;
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


//         return '{
//            "index" : {
//               "max_result_window" : 50000,
//               "analysis" : {
//                  "analyzer" : {
//                     "for_all_field" : {
//                        "char_filter" : [
//                           "html_strip"
//                        ],
//                        "tokenizer" : "standard"
//                     }
//                  }
//               }
//            }
//         }';
    }

    public function getEnvironmentsStats()
    {
        /**@var EnvironmentRepository $repo*/
        $repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment');
        $out = $repo->getEnvironmentsStats();

        foreach ($out as &$item) {
            $item['deleted'] = $repo->getDeletedRevisionsPerEnvironment($item['environment']);
        }

        return $out;
    }

    private function loadEnvironment()
    {
        if ($this->environments === false) {
            $environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findAll();
            $this->environments = [];
            $this->byId = [];
            /**@var \EMS\CoreBundle\Entity\Environment $environment */
            foreach ($environments as $environment) {
                $this->environments[$environment->getName()] = $environment;
                $this->byId[$environment->getId()] = $environment;
            }
        }
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
        $this->loadEnvironment();
        if (isset($this->environments[$name])) {
            return $this->environments[$name];
        }
        return false;
    }

    public function getById($id)
    {
        $this->loadEnvironment();
        if (isset($this->byId[$id])) {
            return $this->byId[$id];
        }
        return false;
    }

    public function getManagedEnvironement()
    {
        $this->loadEnvironment();
        $out = [];

        /**@var Environment $environment*/
        foreach ($this->environments as $index => $environment) {
            if ($environment->getManaged()) {
                $out[$index] = $environment;
            }
        }
        return $out;
    }

    /**
     *
     * @return boolean|array
     */
    public function getAll()
    {
        $this->loadEnvironment();
        return $this->environments;
    }

    public function getAllInMyCircle()
    {
        $this->loadEnvironment();
        $out = [];
        $user = $this->userService->getCurrentUser();
        $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
        /**@var \EMS\CoreBundle\Entity\Environment $environment*/
        foreach ($this->environments as $index => $environment) {
            if (empty($environment->getCircles()) || $isAdmin || !empty(array_intersect($user->getCircles(), $environment->getCircles()))) {
                $out[$index] = $environment;
            }
        }
        return $out;
    }
}
