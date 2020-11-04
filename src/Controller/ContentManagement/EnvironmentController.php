<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\RebuildIndex;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\CompareEnvironmentFormType;
use EMS\CoreBundle\Form\Form\EditEnvironmentType;
use EMS\CoreBundle\Form\Form\RebuildIndexType;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\JobService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class EnvironmentController extends AppController
{

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     *
     * @Route("/publisher/align", name="environment.align"))
     * @Security("has_role('ROLE_PUBLISHER')")
     */
    public function alignAction(Request $request)
    {
        
        $data = [];
        $env = [];
        $withEnvi = [];
                
        /**@var EnvironmentService $environmentService*/
        $environmentService = $this->getEnvironmentService();
        /** @var Client $client */
        $client = $this->getElasticsearch();
        
        $form = $this->createForm(CompareEnvironmentFormType::class, $data, [
        ]);

        $form->handleRequest($request);
        $paging_size = $this->getParameter('ems_core.paging_size');
         
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($data['environment'] == $data['withEnvironment']) {
                $form->addError(new FormError("Source and target environments must be different"));
            } else {
                if (array_key_exists('alignWith', $request->request->get('compare_environment_form'))) {
                    $alignTo = [];
                    $alignTo[$request->query->get('withEnvironment')] = $request->query->get('withEnvironment');
                    $alignTo[$request->query->get('environment')] = $request->query->get('environment');
                    $revid = $request->request->get('compare_environment_form')['alignWith'];

                    /** @var EntityManager $em */
                    $em = $this->getDoctrine()->getManager();
                    
                    $repository = $em->getRepository('EMSCoreBundle:Revision');
                    
                    /**@var Revision $revision */
                    $revision = $repository->findOneBy([
                            'id' => $revid
                    ]);
                    
                    foreach ($revision->getEnvironments() as $item) {
                        if (array_key_exists($item->getName(), $alignTo)) {
                            unset($alignTo[$item->getName()]);
                        }
                    }

                    $continue = true;
                    foreach ($alignTo as $env) {
                        if ($revision->getContentType()->getEnvironment()->getName() == $env) {
                            $this->getLogger()->warning('log.environment.cant_align_default_environment', [
                                EmsFields::LOG_ENVIRONMENT_FIELD => $env,
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            ]);
                            $continue = false;
                            break;
                        }
                        
                        
                        if (! $this->getAuthorizationChecker()->isGranted($revision->getContentType()->getPublishRole())) {
                            $this->getLogger()->warning('log.environment.dont_have_publish_role', [
                                EmsFields::LOG_ENVIRONMENT_FIELD => $env,
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            ]);
                            $continue = false;
                            break;
                        }
                    }
                    
                    if ($continue) {
                        foreach ($alignTo as $env) {
                            $this->getPublishService()->alignRevision($revision->getContentType()->getName(), $revision->getOuuid(), $revision->getEnvironments()->first()->getName(), $env);
                        }
                    }
                } else if (array_key_exists('alignLeft', $request->request->get('compare_environment_form'))) {
                    foreach ($request->request->get('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = explode(':', $item);
                        if (count($exploded) == 2) {
                            $this->getPublishService()->alignRevision($exploded[0], $exploded[1], $request->query->get('withEnvironment'), $request->query->get('environment'));
                        } else {
                            $this->getLogger()->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } else if (array_key_exists('alignRight', $request->request->get('compare_environment_form'))) {
                    foreach ($request->request->get('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = explode(':', $item);
                        if (count($exploded) == 2) {
                            $this->getPublishService()->alignRevision($exploded[0], $exploded[1], $request->query->get('environment'), $request->query->get('withEnvironment'));
                        } else {
                            $this->getLogger()->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } else if (array_key_exists('compare', $request->request->get('compare_environment_form'))) {
                    $request->query->set('environment', $data['environment']);
                    $request->query->set('withEnvironment', $data['withEnvironment']);
                    $request->query->set('contentTypes', $data['contentTypes']);
                    $request->query->set('page', 1);
                }
                
                return $this->redirectToRoute('environment.align', $request->query->all());
            }
        }

        if (null != $request->query->get('page')) {
            $page = $request->query->get('page');
        } else {
            $page = 1;
        }
        
        
        
        $contentTypes = $request->query->get('contentTypes', []);
        if (!$form->isSubmitted()) {
            $form->get('contentTypes')->setData($contentTypes);
        }
        if (empty($contentTypes)) {
            $contentTypes = $form->get('contentTypes')->getConfig()->getOption('choices', []);
        }
        
        $orderField = $request->query->get('orderField', "contenttype");
        $orderDirection = $request->query->get('orderDirection', 'asc');
        
        if (null != $request->query->get('environment')) {
            $environment = $request->query->get('environment');
            if (!$form->isSubmitted()) {
                $form->get('environment')->setData($environment);
            }
        } else {
            $environment = false;
        }
        
        if (null != $request->query->get('withEnvironment')) {
            $withEnvironment = $request->query->get('withEnvironment');

            if (!$form->isSubmitted()) {
                $form->get('withEnvironment')->setData($withEnvironment);
            }
        } else {
            $withEnvironment = false;
        }
        
        if ($environment && $withEnvironment) {
            
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            /** @var RevisionRepository $repository*/
            $repository = $em->getRepository('EMSCoreBundle:Revision');
            

            $env = $environmentService->getAliasByName($environment);
            $withEnvi = $environmentService->getAliasByName($withEnvironment);

            $total = $repository->countDifferencesBetweenEnvironment($env->getId(), $withEnvi->getId(), $contentTypes);
            if ($total) {
                $contentTypeService = $this->getContentTypeService();
                $lastPage = ceil($total / $paging_size);
                if ($page > $lastPage) {
                    $page = $lastPage;
                }
                $results = $repository->compareEnvironment(
                    $env->getId(),
                    $withEnvi->getId(),
                    $contentTypes,
                    ($page - 1) * $paging_size,
                    $paging_size,
                    $orderField,
                    $orderDirection
                );
                for ($index = 0; $index < count($results); $index++) {
                    $results[$index]['contentType'] = $contentTypeService->getByName($results[$index]['content_type_name']);
//                     $results[$index]['revisionEnvironment'] = $repository->findOneById($results[$index]['rId']);
//TODO: is it the better options? to concatenate and split things?
                    $minrevid = explode("/", $results[$index]['minrevid']);//1/81522/2017-03-08 14:32:52 => e.id/r.id/r.created
                    $maxrevid = explode("/", $results[$index]['maxrevid']);
                    if ($minrevid[0] == $env->getId()) {
                        $results[$index]['revisionEnvironment'] = $repository->findOneById($minrevid[1]);
                        $results[$index]['revisionWithEnvironment'] = $repository->findOneById($maxrevid[1]);
                    } else {
                        $results[$index]['revisionEnvironment'] = $repository->findOneById($maxrevid[1]);
                        $results[$index]['revisionWithEnvironment'] = $repository->findOneById($minrevid[1]);
                    }
                    try {
                        $results[$index]['objectEnvironment'] = $client->get([
                                        'id' => $results[$index]['ouuid'],
                                        'index' => $this->getContentTypeService()->getIndex($results[$index]['contentType'], $env),
                                        'type' => $results[$index]['content_type_name'],
                                ]);
                    } catch (Missing404Exception $e) {
                        $results[$index]['objectEnvironment'] = null;//This revision doesn't exist in this environment, but it's ok.
                    }
                    try {
                        $results[$index]['objectWithEnvironment'] = $client->get([
                                'id' => $results[$index]['ouuid'],
                                'index' => $this->getContentTypeService()->getIndex($results[$index]['contentType'], $withEnvi),
                                'type' => $results[$index]['content_type_name'],
                        ]);
                    } catch (Missing404Exception $e) {
                        $results[$index]['objectWithEnvironment'] = null;//This revision doesn't exist in this environment, but it's ok.
                    }
                }
            } else {
                $page = $lastPage = 1;
                $this->getLogger()->notice('log.environment.aligned', [
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment,
                    'with_environment' => $withEnvironment,
                ]);
                $total = 0;
                $results = [];
            }
        } else {
            $environment = false;
            $withEnvironment = false;
            $results = false;
            $page = 0;
            $total = 0;
            $lastPage = 0;
        }
        
        return $this->render('@EMSCore/environment/align.html.twig', [
                'form' => $form->createView(),
                 'results' => $results,
                'lastPage' => $lastPage,
                'paginationPath' => 'environment.align',
                'page' => $page,
                'paging_size' => $paging_size,
                'total' => $total,
                'currentFilters' => $request->query,
                'fromEnv' => $env,
                'withEnv' => $withEnvi,
                'environment' => $environment,
                'withEnvironment' => $withEnvironment,
                'environments' => $environmentService->getAll(),
                 'orderField' => $orderField,
                 'orderDirection' => $orderDirection,
                'contentTypes' => $this->getContentTypeService()->getAll(),
         ]);
    }


    /**
     * Attach a external index as a new referenced environment
     *
     * @param string $name
     *            alias name
     *
     * @return RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/environment/attach/{name}", name="environment.attach"), methods={"POST"})
     */
    public function attachAction($name)
    {
        /** @var  Client $client */
        $client = $this->getElasticsearch();
        try {
            $indexes = $client->indices()->get([
                    'index' => $name
            ]);
                        
            if (strcmp($name, array_keys($indexes) [0]) != 0) {
                /** @var EntityManager $em */
                $em = $this->getDoctrine()->getManager();
                
                $environmentRepository = $em->getRepository('EMSCoreBundle:Environment');
                $anotherObject = $environmentRepository->findBy([
                        'name' => $name
                ]);
                
                if (count($anotherObject) == 0) {
                    $environment = new Environment();
                    $environment->setName($name);
                    $environment->setAlias($name);
                    //TODO: setCircles
                    $environment->setManaged(false);
                    
                    $em->persist($environment);
                    $em->flush();


                    $this->getLogger()->notice('log.environment.alias_attached', [
                        'alias' => $name,
                    ]);
                    
                    return $this->redirectToRoute('environment.edit', [
                            'id' => $environment->getId()
                    ]);
                }
            }
        } catch (Missing404Exception $e) {
            $this->getLogger()->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
        
        return $this->redirectToRoute('environment.index');
    }

    /**
     * Remove unreferenced alias
     *
     * @param string $name
     * @return RedirectResponse
     *
     * @Route("/environment/remove/alias/{name}", name="environment.remove.alias"), methods={"POST"})
     */
    public function removeAliasAction($name)
    {
        if ($this->getAliasService()->removeAlias($name)) {
            $this->getLogger()->notice('log.environment.alias_removed', [
                'alias' => $name,
            ]);
        }
            
        return $this->redirectToRoute('environment.index');
    }

    /**
     * Try to remove an evironment if it is empty form an eMS perspective.
     * If it's managed environment the Elasticsearch alias will be also removed.
     *
     * @param int $id
     * @return RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/environment/remove/{id}", name="environment.remove"), methods={"POST"})
     */
    public function removeAction(int $id)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var  EnvironmentRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Environment');
        /** @var  Environment $environment */
        $environment = $repository->find($id);
            
        /** @var  Client $client */
        $client = $this->getElasticsearch();
        if ($environment->getManaged()) {
            try {
                $indexes = $client->indices()->get([
                        'index' => $environment->getAlias()
                ]);
                $client->indices()->deleteAlias([
                        'name' => $environment->getAlias(),
                        'index' => array_keys($indexes) [0]
                ]);
            } catch (Missing404Exception $e) {
                $this->getLogger()->warning('log.environment.alias_not_found', [
                    'alias' => $environment->getAlias(),
                ]);
            }
        }
        if ($environment->getRevisions()->count() != 0) {
            $this->getLogger()->error('log.environment.not_empty', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
        } else {
            $linked = false;
            /**@var ContentType $contentType */
            foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType) {
                if (!$contentType->getDeleted()) {
                    $linked = true;
                    break;
                }
            }
            
            if ($linked) {
                $this->getLogger()->error('log.environment.is_default', [
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                ]);
            } else {
                /**@var ContentType $contentType */
                foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType) {
                    $contentType->getFieldType()->setContentType(null);
                    $em->persist($contentType->getFieldType());
                    $em->flush();
                    $em->remove($contentType);
                    $em->flush();
                }
                $em->remove($environment);
                $em->flush();
                $this->getLogger()->notice('log.environment.deleted', [
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                ]);
            }
        }
        
        return $this->redirectToRoute('environment.index');
    }
    
    
    public static function isValidName($name)
    {
        return preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && strlen($name) <= 100;
    }

    /**
     * Add a new environement
     *
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/environment/add", name="environment.add"))
     */
    public function addAction(Request $request)
    {
        $environment = new Environment();
        
        $form = $this->createFormBuilder($environment)->add('name', IconTextType::class, [
                'icon' => 'fa fa-database',
                'required' => false
        ])->add('color', ColorPickerType::class, [
                'required' => false
        ])->add('save', SubmitEmsType::class, [
                'label' => 'Create',
                'icon' => 'fa fa-plus',
                'attr' => [
                        'class' => 'btn btn-primary pull-right'
                ]
        ])->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            
            
            /** @var Environment $environment */
            $environment = $form->getData();
            if (!$this->isValidName($environment->getName())) {
                $form->get('name')->addError(new FormError('Must respects the following regex /^[a-z][a-z0-9\-_]*$/'));
            }
            
            if ($form->isValid()) {
                
                
                /** @var EntityManager $em */
                $em = $this->getDoctrine()->getManager();
                
                $environmetRepository = $em->getRepository('EMSCoreBundle:Environment');
                $anotherObject = $environmetRepository->findBy([
                        'name' => $environment->getName()
                ]);
                
                if (count($anotherObject) != 0) {
                    //TODO: test name format
                    $form->get('name')->addError(new FormError('Another environment named ' . $environment->getName() . ' already exists'));
                } else {
                    $environment->setAlias($this->getParameter('ems_core.instance_id') . $environment->getName());
                    $environment->setManaged(true);
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($environment);
                    $em->flush();
    
                    $indexName = $environment->getAlias() . AppController::getFormatedTimestamp();
                    $this->getElasticsearch()->indices()->create([
                            'index' => $indexName,
                            'body' => $this->getEnvironmentService()->getIndexAnalysisConfiguration(),
                    ]);
                    
                    foreach ($this->getContentTypeService()->getAll() as $contentType) {
                        $this->getContentTypeService()->updateMapping($contentType, $indexName);
                    }
                    
                    $this->getElasticsearch()->indices()->putAlias([
                        'index' => $indexName,
                        'name' => $environment->getAlias()
                    ]);

                    $this->getLogger()->notice('log.environment.created', [
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    ]);
                    return $this->redirectToRoute('environment.index');
                }
            }
        }
        
        return $this->render('@EMSCore/environment/add.html.twig', [
                'form' => $form->createView()
        ]);
    }


    /**
     * Edit environement (name and color). It's not allowed to update the elasticsearch alias.
     *
     * @param int $id
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/environment/edit/{id}", name="environment.edit"))
     */
    public function editAction(int $id, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
    
        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Environment');
    
        /** @var Environment|null $environment */
        $environment = $repository->find($id);
    
        if ($environment === null) {
            throw new NotFoundHttpException('Unknow environment');
        }
    
        $options = [];
        if ($this->getParameter("ems_core.circles_object")) {
            $options['type'] = $this->getParameter("ems_core.circles_object");
        }
        
        $form = $this->createForm(EditEnvironmentType::class, $environment, $options);
    
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($environment);
            $em->flush();
            $this->getLogger()->notice('log.environment.updated', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
            return $this->redirectToRoute('environment.index');
        }
    
        return $this->render('@EMSCore/environment/edit.html.twig', [
                'environment' => $environment,
                'form' => $form->createView(),
        ]);
    }


    /**
     * @param int $id
     * @return Response
     * View environement details (especially the mapping information).
     *
     * @Route("/environment/{id}", name="environment.view"))
     */
    public function viewAction(int $id)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        
        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Environment');
        
        /** @var Environment|null $environment */
        $environment = $repository->find($id);
    
        if ($environment === null) {
            throw new NotFoundHttpException('Unknow environment');
        }

        /** @var  Client $client */
        $client = $this->getElasticsearch();
        
        try {
            $info = $client->indices()->getMapping([
                    'index' => $environment->getAlias(),
            ]);
        } catch (Missing404Exception $e) {
            $this->getLogger()->error('log.environment.alias_missing', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                'alias' => $environment->getAlias(),
            ]);
            $info = false;
        }
    
        return $this->render('@EMSCore/environment/view.html.twig', [
                'environment' => $environment,
                'info' => $info,
        ]);
    }

    /**
     * Rebuils a environement in elasticsearch in a new index or not (depending the rebuild option)
     *
     * @param int $id
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/environment/rebuild/{id}", name="environment.rebuild"))
     */
    public function rebuild($id, Request $request, JobService $jobService)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Environment');
    
        /** @var Environment|null $environment */
        $environment = $repository->find($id);
    
        if ($environment === null) {
            throw new NotFoundHttpException('Unknow environment');
        }
    
        $rebuildIndex = new RebuildIndex();
    
        $form = $this->createForm(RebuildIndexType::class, $rebuildIndex);
    
        $form->handleRequest($request);

        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user object');
        }
    
        if ($form->isSubmitted() && $form->isValid()) {
            $option = $rebuildIndex->getOption();

            switch ($option) {
                case "newIndex":
                    $job = $jobService->createCommand($user, sprintf('ems:environment:rebuild %s', $environment->getName()));
                    return $this->redirectToRoute('job.status', [
                        'job' => $job->getId(),
                    ]);
                case "sameIndex":
                    $job = $jobService->createCommand($user, sprintf('ems:environment:reindex %s', $environment->getName()));
                    return $this->redirectToRoute('job.status', [
                        'job' => $job->getId(),
                    ]);
                default:
                    $this->getLogger()->warning('log.environment.rebuild_unknown_option', [
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                        'option' => $option,
                    ]);
            }
        }
    
        return $this->render('@EMSCore/environment/rebuild.html.twig', [
                'environment' => $environment,
                'form' => $form->createView(),
        ]);
    }

    /**
     * List all environments, orphean indexes, unmanaged aliases and referenced environments
     *
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException

     * @Route("/environment", name="environment.index")
     * @Route("/environment", name="ems_environment_index")
     */
    public function indexAction(Request $request)
    {
        try {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            $logger = $this->getLogger();
            $logger->debug('For each environments: start');
            
            $builder = $this->createFormBuilder([])
            ->add('reorder', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary '
                ],
                'label' => 'controller.environment.index.reorder_submit_button',
                'icon' => 'fa fa-reorder',
                'translation_domain' => CoreBundle\EMSCoreBundle::TRANS_DOMAIN,
            ]);
            
            $names = [];
                        $aliasService = $this->getAliasService();
                        
            $environments = [];//$repository->findAll();
            $stats = $this->getEnvironmentService()->getEnvironmentsStats();
//            $statsDeleted = $this->getEnvironmentService()->getDe EnvironmentsStats();
            /** @var  Environment $environment */
            foreach ($stats as $stat) {
                $environment = $stat['environment'];
                $environment->setCounter($stat['counter']);
                $environment->setDeletedRevision($stat['deleted']);
                if ($aliasService->hasAlias($environment->getAlias())) {
                    $alias = $aliasService->getAlias($environment->getAlias());

                    $environment->setIndexes($alias['indexes']);
                    $environment->setTotal($alias['total']);
                }
                $environments[] = $environment;
                $names[] = $environment->getName();
            }
            $logger->debug('For each environments: done');
            
            $builder->add('environmentNames', CollectionType::class, array(
                    // each entry in the array will be an "email" field
                    'entry_type'   => HiddenType::class,
                    // these options are passed to each "email" type
                    'entry_options'  => array(
                    ),
                    'data' => $names
            ));
            
            $form = $builder->getForm();
            
            
            
            if ($request->isMethod('POST')) {
                $form = $request->get('form');
                if (isset($form['environmentNames']) && is_array($form['environmentNames'])) {
                    $counter = 0;
                    foreach ($form['environmentNames'] as $name) {
                        $contentType = $this->getEnvironmentService()->getByName($name);
                        if ($contentType) {
                            $contentType->setOrderKey($counter);
                            $em->persist($contentType);
                        }
                        ++$counter;
                    }
                    
                    $em->flush();
                    $this->getLogger()->notice('log.environment.reordered', [
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                    ]);
                }
                
                return $this->redirectToRoute('ems_environment_index');
            }
        
            return $this->render('@EMSCore/environment/index.html.twig', [
                    'environments' => $environments,
                    'orphanIndexes' => $aliasService->getOrphanIndexes(),
                    'unreferencedAliases' => $aliasService->getUnreferencedAliases(),
                                        'managedAliases' => $aliasService->getManagedAliases(),
                    'form' => $form->createView(),
            ]);
        } catch (NoNodesAvailableException $e) {
            return $this->redirectToRoute('elasticsearch.status');
        }
    }
}
