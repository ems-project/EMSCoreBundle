<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Service\Pdf\Pdf;
use EMS\CommonBundle\Service\Pdf\PdfPrinterInterface;
use EMS\CommonBundle\Service\Pdf\PdfPrintOptions;
use EMS\CoreBundle\Core\ContentType\ViewTypes;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\Error;

class DataController extends AbstractController
{
    private LoggerInterface $logger;
    private DataService $dataService;
    private SearchService $searchService;
    private ElasticaService $elasticaService;
    private ContentTypeService $contentTypeService;
    private EnvironmentService $environmentService;
    private IndexService $indexService;
    private TranslatorInterface $translator;
    private ViewTypes $viewTypes;
    private TwigEnvironment $twig;
    private PdfPrinterInterface $pdfPrinter;
    private JobService $jobService;

    public function __construct(
        LoggerInterface $logger,
        DataService $dataService,
        SearchService $searchService,
        ElasticaService $elasticaService,
        ContentTypeService $contentTypeService,
        EnvironmentService $environmentService,
        IndexService $indexService,
        TranslatorInterface $translator,
        ViewTypes $viewTypes,
        TwigEnvironment $twig,
        PdfPrinterInterface $pdfPrinter,
        JobService $jobService
    ) {
        $this->logger = $logger;
        $this->dataService = $dataService;
        $this->searchService = $searchService;
        $this->elasticaService = $elasticaService;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->indexService = $indexService;
        $this->translator = $translator;
        $this->viewTypes = $viewTypes;
        $this->twig = $twig;
        $this->pdfPrinter = $pdfPrinter;
        $this->jobService = $jobService;
    }

    public function rootAction(string $name): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');
        $contentType = $repository->findOneBy([
            'name' => $name,
            'deleted' => false,
        ]);

        if (!$contentType instanceof ContentType) {
            throw new NotFoundHttpException('Content type '.$name.' not found');
        }

        $searchRepository = $em->getRepository('EMSCoreBundle:Form\Search');
        $searches = $searchRepository->findBy([
            'contentType' => $contentType->getId(),
        ]);
        /** @var Search $search */
        foreach ($searches as $search) {
            return $this->forward('EMSCoreBundle:Elasticsearch:search', [
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

        return $this->forward('EMS\CoreBundle\Controller\ElasticsearchController::searchAction', [
            'query' => null,
        ], [
            'search_form' => $searchForm->jsonSerialize(),
        ]);
    }

    public function inMyCirclesAction(string $name): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');
        $contentType = $repository->findOneBy([
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

        $searchForm->filters = [];
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

        $formEncoded = \json_encode($searchForm);
        if (false === $formEncoded) {
            throw new \RuntimeException('Unexpected null json');
        }

        return $this->forward('EMSCoreBundle:Elasticsearch:search', [
            'query' => null,
        ], [
            'search_form' => \json_decode($formEncoded, true),
        ]);
    }

    public function trashAction(ContentType $contentType): Response
    {
        return $this->render('@EMSCore/data/trash.html.twig', [
            'contentType' => $contentType,
            'revisions' => $this->dataService->getAllDeleted($contentType),
        ]);
    }

    public function putBackAction(ContentType $contentType, string $ouuid): RedirectResponse
    {
        $revId = $this->dataService->putBack($contentType, $ouuid);

        return $this->redirectToRoute(Routes::EDIT_REVISION, [
            'revisionId' => $revId,
        ]);
    }

    public function emptyTrashAction(ContentType $contentType, string $ouuid): RedirectResponse
    {
        $this->dataService->emptyTrash($contentType, $ouuid);

        return $this->redirectToRoute('ems_data_trash', [
            'contentType' => $contentType->getId(),
        ]);
    }

    public function viewDataAction(string $environmentName, string $type, string $ouuid): Response
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
        } catch (\Throwable $e) {
            throw new NotFoundHttpException(\sprintf('Document %s with identifier %s not found in environment %s', $contentType->getSingularName(), $ouuid, $environmentName));
        }

        return $this->render('@EMSCore/data/view-data.html.twig', [
            'object' => $document->getRaw(),
            'environment' => $environment,
            'contentType' => $contentType,
        ]);
    }

    public function revisionInEnvironmentDataAction(string $type, string $ouuid, string $environment): RedirectResponse
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
        } catch (NoResultException $e) {
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
        $response->headers->set('Content-Type', 'text/plain');
        $response->setContent($this->dataService->getPublicKey());

        return $response;
    }

    public function duplicateAction(string $environment, string $type, string $ouuid): RedirectResponse
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
        } catch (NoResultException $e) {
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

    public function copyAction(string $environment, string $type, string $ouuid, Request $request): RedirectResponse
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
        } catch (NoResultException $e) {
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

    public function newDraftAction(Request $request, string $type, string $ouuid): RedirectResponse
    {
        return $this->redirectToRoute(Routes::EDIT_REVISION, [
            'revisionId' => $this->dataService->initNewDraft($type, $ouuid)->getId(),
            'item' => $request->get('item'),
        ]);
    }

    public function deleteAction(string $type, string $ouuid): RedirectResponse
    {
        $revision = $this->dataService->getNewestRevision($type, $ouuid);
        $contentType = $revision->giveContentType();
        $deleteRole = $contentType->getDeleteRole();

        if ($deleteRole && !$this->isGranted($deleteRole)) {
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
                } catch (NoResultException $e) {
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

        return $this->redirectToRoute('data.root', [
            'name' => $type,
        ]);
    }

    public function discardDraft(Revision $revision): ?int
    {
        return $this->dataService->discardDraft($revision);
    }

    public function discardRevisionAction(int $revisionId): RedirectResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

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
                return $this->reindexRevisionAction($previousRevisionId, true);
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

    public function cancelModificationsAction(Revision $revision, PublishService $publishService): RedirectResponse
    {
        $contentTypeId = $revision->giveContentType()->getId();
        $type = $revision->giveContentType()->getName();
        $ouuid = $revision->getOuuid();

        $this->dataService->lockRevision($revision);

        $em = $this->getDoctrine()->getManager();
        $revision->setAutoSave(null);
        $em->persist($revision);
        $em->flush();

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

    public function reindexRevisionAction(int $revisionId, bool $defaultOnly = false): RedirectResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if (null === $revision) {
            throw $this->createNotFoundException('Revision not found');
        }

        $this->dataService->lockRevision($revision);

        try {
            $this->dataService->reloadData($revision);
            $this->dataService->sign($revision);

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
            $em->persist($revision);
            $em->flush();
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

    public function customIndexViewAction(View $viewId, bool $public, Request $request): Response
    {
        $view = $viewId;
        if ($public && !$view->isPublic()) {
            throw new NotFoundHttpException($this->translator->trans('log.view.not_found', ['%view_id%' => $viewId->getId()], EMSCoreBundle::TRANS_DOMAIN));
        }
        $viewType = $this->viewTypes->get($view->getType());

        return $viewType->generateResponse($view, $request);
    }

    public function customViewAction(string $environmentName, int $templateId, string $ouuid, bool $_download, bool $public): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');

        /** @var Template|null $template * */
        $template = $templateRepository->find($templateId);

        if (null === $template || ($public && !$template->isPublic())) {
            throw new NotFoundHttpException('Template type not found');
        }

        /** @var EnvironmentRepository $environmentRepository */
        $environmentRepository = $em->getRepository('EMSCoreBundle:Environment');

        $environment = $environmentRepository->findBy([
            'name' => $environmentName,
        ]);

        if (!$environment || 1 != \count($environment)) {
            throw new NotFoundHttpException('Environment type not found');
        }

        /** @var Environment $environment */
        $environment = $environment[0];

        $document = $this->searchService->get($environment, $template->getContentType(), $ouuid);

        try {
            $body = $this->twig->createTemplate($template->getBody());
        } catch (Error $e) {
            $this->logger->error('log.template.twig.error', [
                'template_id' => $template->getId(),
                'template_name' => $template->getName(),
                'error_message' => $e->getMessage(),
            ]);
            $body = $this->twig->createTemplate($this->translator->trans('log.template.twig.error', [
                '%template_id%' => $template->getId(),
                '%template_name%' => $template->getName(),
                '%error_message%' => $e->getMessage(),
            ], EMSCoreBundle::TRANS_DOMAIN));
        }

        if (RenderOptionType::PDF === $template->getRenderOption() && ($_download || !$template->getPreview())) {
            $output = $body->render([
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
                '_download' => true,
            ]);
            $filename = $this->generateFilename($this->twig, $template->getFilename() ?? 'document.pdf', [
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
                '_download' => true,
            ]);

            $pdf = new Pdf($filename, $output);
            $printOptions = new PdfPrintOptions([
                PdfPrintOptions::ATTACHMENT => PdfPrintOptions::ATTACHMENT === $template->getDisposition(),
                PdfPrintOptions::COMPRESS => true,
                PdfPrintOptions::HTML5_PARSING => true,
                PdfPrintOptions::ORIENTATION => $template->getOrientation() ?? 'portrait',
                PdfPrintOptions::SIZE => $template->getSize() ?? 'A4',
            ]);

            return $this->pdfPrinter->getStreamedResponse($pdf, $printOptions);
        }
        if ($_download || (0 === \strcmp($template->getRenderOption(), RenderOptionType::EXPORT) && !$template->getPreview())) {
            if (null != $template->getMimeType()) {
                \header('Content-Type: '.$template->getMimeType());
            }

            $filename = $this->generateFilename($this->twig, $template->getFilename() ?? $ouuid, [
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
            ]);

            if (!empty($template->getDisposition())) {
                $attachment = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
                if ('inline' == $template->getDisposition()) {
                    $attachment = ResponseHeaderBag::DISPOSITION_INLINE;
                }
                \header("Content-Disposition: $attachment; filename=".$filename.($template->getExtension() ? '.'.$template->getExtension() : ''));
            }
            if (null != $template->getAllowOrigin()) {
                \header('Access-Control-Allow-Origin: '.$template->getAllowOrigin());
                \header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, Accept-Language, If-None-Match, If-Modified-Since');
                \header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
            }

            $output = $body->render([
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
            ]);
            echo $output;

            exit;
        }

        return $this->render('@EMSCore/data/custom-view.html.twig', [
            'template' => $template,
            'object' => $document,
            'environment' => $environment,
            'contentType' => $template->getContentType(),
            'body' => $body,
        ]);
    }

    public function customViewJobAction(string $environmentName, int $templateId, string $ouuid, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Template|null $template * */
        $template = $em->getRepository(Template::class)->find($templateId);
        /** @var Environment|null $env */
        $env = $em->getRepository(Environment::class)->findOneByName($environmentName);

        if (null === $template || null === $env) {
            throw new NotFoundHttpException();
        }

        $document = $this->searchService->get($env, $template->getContentType(), $ouuid);

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
            $job = $this->jobService->createCommand($user, $command);

            $success = true;
            $this->logger->notice('log.data.job.initialized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                'template_id' => $template->getId(),
                'job_id' => $job->getId(),
                'template_name' => $template->getName(),
                'environment' => $env->getLabel(),
            ]);

            return EmsCoreResponse::createJsonResponse($request, true, [
                'jobId' => $job->getId(),
                'jobUrl' => $this->generateUrl('emsco_job_start', ['job' => $job->getId()], UrlGeneratorInterface::ABSOLUTE_PATH),
                'url' => $this->generateUrl('emsco_job_status', ['job' => $job->getId()], UrlGeneratorInterface::ABSOLUTE_PATH),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('log.data.job.initialize_failed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                'template_name' => $template->getName(),
                'environment' => $env->getLabel(),
            ]);
        }

        $response = $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => $success,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function ajaxUpdateAction(int $revisionId, Request $request, PublishService $publishService): Response
    {
        $em = $this->getDoctrine()->getManager();
        $formErrors = [];

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

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

            $response = $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        }

        $revisionInRequest = $request->request->get('revision');
        if (empty($revisionInRequest) || !isset($revisionInRequest['allFieldsAreThere']) || empty($revisionInRequest['allFieldsAreThere'])) {
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

            //If the bag is not empty the user already see its content when opening the edit page
            $request->getSession()->getBag('flashes')->clear();

            /**little trick to reorder collection*/
            $requestRevision = $request->request->get('revision');
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

            $em->persist($revision);
            $em->flush();

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

        $response = $this->render('@EMSCore/data/ajax-revision.json.twig', [
            'success' => true,
            'formErrors' => $formErrors,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function finalizeDraftAction(Revision $revision): Response
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
            if (0 !== \count($form->getErrors())) {
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

    public function duplicateWithJsonContentAction(ContentType $contentType, string $ouuid, Request $request): RedirectResponse
    {
        $content = $request->get('JSON_BODY', null);
        $jsonContent = \json_decode($content, true);
        $jsonContent = \array_merge($this->dataService->getNewestRevision($contentType->getName(), $ouuid)->getRawData(), $jsonContent);

        return $this->intNewDocumentFromArray($contentType, $jsonContent);
    }

    public function addFromJsonContentAction(ContentType $contentType, Request $request): RedirectResponse
    {
        $content = $request->get('JSON_BODY', null);
        $jsonContent = \json_decode($content, true);
        if (null === $jsonContent) {
            $this->logger->error('log.data.revision.add_from_json_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
            ]);

            return $this->redirectToRoute('data.root', [
                'name' => $contentType->getName(),
            ]);
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

            return $this->redirectToRoute('data.root', [
                'name' => $contentType->getName(),
            ]);
        }
    }

    public function addAction(ContentType $contentType, Request $request): Response
    {
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
            } catch (DuplicateOuuidException $e) {
                $form->get('ouuid')->addError(new FormError('Another '.$contentType->getName().' with this identifier already exists'));
            }
        }

        return $this->render('@EMSCore/data/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    public function revertRevisionAction(Revision $revision): Response
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

    public function linkDataAction(string $key, ContentTypeService $ctService): Response
    {
        $category = $type = $ouuid = null;
        $split = \explode(':', $key);

        if (3 === \count($split)) {
            $category = $split[0]; // object or asset
            $type = $split[1];
            $ouuid = $split[2];
        }

        if (null != $ouuid && null != $type) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var RevisionRepository $repository */
            $repository = $em->getRepository('EMSCoreBundle:Revision');

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

            $revision = $repository->findByOuuidAndContentTypeAndEnvironment($contentType, $ouuid, $contentType->giveEnvironment());

            if (!$revision instanceof Revision) {
                throw new NotFoundHttpException('Impossible to find this item : '.$ouuid);
            }

            if ('asset' == $category) {
                if (empty($contentType->getAssetField()) && empty($revision->getRawData()[$contentType->getAssetField()])) {
                    throw new NotFoundHttpException('Asset field not found for '.$revision);
                }

                return $this->redirectToRoute('file.download', [
                    'sha1' => $revision->getRawData()[$contentType->getAssetField()]['sha1'],
                    'type' => $revision->getRawData()[$contentType->getAssetField()]['mimetype'],
                    'name' => $revision->getRawData()[$contentType->getAssetField()]['filename'],
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

    /**
     * @param array<string, mixed> $options
     */
    private function generateFilename(TwigEnvironment $twig, string $rawTemplate, array $options): string
    {
        try {
            $template = $twig->createTemplate($rawTemplate);
            $filename = $template->render($options);
            $filename = \preg_replace('~[\r\n]+~', '', $filename);
        } catch (\Throwable $e) {
            $filename = null;
        }

        return $filename ?? 'error-in-filename-template';
    }
}
