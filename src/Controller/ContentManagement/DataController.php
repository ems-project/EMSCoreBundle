<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\NoResultException;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\MimeTypeHelper;
use EMS\CoreBundle\Controller\ElasticsearchController;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\ContentType\ViewTypes;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Core\Revision\EventType;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\SearchRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ActionService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Twig\CoreExtension;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class DataController extends AbstractController
{
    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly DataService $dataService,
        private readonly SearchService $searchService,
        private readonly ContentTypeService $contentTypeService,
        private readonly EnvironmentService $environmentService,
        private readonly IndexService $indexService,
        private readonly TranslatorInterface $translator,
        private readonly ViewTypes $viewTypes,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly SearchRepository $searchRepository,
        private readonly RevisionRepository $revisionRepository,
        private readonly ActionService $actionService,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly PublishService $publishService,
        private readonly ContentTypeService $ctService,
        private readonly string $templateNamespace,
    ) {
    }

    public function root(string $name): Response
    {
        $contentType = $this->contentTypeRepository->findOneBy([
            'name' => $name,
            'deleted' => false,
        ]);

        if (!$contentType instanceof ContentType) {
            throw new NotFoundHttpException('Content type '.$name.' not found');
        }

        $searches = $this->searchRepository->findBy([
            'contentType' => $contentType->getId(),
        ]);
        foreach ($searches as $search) {
            return $this->forward(ElasticsearchController::class.'::search', [
                'query' => null,
            ], [
                'search_form' => $search->jsonSerialize(),
            ]);
        }

        $searchForm = new Search();
        $searchForm->setContentTypes([$contentType->getName()]);
        $searchForm->setEnvironments([$contentType->giveEnvironment()->getName()]);
        $searchForm->setSortBy('_finalization_datetime');
        if ($contentType->getSortBy()) {
            $searchForm->setSortBy($contentType->getSortBy());
        }
        $searchForm->setSortOrder('desc');
        if ($contentType->getSortOrder()) {
            $searchForm->setSortOrder($contentType->getSortOrder());
        }

        return $this->forward(ElasticsearchController::class.'::search', [
            'query' => null,
        ], [
            'search_form' => $searchForm->jsonSerialize(),
        ]);
    }

    public function inMyCircles(string $name): Response
    {
        $contentType = $this->contentTypeRepository->findOneBy([
            'name' => $name,
            'deleted' => false,
        ]);

        if (!$contentType instanceof ContentType) {
            throw new NotFoundHttpException('Content type '.$name.' not found');
        }

        $searchForm = new Search();
        $searchForm->setContentTypes([$contentType->getName()]);
        $searchForm->setEnvironments([$contentType->giveEnvironment()->getName()]);
        $searchForm->setSortBy('_finalization_datetime');
        if ($contentType->getSortBy()) {
            $searchForm->setSortBy($contentType->getSortBy());
        }
        $searchForm->setSortOrder('desc');
        if ($contentType->getSortOrder()) {
            $searchForm->setSortOrder($contentType->getSortOrder());
        }

        $circleField = $contentType->getCirclesField();
        if (null === $circleField || '' === $circleField) {
            throw new \RuntimeException('Unexpected empty circle field');
        }

        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user object');
        }
        foreach ($user->getCircles() as $circle) {
            $filter = new SearchFilter();
            $filter->setBooleanClause('should')
                ->setField($circleField)
                ->setOperator('term')
                ->setPattern($circle);
            $searchForm->addFilter($filter);
        }

        $formEncoded = Json::encode($searchForm);

        return $this->forward(ElasticsearchController::class.'::search', [
            'query' => null,
        ], [
            'search_form' => Json::decode($formEncoded),
        ]);
    }

    public function viewData(string $environmentName, string $type, string $ouuid): Response
    {
        $environment = $this->environmentService->getByName($environmentName);
        if (false === $environment) {
            throw new NotFoundHttpException(\sprintf('Environment %s not found', $environmentName));
        }

        $contentType = $this->contentTypeService->getByName($type);
        if (false === $contentType) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $type));
        }

        try {
            $document = $this->searchService->getDocument($contentType, $ouuid, $environment);
        } catch (\Throwable) {
            throw new NotFoundHttpException(\sprintf('Document %s with identifier %s not found in environment %s', $contentType->getSingularName(), $ouuid, $environmentName));
        }

        return $this->render(\sprintf('@%s/data/view-data.html.twig', $this->templateNamespace), [
            'document' => $document,
            'object' => $document->getRaw(),
            'environment' => $environment,
            'contentType' => $contentType,
        ]);
    }

    public function revisionInEnvironmentData(string $type, string $ouuid, string $environment): RedirectResponse
    {
        $contentType = $this->contentTypeService->getByName($type);
        if (!$contentType instanceof ContentType || $contentType->getDeleted()) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $type));
        }
        $environment = $this->environmentService->getByName($environment);
        if (!$environment instanceof Environment) {
            throw new NotFoundHttpException('Environment not found');
        }

        try {
            $revision = $this->dataService->getRevisionByEnvironment($ouuid, $contentType, $environment);

            return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                'type' => $contentType->getName(),
                'ouuid' => $ouuid,
                'revisionId' => $revision->getId(),
            ]);
        } catch (NoResultException) {
            $this->logger->messageWarning(t('message.revision_not_found_in_environment', [
                'environment' => $environment->getLabel(),
                'ouuid' => $ouuid,
            ], 'emsco-core'));

            return $this->redirectToRoute('emsco_draft_in_progress', ['contentTypeId' => $contentType->getId()]);
        }
    }

    public function publicKey(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', MimeTypeHelper::TEXT_PLAIN);
        $response->setContent($this->dataService->getPublicKey());

        return $response;
    }

    public function duplicate(string $environment, string $type, string $ouuid): RedirectResponse
    {
        $contentType = $this->contentTypeService->getByName($type);
        if (false === $contentType) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $type));
        }
        $environmentObject = $this->environmentService->getByName($environment);
        if (false === $environmentObject) {
            throw new NotFoundHttpException(\sprintf('Environment %s not found', $environment));
        }

        try {
            $revision = $this->dataService->getRevisionByEnvironment($ouuid, $contentType, $environmentObject);
            $dataRaw = $revision->getCopyRawData();
        } catch (NoResultException) {
            throw new NotFoundHttpException(\sprintf('Revision %s not found', $ouuid));
        }

        if ($contentType->getAskForOuuid()) {
            $this->logger->messageWarning(t('message.document_cant_duplicate_ouuid_required', [
                'content_type' => $type,
                'label' => $revision->getLabel(),
            ], 'emsco-core'));

            return $this->redirectToRoute('emsco_data_view', [
                'environmentName' => $environment,
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        $revision = $this->dataService->newDocument($contentType, null, $dataRaw);

        $this->logger->messageNotice(t('message.document_duplicated', [
            'label' => $revision->getLabel(),
        ], 'emsco-core'));

        return $this->redirectToRoute(Routes::EDIT_REVISION, [
            'revisionId' => $revision->getId(),
        ]);
    }

    public function copy(string $environment, string $type, string $ouuid, Request $request): RedirectResponse
    {
        $contentType = $this->contentTypeService->getByName($type);
        if (!$contentType) {
            throw new NotFoundHttpException('Content type '.$type.' not found');
        }
        $environmentObject = $this->environmentService->getByName($environment);
        if (false === $environmentObject) {
            throw new NotFoundHttpException(\sprintf('Environment %s not found', $environment));
        }

        try {
            $revision = $this->dataService->getRevisionByEnvironment($ouuid, $contentType, $environmentObject);
            $dataRaw = $revision->getCopyRawData();
        } catch (NoResultException) {
            throw new NotFoundHttpException(\sprintf('Revision %s not found', $ouuid));
        }

        $request->getSession()->set('ems_clipboard', $dataRaw);

        $this->logger->messageNotice(t('message.document_copied', [
            'label' => $revision->getLabel(),
        ], 'emsco-core'));

        return $this->redirectToRoute('emsco_data_view', [
            'environmentName' => $environment,
            'type' => $type,
            'ouuid' => $ouuid,
        ]);
    }

    public function newDraft(Request $request, string $type, string $ouuid): RedirectResponse
    {
        $contentType = $this->contentTypeService->giveByName($type);
        if (!$this->isGranted($contentType->role(ContentTypeRoles::EDIT))) {
            throw $this->createAccessDeniedException('Edit role not granted!');
        }

        return $this->redirectToRoute(Routes::EDIT_REVISION, [
            'revisionId' => $this->dataService->initNewDraft($type, $ouuid)->getId(),
            'item' => $request->query->get('item'),
        ]);
    }

    public function delete(string $type, string $ouuid): RedirectResponse
    {
        $revision = $this->dataService->getNewestRevision($type, $ouuid);
        $contentType = $revision->giveContentType();

        if (!$this->isGranted($contentType->role(ContentTypeRoles::DELETE))) {
            throw $this->createAccessDeniedException('Delete not granted!');
        }

        $found = false;
        foreach ($this->environmentService->getEnvironments() as $environment) {
            if ($environment !== $revision->giveContentType()->giveEnvironment()) {
                try {
                    $sibling = $this->dataService->getRevisionByEnvironment($ouuid, $revision->giveContentType(), $environment);

                    $this->logger->messageWarning(t('message.revision_cannot_delete_published', [
                        'label' => $revision->getLabel(),
                        'environment' => $environment->getLabel(),
                    ], 'emsco-core'), LogRevisionContext::publish($sibling, $environment));

                    $found = true;
                } catch (NoResultException) {
                }
            }
        }

        if ($found) {
            return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        $this->dataService->delete($type, $ouuid);

        return $this->contentTypeService->redirectOverview($contentType);
    }

    public function discardDraft(Revision $revision): ?int
    {
        return $this->dataService->discardDraft($revision);
    }

    public function discardRevision(int $revisionId): RedirectResponse
    {
        /** @var Revision|null $revision */
        $revision = $this->revisionRepository->find($revisionId);

        if (null === $revision) {
            throw $this->createNotFoundException('Revision not found');
        }
        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }

        $contentTypeId = $revision->giveContentType()->getId();
        $type = $revision->giveContentType()->getName();
        $autoPublish = $revision->giveContentType()->isAutoPublish();
        $ouuid = $revision->getOuuid();

        $previousRevisionId = $this->discardDraft($revision);

        if (null != $ouuid && null !== $previousRevisionId && $previousRevisionId > 0) {
            if ($autoPublish) {
                return $this->reindexRevision($previousRevisionId, true);
            }

            return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        return $this->redirectToRoute('emsco_draft_in_progress', [
            'contentTypeId' => $contentTypeId,
        ]);
    }

    public function cancelModifications(Revision $revision): RedirectResponse
    {
        $contentTypeId = $revision->giveContentType()->getId();
        $type = $revision->giveContentType()->getName();
        $ouuid = $revision->getOuuid();

        $this->dataService->lockRevision($revision);
        $revision->autoSaveClear();
        $this->revisionRepository->save($revision);

        if (null != $ouuid) {
            if ($revision->giveContentType()->isAutoPublish()) {
                $this->publishService->silentPublish($revision);

                $this->logger->messageWarning(t('message.revision_auto_publish_rollback', [
                    'label' => $revision->getLabel(),
                    'environment' => $revision->giveContentType()->giveEnvironment()->getLabel(),
                ], 'emsco-core'));
            }

            return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        return $this->redirectToRoute(Routes::DRAFT_IN_PROGRESS, [
            'contentTypeId' => $contentTypeId,
        ]);
    }

    public function reindexRevision(int $revisionId, bool $defaultOnly = false): RedirectResponse
    {
        /** @var Revision|null $revision */
        $revision = $this->revisionRepository->find($revisionId);

        if (null === $revision) {
            throw $this->createNotFoundException('Revision not found');
        }

        try {
            $this->dataService->reloadData($revision);

            /** @var Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                if (!$defaultOnly || $environment === $revision->giveContentType()->getEnvironment()) {
                    if ($this->indexService->indexRevision($revision, $environment)) {
                        $this->logger->messageNotice(t('message.revision_reindexed', [
                            'label' => $revision->getLabel(),
                            'environment' => $environment->getLabel(),
                        ], 'emsco-core'), LogRevisionContext::update($revision));
                    } else {
                        $this->logger->messageWarning(t('message.revision_reindexed_failed_in', [
                            'label' => $revision->getLabel(),
                            'environment' => $environment->getLabel(),
                        ], 'emsco-core'), LogRevisionContext::update($revision));
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger->messageWarning(t('message.revision_reindexed_failed', [
                'label' => $revision->getLabel(),
                'error_message' => $throwable->getMessage(),
            ], 'emsco-core'), LogRevisionContext::update($revision));
        }

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'ouuid' => $revision->getOuuid(),
            'type' => $revision->giveContentType()->getName(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function customIndexView(View $viewId, bool $public, Request $request): Response
    {
        $view = $viewId;
        if ($public && !$view->isPublic()) {
            throw $this->createNotFoundException(t('message.view_not_found', ['id' => $viewId], 'emsco-core')->trans($this->translator));
        }
        $viewType = $this->viewTypes->get($view->getType());

        return $viewType->generateResponse($view, $request);
    }

    public function customViewJob(Request $request, SymfonyUserInterface $user, string $environmentName, int $templateId, string $ouuid): Response
    {
        try {
            $action = $this->actionService->giveById($templateId);
            $environment = $this->environmentService->giveByName($environmentName);
            $job = $this->actionService->executeJobAction($user, $action, $ouuid, $environment);

            return EmsCoreResponse::createJsonResponse($request, true, [
                'jobId' => $job->getId(),
                'jobUrl' => $this->generateUrl(Routes::JOB_START, ['job' => $job->getId()]),
                'url' => $this->generateUrl(Routes::JOB_STATUS, ['job' => $job->getId()]),
            ]);
        } catch (\Throwable) {
            return EmsCoreResponse::createJsonResponse($request, false);
        }
    }

    public function ajaxUpdate(int $revisionId, Request $request): Response
    {
        $formErrors = [];

        /** @var Revision|null $revision */
        $revision = $this->revisionRepository->find($revisionId);

        if (null === $revision) {
            throw new NotFoundHttpException('Revision not found');
        }

        if (!$revision->getDraft() || null !== $revision->getEndTime()) {
            $this->logger->messageWarning(t('message.revision_ajax_update_on_finalized', [
                'label' => $revision->getLabel(),
            ], 'emsco-core'));

            $response = $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
            ]);
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        }

        $revisionInRequest = $request->request->all('revision');
        if (empty($revisionInRequest['allFieldsAreThere'])) {
            $this->logger->messageError(t('message.revision_incomplete_request', [
                'label' => $revision->getLabel(),
            ], 'emsco-core'));
        } else {
            $this->dataService->lockRevision($revision);
            $this->logger->debug('Revision locked');

            $backup = $revision->getRawData();
            $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);

            // If the bag is not empty the user already see its content when opening the edit page
            $request->getSession()->getBag('flashes')->clear();

            /**little trick to reorder collection*/
            $requestRevision = $request->request->all('revision');
            $this->reorderCollection($requestRevision);
            $request->request->set('revision', $requestRevision);
            /**end little trick to reorder collection*/

            $form->handleRequest($request);
            $revision->setAutoSave($revision->getRawData());
            $objectArray = $revision->getRawData();
            $revision->setRawData($backup);

            $now = new \DateTime();
            $revision->setAutoSaveAt($now);
            $revision->setDraftSaveDate($now);
            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new \RuntimeException('Unexpected user object');
            }
            $revision->setAutoSaveBy($user->getUsername());
            $this->revisionRepository->save($revision);

            $this->dataService->isValid($form, null, $objectArray);
            if (\is_array($objectArray)) {
                $this->dataService->propagateDataToComputedField($form->get('data'), $objectArray, $revision->giveContentType(), $revision->giveContentType()->getName(), $revision->getOuuid(), EventType::autoSaveEvent());
            }

            $session = $request->getSession();
            if ($session instanceof Session) {
                $session->getFlashBag()->set('warning', []);
            }

            $formErrors = $form->getErrors(true, true);

            if (0 === $formErrors->count() && $revision->giveContentType()->isAutoPublish()) {
                $this->publishService->silentPublish($revision);
            }
        }

        $serialisedFormErrors = [];
        foreach ($formErrors as $error) {
            $serialisedFormErrors[] = [
                'propertyPath' => CoreExtension::propertyPath($error),
                'message' => $error->getMessage(),
            ];
        }

        $response = $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'formErrors' => $serialisedFormErrors,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function finalizeDraft(Revision $revision): Response
    {
        $this->dataService->loadDataStructure($revision);
        try {
            $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            if (!empty($revision->getAutoSave())) {
                $this->logger->messageError(t('message.revision_cannot_finalize_pending_auto_save', [
                    'label' => $revision->getLabel(),
                ], 'emsco-core'));

                return $this->redirectToRoute(Routes::EDIT_REVISION, [
                    'revisionId' => $revision->getId(),
                ]);
            }

            $revision = $this->dataService->finalizeDraft($revision, $form);
            if (0 !== $form->getErrors()->count()) {
                $this->logger->messageError(t('message.revision_cannot_finalize_invalid', [
                    'label' => $revision->getLabel(),
                    'count' => $form->getErrors(true)->count(),
                ], 'emsco-core'), LogRevisionContext::read($revision));

                return $this->redirectToRoute(Routes::EDIT_REVISION, [
                    'revisionId' => $revision->getId(),
                ]);
            }
        } catch (\Throwable $throwable) {
            $this->logger->messageError(t('message.revision_finalize_error', [
                'label' => $revision->getLabel(),
                'content_type' => $revision->giveContentType()->getSingularName(),
                'error_message' => $throwable->getMessage(),
            ], 'emsco-core'), [
                ...LogRevisionContext::read($revision),
                EmsFields::LOG_EXCEPTION_FIELD => $throwable,
            ]);

            return $this->redirectToRoute(Routes::EDIT_REVISION, [
                'revisionId' => $revision->getId(),
            ]);
        }

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'ouuid' => $revision->getOuuid(),
            'type' => $revision->giveContentType()->getName(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function duplicateWithJsonContent(ContentType $contentType, string $ouuid, Request $request): RedirectResponse
    {
        $jsonContent = Json::decode($request->request->getString('JSON_BODY', '{}'));
        $jsonContent = \array_merge($this->dataService->getNewestRevision($contentType->getName(), $ouuid)->getRawData(), $jsonContent);

        return $this->intNewDocumentFromArray($contentType, $jsonContent);
    }

    public function addFromJsonContent(ContentType $contentType, Request $request): RedirectResponse
    {
        try {
            $jsonContent = Json::decode($request->request->getString('JSON_BODY', '{}'));
        } catch (\Throwable) {
            $this->logger->messageError(t('message.revision_add_from_json_error', [
                'content_type' => $contentType->getSingularName(),
            ], 'emsco-core'));

            return $this->contentTypeService->redirectOverview($contentType);
        }

        return $this->intNewDocumentFromArray($contentType, $jsonContent);
    }

    /**
     * @param mixed[] $rawData
     */
    private function intNewDocumentFromArray(ContentType $contentType, array $rawData): RedirectResponse
    {
        $this->dataService->hasCreateRights($contentType);

        try {
            $revision = $this->dataService->newDocument($contentType, null, $rawData);

            return $this->redirectToRoute(Routes::EDIT_REVISION, [
                'revisionId' => $revision->getId(),
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->messageError(t('message.revision_create_from_array_error', [
                'content_type' => $contentType->getSingularName(),
            ], 'emsco-core'), [
                EmsFields::LOG_EXCEPTION_FIELD => $throwable,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $throwable->getMessage(),
            ]);

            return $this->contentTypeService->redirectOverview($contentType);
        }
    }

    public function add(ContentType $contentType, Request $request): Response
    {
        if (!$this->isGranted($contentType->role(ContentTypeRoles::CREATE))) {
            throw $this->createAccessDeniedException('Create not granted');
        }

        $this->dataService->hasCreateRights($contentType);

        $revision = new Revision();
        $form = $this->createFormBuilder($revision)
            ->add('ouuid', IconTextType::class, [
                'constraints' => [
                    new Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                        if (null === $value || '' === $value || \preg_match('/^[A-Za-z0-9_.\-~]*$/', (string) $value)) {
                            return;
                        }

                        $context
                            ->buildViolation(t('form.data.add.ouuid.constraint_message', [], 'emsco-core')->getMessage())
                            ->setTranslationDomain('emsco-core')
                            ->addViolation();
                    }),
                ],
                'label' => t('form.data.add.ouuid.label', [], 'emsco-core'),
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => t('form.data.add.ouuid.placeholder', [], 'emsco-core'),
                ],
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => t('form.data.add.save', ['%content_type%' => $contentType->getSingularName()], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary pull-right',
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if (($form->isSubmitted() && $form->isValid()) || !$contentType->getAskForOuuid()) {
            /** @var Revision $revision */
            $revision = $form->getData();
            try {
                $revision = $this->dataService->newDocument($contentType, $revision->getOuuid());

                return $this->redirectToRoute(Routes::EDIT_REVISION, [
                    'revisionId' => $revision->getId(),
                ]);
            } catch (DuplicateOuuidException) {
                $form->get('ouuid')->addError(new FormError('Another '.$contentType->getName().' with this identifier already exists'));
            }
        }

        return $this->render(\sprintf('@%s/data/add.html.twig', $this->templateNamespace), [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    public function revertRevision(Revision $revision): Response
    {
        $type = $revision->giveContentType()->getName();
        $ouuid = $revision->giveOuuid();

        $newestRevision = $this->dataService->getNewestRevision($type, $ouuid);
        if ($newestRevision->getDraft()) {
            throw new ElasticmsException('Can\`t revert if a  draft exists for the document');
        }

        $revertedRevision = $this->dataService->initNewDraft($type, $ouuid, $revision);

        return $this->redirectToRoute(Routes::EDIT_REVISION, [
            'revisionId' => $revertedRevision->getId(),
        ]);
    }

    public function linkData(string $key): Response
    {
        $category = null;
        $type = null;
        $ouuid = null;
        $split = \explode(':', $key);

        if (3 === \count($split)) {
            $category = $split[0]; // object or asset
            $type = $split[1];
            $ouuid = $split[2];
        }

        if (null != $ouuid && null != $type) {
            $contentType = $this->ctService->getByName($type);

            if (empty($contentType)) {
                throw new NotFoundHttpException('Content type '.$type.'not found');
            }

            // For each type, we must perform a different redirect.
            if ('object' == $category) {
                return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                    'type' => $type,
                    'ouuid' => $ouuid,
                ]);
            }

            $revision = $this->revisionRepository->findByOuuidAndContentTypeAndEnvironment($contentType, $ouuid, $contentType->giveEnvironment());

            if (!$revision instanceof Revision) {
                throw new NotFoundHttpException('Impossible to find this item : '.$ouuid);
            }

            if (\in_array($category, ['asset', 'file'], true)) {
                $rawData = $revision->getRawData();
                $assetField = $contentType->getAssetField();

                if (null === $assetField || isset($rawData[$assetField])) {
                    throw new NotFoundHttpException('Asset field not found for '.$revision);
                }

                return $this->redirectToRoute('ems_file_view', [
                    'sha1' => $rawData[$assetField][EmsFields::CONTENT_FILE_HASH_FIELD_] ?? $rawData[$assetField][EmsFields::CONTENT_FILE_HASH_FIELD],
                    'type' => $rawData[$assetField][EmsFields::CONTENT_MIME_TYPE_FIELD_] ?? $rawData[$assetField][EmsFields::CONTENT_MIME_TYPE_FIELD],
                    'name' => $rawData[$assetField][EmsFields::CONTENT_FILE_NAME_FIELD_] ?? $rawData[$assetField][EmsFields::CONTENT_FILE_NAME_FIELD],
                ]);
            }
        }
        throw new NotFoundHttpException('Impossible to find this item : '.$key);
    }

    /**
     * @param array<mixed> $input
     */
    private function reorderCollection(array &$input): void
    {
        if ([] === $input) {
            return;
        }
        $keys = \array_keys($input);
        if (\is_int($keys[0])) {
            \sort($keys);
            $temp = [];
            $loop0 = 0;
            foreach ($input as $item) {
                $temp[$keys[$loop0]] = $item;
                ++$loop0;
            }
            $input = $temp;
        }
        foreach ($input as &$elem) {
            if (\is_array($elem)) {
                $this->reorderCollection($elem);
            }
        }
    }
}
