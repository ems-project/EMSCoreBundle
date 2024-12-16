<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\NoResultException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\MimeTypeHelper;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\ContentType\ViewTypes;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\SearchRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Twig\AppExtension;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class DataController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DataService $dataService,
        private readonly SearchService $searchService,
        private readonly ContentTypeService $contentTypeService,
        private readonly EnvironmentService $environmentService,
        private readonly IndexService $indexService,
        private readonly TranslatorInterface $translator,
        private readonly ViewTypes $viewTypes,
        private readonly TwigEnvironment $twig,
        private readonly JobService $jobService,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly SearchRepository $searchRepository,
        private readonly RevisionRepository $revisionRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly FlashMessageLogger $flashMessageLogger,
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
            return $this->forward('EMS\CoreBundle\Controller\ElasticsearchController::search', [
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

        return $this->forward('EMS\CoreBundle\Controller\ElasticsearchController::search', [
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

        return $this->forward('EMS\CoreBundle\Controller\ElasticsearchController::search', [
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

        return $this->render("@$this->templateNamespace/data/view-data.html.twig", [
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
            $this->logger->warning('log.data.revision.not_found_in_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
            ]);

            return $this->redirectToRoute('data.draft_in_progress', ['contentTypeId' => $contentType->getId()]);
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
            $dataRaw = $this->dataService->getRevisionByEnvironment($ouuid, $contentType, $environmentObject)->getCopyRawData();
        } catch (NoResultException) {
            throw new NotFoundHttpException(\sprintf('Revision %s not found', $ouuid));
        }

        if ($contentType->getAskForOuuid()) {
            $this->logger->warning('log.data.document.cant_duplicate_when_waiting_ouuid', [
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
            ]);

            return $this->redirectToRoute('data.view', [
                'environmentName' => $environment,
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        $revision = $this->dataService->newDocument($contentType, null, $dataRaw);

        $this->logger->notice('log.data.document.duplicated', [
            EmsFields::LOG_OUUID_FIELD => $ouuid,
            EmsFields::LOG_CONTENTTYPE_FIELD => $type,
        ]);

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
            $dataRaw = $this->dataService->getRevisionByEnvironment($ouuid, $contentType, $environmentObject)->getCopyRawData();
        } catch (NoResultException) {
            throw new NotFoundHttpException(\sprintf('Revision %s not found', $ouuid));
        }

        $request->getSession()->set('ems_clipboard', $dataRaw);

        $this->logger->notice('log.data.document.copy', [
            EmsFields::LOG_OUUID_FIELD => $ouuid,
            EmsFields::LOG_CONTENTTYPE_FIELD => $type,
        ]);

        return $this->redirectToRoute('data.view', [
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
            'item' => $request->get('item'),
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
            /** @var Environment $environment */
            if ($environment !== $revision->giveContentType()->giveEnvironment()) {
                try {
                    $sibling = $this->dataService->getRevisionByEnvironment($ouuid, $revision->giveContentType(), $environment);
                    $this->logger->warning(
                        'log.data.revision.cant_delete_has_published',
                        LogRevisionContext::publish($sibling, $environment)
                    );
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

        return $this->redirectToRoute('data.draft_in_progress', [
            'contentTypeId' => $contentTypeId,
        ]);
    }

    public function cancelModifications(Revision $revision, PublishService $publishService): RedirectResponse
    {
        $contentTypeId = $revision->giveContentType()->getId();
        $type = $revision->giveContentType()->getName();
        $ouuid = $revision->getOuuid();

        $this->dataService->lockRevision($revision);
        $revision->setAutoSave(null);
        $this->revisionRepository->save($revision);

        if (null != $ouuid) {
            if ($revision->giveContentType()->isAutoPublish()) {
                $publishService->silentPublish($revision);

                $this->logger->warning('log.data.revision.auto_publish_rollback', [
                    EmsFields::LOG_OUUID_FIELD => $ouuid,
                    EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $revision->giveContentType()->giveEnvironment()->getName(),
                ]);
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
                        $this->logger->notice('log.data.revision.reindex', LogRevisionContext::update($revision));
                    } else {
                        $this->logger->warning('log.data.revision.reindex_failed_in', LogRevisionContext::update($revision));
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('log.data.revision.reindex_failed', \array_merge(LogRevisionContext::update($revision), [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]));
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
            throw new NotFoundHttpException($this->translator->trans('log.view.not_found', ['%view_id%' => $viewId->getId()], EMSCoreBundle::TRANS_DOMAIN));
        }
        $viewType = $this->viewTypes->get($view->getType());

        return $viewType->generateResponse($view, $request);
    }

    public function customViewJob(string $environmentName, int $templateId, string $ouuid, Request $request): Response
    {
        /** @var Template|null $template * */
        $template = $this->templateRepository->find($templateId);
        /** @var Environment|null $env */
        $env = $this->environmentRepository->findOneByName($environmentName);

        if (null === $template || !$env) {
            throw new NotFoundHttpException();
        }

        $document = $this->searchService->getDocument($template->giveContentType(), $ouuid, $env);

        $success = false;
        try {
            $command = $this->twig->createTemplate($template->getBody())->render([
                'environment' => $env->getName(),
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
            ]);

            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new \RuntimeException('Unexpected user object');
            }
            $job = $this->jobService->createCommand($user, $command, $template->getTag());

            $success = true;
            $this->logger->notice('log.data.job.initialized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->giveContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                'template_id' => $template->getId(),
                'job_id' => $job->getId(),
                'template_name' => $template->getName(),
                'template_label' => $template->getLabel(),
                'environment' => $env->getLabel(),
            ]);

            return EmsCoreResponse::createJsonResponse($request, true, [
                'jobId' => $job->getId(),
                'jobUrl' => $this->generateUrl('emsco_job_start', ['job' => $job->getId()], UrlGeneratorInterface::ABSOLUTE_PATH),
                'url' => $this->generateUrl('emsco_job_status', ['job' => $job->getId()], UrlGeneratorInterface::ABSOLUTE_PATH),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('log.data.job.initialize_failed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                'template_name' => $template->getName(),
                'template_label' => $template->getLabel(),
                'environment' => $env->getLabel(),
            ]);
        }

        $response = $this->flashMessageLogger->buildJsonResponse([
            'success' => $success,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function ajaxUpdate(int $revisionId, Request $request, PublishService $publishService): Response
    {
        $formErrors = [];

        /** @var Revision|null $revision */
        $revision = $this->revisionRepository->find($revisionId);

        if (null === $revision) {
            throw new NotFoundHttpException('Revision not found');
        }

        if (!$revision->getDraft() || null !== $revision->getEndTime()) {
            $this->logger->warning('log.data.revision.ajax_update_on_finalized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);

            $response = $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
            ]);
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        }

        $revisionInRequest = $request->request->all('revision');
        if (empty($revisionInRequest['allFieldsAreThere'])) {
            $this->logger->error('log.data.revision.not_completed_request', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);
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
            $this->dataService->propagateDataToComputedField($form->get('data'), $objectArray, $revision->giveContentType(), $revision->giveContentType()->getName(), $revision->getOuuid(), false, false);

            $session = $request->getSession();
            if ($session instanceof Session) {
                $session->getFlashBag()->set('warning', []);
            }

            $formErrors = $form->getErrors(true, true);

            if (0 === $formErrors->count() && $revision->giveContentType()->isAutoPublish()) {
                $publishService->silentPublish($revision);
            }
        }

        $serialisedFormErrors = [];
        /** @var FormError $error */
        foreach ($formErrors as $error) {
            $serialisedFormErrors[] = [
                'propertyPath' => AppExtension::propertyPath($error),
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
                $this->logger->error('log.data.revision.can_finalized_as_pending_auto_save', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                ]);

                return $this->redirectToRoute(Routes::EDIT_REVISION, [
                    'revisionId' => $revision->getId(),
                ]);
            }

            $revision = $this->dataService->finalizeDraft($revision, $form);
            if (0 !== (\is_countable($form->getErrors()) ? \count($form->getErrors()) : 0)) {
                $this->logger->error('log.data.revision.can_finalized_as_invalid', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'count' => $form->getErrors(true)->count(),
                ]);

                return $this->redirectToRoute(Routes::EDIT_REVISION, [
                    'revisionId' => $revision->getId(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('log.data.revision.can_finalized_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
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
        $content = $request->get('JSON_BODY');
        $jsonContent = Json::decode((string) $content);
        $jsonContent = \array_merge($this->dataService->getNewestRevision($contentType->getName(), $ouuid)->getRawData(), $jsonContent);

        return $this->intNewDocumentFromArray($contentType, $jsonContent);
    }

    public function addFromJsonContent(ContentType $contentType, Request $request): RedirectResponse
    {
        try {
            $content = $request->get('JSON_BODY');
            $jsonContent = Json::decode((string) $content);
        } catch (\Throwable) {
            $this->logger->error('log.data.revision.add_from_json_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
            ]);

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
        } catch (\Throwable $e) {
            $this->logger->error('log.data.revision.init_document_from_array', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
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
                'constraints' => [new Regex([
                    'pattern' => '/^[A-Za-z0-9_\.\-~]*$/',
                    'match' => true,
                    'message' => 'Ouuid has an unauthorized character.',
                ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Auto-generated if left empty',
                ],
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create '.$contentType->getName().' draft',
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

        return $this->render("@$this->templateNamespace/data/add.html.twig", [
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

    public function linkData(string $key, ContentTypeService $ctService): Response
    {
        $category = $type = $ouuid = null;
        $split = \explode(':', $key);

        if (3 === \count($split)) {
            $category = $split[0]; // object or asset
            $type = $split[1];
            $ouuid = $split[2];
        }

        if (null != $ouuid && null != $type) {
            $contentType = $ctService->getByName($type);

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

            if (\in_array($category, ['asset', 'file'])) {
                if (empty($contentType->getAssetField()) && empty($revision->getRawData()[$contentType->getAssetField()])) {
                    throw new NotFoundHttpException('Asset field not found for '.$revision);
                }

                return $this->redirectToRoute('ems_file_view', [
                    'sha1' => $revision->getRawData()[$contentType->getAssetField()][EmsFields::CONTENT_FILE_HASH_FIELD_] ?? $revision->getRawData()[$contentType->getAssetField()][EmsFields::CONTENT_FILE_HASH_FIELD],
                    'type' => $revision->getRawData()[$contentType->getAssetField()][EmsFields::CONTENT_MIME_TYPE_FIELD_] ?? $revision->getRawData()[$contentType->getAssetField()][EmsFields::CONTENT_MIME_TYPE_FIELD],
                    'name' => $revision->getRawData()[$contentType->getAssetField()][EmsFields::CONTENT_FILE_NAME_FIELD_] ?? $revision->getRawData()[$contentType->getAssetField()][EmsFields::CONTENT_FILE_NAME_FIELD],
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
        if (empty($input)) {
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
