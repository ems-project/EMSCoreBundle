<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\CompareEnvironmentFormType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EnvironmentController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SearchService $searchService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly PublishService $publishService,
        private readonly RevisionRepository $revisionRepository,
        private readonly int $pagingSize,
        private readonly string $templateNamespace,
    ) {
    }

    public function align(Request $request): Response
    {
        if (!$this->isGranted('ROLE_PUBLISHER')) {
            throw new AccessDeniedHttpException();
        }
        $data = [];
        $env = [];
        $withEnvi = [];

        $form = $this->createForm(CompareEnvironmentFormType::class, $data, [
        ]);

        $form->handleRequest($request);
        $paging_size = $this->pagingSize;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($data['environment'] == $data['withEnvironment']) {
                $form->addError(new FormError('Source and target environments must be different'));
            } else {
                if (\array_key_exists('alignWith', $request->request->all('compare_environment_form'))) {
                    $alignTo = [];
                    $alignTo[Type::string($request->query->get('withEnvironment'))] = Type::string($request->query->get('withEnvironment'));
                    $alignTo[Type::string($request->query->get('environment'))] = Type::string($request->query->get('environment'));
                    $revid = $request->request->all('compare_environment_form')['alignWith'];
                    /** @var Revision $revision */
                    $revision = $this->revisionRepository->findOneBy([
                        'id' => $revid,
                    ]);

                    foreach ($revision->getEnvironments() as $item) {
                        if (\array_key_exists($item->getName(), $alignTo)) {
                            unset($alignTo[$item->getName()]);
                        }
                    }

                    $continue = true;
                    foreach ($alignTo as $env) {
                        if ($revision->giveContentType()->giveEnvironment()->getName() == $env) {
                            $this->logger->warning('log.environment.cant_align_default_environment', [
                                EmsFields::LOG_ENVIRONMENT_FIELD => $env,
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            ]);
                            $continue = false;
                            break;
                        }

                        if (!$this->authorizationChecker->isGranted($revision->giveContentType()->role(ContentTypeRoles::PUBLISH))) {
                            $this->logger->warning('log.environment.dont_have_publish_role', [
                                EmsFields::LOG_ENVIRONMENT_FIELD => $env,
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            ]);
                            $continue = false;
                            break;
                        }
                    }

                    if ($continue) {
                        foreach ($alignTo as $env) {
                            $firstEnvironment = $revision->getEnvironments()->first();
                            if (false !== $firstEnvironment) {
                                $this->publishService->alignRevision($revision->giveContentType()->getName(), $revision->giveOuuid(), $firstEnvironment->getName(), $env);
                            }
                        }
                    }
                } elseif (\array_key_exists('alignLeft', $request->request->all('compare_environment_form'))) {
                    foreach ($request->request->all('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = \explode(':', (string) $item);
                        if (2 == \count($exploded)) {
                            $this->publishService->alignRevision($exploded[0], $exploded[1], Type::string($request->query->get('withEnvironment')), Type::string($request->query->get('environment')));
                        } else {
                            $this->logger->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } elseif (\array_key_exists('alignRight', $request->request->all('compare_environment_form'))) {
                    foreach ($request->request->all('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = \explode(':', (string) $item);
                        if (2 == \count($exploded)) {
                            $this->publishService->alignRevision($exploded[0], $exploded[1], Type::string($request->query->get('environment')), Type::string($request->query->get('withEnvironment')));
                        } else {
                            $this->logger->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } elseif (\array_key_exists('compare', $request->request->all('compare_environment_form'))) {
                    $request->query->set('environment', $data['environment']);
                    $request->query->set('withEnvironment', $data['withEnvironment']);
                    $request->query->set('contentTypes', $data['contentTypes']);
                    $request->query->set('page', 1);
                }

                return $this->redirectToRoute('environment.align', $request->query->all());
            }
        }

        $page = $request->query->getInt('page', 1);

        $contentTypes = $request->query->all('contentTypes');
        if (!$form->isSubmitted()) {
            $form->get('contentTypes')->setData($contentTypes);
        }
        if (empty($contentTypes)) {
            $contentTypes = $form->get('contentTypes')->getConfig()->getOption('choices', []);
        }

        $orderField = $request->query->get('orderField', 'contenttype');
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
            $env = $this->environmentService->giveByName($environment);
            $withEnvi = $this->environmentService->giveByName($withEnvironment);

            $total = $this->revisionRepository->countDifferencesBetweenEnvironment($env->getId(), $withEnvi->getId(), $contentTypes);
            if ($total) {
                $lastPage = \ceil($total / $paging_size);
                if ($page > $lastPage) {
                    $page = $lastPage;
                }
                $results = $this->revisionRepository->compareEnvironment(
                    $env->getId(),
                    $withEnvi->getId(),
                    $contentTypes,
                    (int) (($page - 1) * $paging_size),
                    $paging_size,
                    $orderField,
                    $orderDirection
                );
                for ($index = 0; $index < \count($results); ++$index) {
                    $results[$index]['contentType'] = $this->contentTypeService->getByName($results[$index]['content_type_name']);
                    //                     $results[$index]['revisionEnvironment'] = $repository->findOneById($results[$index]['rId']);
                    // TODO: is it the better options? to concatenate and split things?
                    $minrevid = \explode('/', (string) $results[$index]['minrevid']); // 1/81522/2017-03-08 14:32:52 => e.id/r.id/r.created
                    $maxrevid = \explode('/', (string) $results[$index]['maxrevid']);

                    $results[$index]['revisionEnvironment'] = $this->revisionRepository->findOneById((int) $minrevid[1]);
                    $results[$index]['revisionWithEnvironment'] = $this->revisionRepository->findOneById((int) $maxrevid[1]);

                    $contentType = $results[$index]['contentType'];
                    if (false === $contentType) {
                        throw new \RuntimeException(\sprintf('Content type %s not found', $results[$index]['contentType']));
                    }
                    try {
                        $document = $this->searchService->getDocument($contentType, $results[$index]['ouuid'], $env);
                        $results[$index]['objectEnvironment'] = $document->getRaw();
                    } catch (NotFoundException) {
                        $results[$index]['objectEnvironment'] = null; // This revision doesn't exist in this environment, but it's ok.
                    }
                    try {
                        $document = $this->searchService->getDocument($contentType, $results[$index]['ouuid'], $withEnvi);
                        $results[$index]['objectWithEnvironment'] = $document->getRaw();
                    } catch (NotFoundException) {
                        $results[$index]['objectWithEnvironment'] = null; // This revision doesn't exist in this environment, but it's ok.
                    }
                }
            } else {
                $page = $lastPage = 1;
                $this->logger->notice('log.environment.aligned', [
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

        return $this->render("@$this->templateNamespace/environment/align.html.twig", [
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
            'environments' => $this->environmentService->getEnvironments(),
            'orderField' => $orderField,
            'orderDirection' => $orderDirection,
            'contentTypes' => $this->contentTypeService->getAll(),
        ]);
    }
}
