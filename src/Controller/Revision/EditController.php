<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Core\Revision\DraftInProgress;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Form\RevisionJsonType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\LoggingContext;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditController extends AbstractController
{
    private DataService $dataService;
    private LoggerInterface $logger;
    private PublishService $publishService;
    private RevisionService $revisionService;
    private TranslatorInterface $translator;
    private WysiwygStylesSetService $wysiwygStylesSetService;
    private DraftInProgress $draftInProgress;

    public function __construct(
        DataService $dataService,
        DraftInProgress $draftInProgress,
        LoggerInterface $logger,
        PublishService $publishService,
        RevisionService $revisionService,
        TranslatorInterface $translator,
        WysiwygStylesSetService $wysiwygStylesSetService
    ) {
        $this->dataService = $dataService;
        $this->logger = $logger;
        $this->publishService = $publishService;
        $this->revisionService = $revisionService;
        $this->translator = $translator;
        $this->wysiwygStylesSetService = $wysiwygStylesSetService;
        $this->draftInProgress = $draftInProgress;
    }

    public function editJsonRevision(Revision $revision, Request $request): Response
    {
        $this->dataService->lockRevision($revision);

        if (!$revision->getDraft()) {
            throw new ElasticmsException($this->translator->trans('log.data.revision.only_draft_can_be_json_edited', LoggingContext::read($revision), EMSCoreBundle::TRANS_DOMAIN));
        }
        if ($request->isMethod('GET') && null != $revision->getAutoSave()) {
            $data = $revision->getAutoSave();
            $this->logger->warning('log.data.revision.load_from_auto_save', LoggingContext::read($revision));
        } else {
            $data = $revision->getRawData();
        }

        $form = $this->createForm(RevisionJsonType::class, [
            'json' => Json::encode($data, true),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $revision->setAutoSave(null);
            $objectArray = Json::decode($form->get('json')->getData());
            $this->revisionService->save($revision, $objectArray);

            return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                'type' => $revision->giveContentType()->getName(),
                'ouuid' => $revision->giveOuuid(),
                'revisionId' => $revision->getId(),
            ]);
        }

        return $this->render('@EMSCore/data/edit-json-revision.html.twig', [
            'revision' => $revision,
            'form' => $form->createView(),
        ]);
    }

    public function editRevision(int $revisionId, Request $request): Response
    {
        if (null === $revision = $this->revisionService->find($revisionId)) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $this->dataService->lockRevision($revision);

        if (null === $contentType = $revision->getContentType()) {
            throw new NotFoundException('ContentType not found!');
        }

        if ($revision->getEndTime() && !$this->isGranted(Roles::ROLE_SUPER)) {
            throw new ElasticmsException($this->translator->trans('log.data.revision.only_super_can_finalize_an_archive', LoggingContext::read($revision), EMSCoreBundle::TRANS_DOMAIN));
        }

        if ($request->isMethod('GET') && null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $this->logger->warning('log.data.revision.load_from_auto_save', LoggingContext::read($revision));
        }

        $form = $this->createForm(RevisionType::class, $revision, [
            'has_clipboard' => $request->getSession()->has('ems_clipboard'),
            'has_copy' => $this->isGranted('ROLE_COPY_PASTE'),
            'raw_data' => $revision->getRawData(),
        ]);
        $this->logger->debug('Revision\'s form created');

        /** @var array<string, mixed> $requestRevision */
        $requestRevision = $request->request->get('revision', []);

        /**little trick to reorder collection*/
        $this->reorderCollection($requestRevision);
        $request->request->set('revision', $requestRevision);
        /**end little trick to reorder collection*/

        $form->handleRequest($request);
        $this->logger->debug('Revision request form handled');

        if ($form->isSubmitted()) {//Save, Finalize or Discard
            $allFieldsAreThere = $requestRevision['allFieldsAreThere'] ?? false;
            if (empty($requestRevision) || !$allFieldsAreThere) {
                $this->logger->error('log.data.revision.not_completed_request', LoggingContext::read($revision));

                return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                    'ouuid' => $revision->getOuuid(),
                    'type' => $contentType->getName(),
                    'revisionId' => $revision->getId(),
                ]);
            }

            $revision->setAutoSave(null);
            if (!isset($requestRevision['discard'])) {//Save, Copy, Paste or Finalize
                //Save anyway
                /** @var Revision $revision */
                $revision = $form->getData();
                $objectArray = $revision->getRawData();

                $this->logger->debug('Revision extracted from the form');

                if (isset($requestRevision['paste'])) {
                    $this->logger->notice('log.data.revision.paste', LoggingContext::update($revision));
                    $objectArray = \array_merge($objectArray, $request->getSession()->get('ems_clipboard', []));
                    $this->logger->debug('Paste data have been merged');
                }

                if (isset($requestRevision['copy'])) {
                    $request->getSession()->set('ems_clipboard', $objectArray);
                    $this->logger->notice('log.data.document.copy', LoggingContext::update($revision));
                }

                $user = $this->getUser();
                if (!$user instanceof UserInterface) {
                    throw new \RuntimeException('Unexpect user object');
                }
                $revision->setAutoSaveBy($user->getUsername());
                if (isset($requestRevision['publish_version'])) {
                    $versionTag = $form->get('publish_version_tags')->getData();
                    $revision = $this->revisionService->saveVersion($revision, $objectArray, $versionTag);
                } else {
                    $this->revisionService->save($revision, $objectArray);
                }

                if (isset($requestRevision['publish'])) {//Finalize
                    $revision = $this->dataService->finalizeDraft($revision, $form);
                }

                if ((isset($requestRevision['publish']) || isset($requestRevision['publish_version']))
                    && 0 === \count($form->getErrors(true))) {
                    if ($revision->getOuuid()) {
                        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                            'ouuid' => $revision->getOuuid(),
                            'type' => $contentType->getName(),
                        ]);
                    } else {
                        return $this->redirectToRoute(Routes::EDIT_REVISION, [
                            'revisionId' => $revision->getId(),
                        ]);
                    }
                }
            }

            if (isset($requestRevision['paste']) || isset($requestRevision['copy'])) {
                return $this->redirectToRoute(Routes::EDIT_REVISION, ['revisionId' => $revisionId]);
            }

            //if Save or Discard
            if (!isset($requestRevision['publish']) && !isset($requestRevision['publish_version'])) {
                if (null != $revision->getOuuid()) {
                    if (0 === \count($form->getErrors()) && $contentType->isAutoPublish()) {
                        $this->publishService->silentPublish($revision);
                    }

                    return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                        'ouuid' => $revision->getOuuid(),
                        'type' => $contentType->getName(),
                        'revisionId' => $revision->getId(),
                    ]);
                } else {
                    return $this->redirectToRoute('data.draft_in_progress', [
                        'contentTypeId' => $contentType->getId(),
                    ]);
                }
            }
        } else {
            $objectArray = $revision->getRawData();
            $isValid = $this->dataService->isValid($form, null, $objectArray);
            if (!$isValid) {
                $this->logger->warning('log.data.revision.can_finalized', LoggingContext::update($revision));
            }
        }

        if ($contentType->isAutoPublish()) {
            $this->logger->warning('log.data.revision.auto_save_off_with_auto_publish', LoggingContext::update($revision));
        }

        $objectArray = $revision->getRawData();
        $this->dataService->propagateDataToComputedField($form->get('data'), $objectArray, $contentType, $contentType->getName(), $revision->getOuuid(), false, false);

        if ($revision->getOuuid()) {
            $this->logger->info('log.data.revision.start_edit', LoggingContext::read($revision));
        } else {
            $this->logger->info('log.data.revision.start_edit_new_document', LoggingContext::read($revision));
        }

        if (!$revision->getDraft()) {
            $this->logger->warning('controller.revision.edit-controller.warning.edit-draft', [
                'path' => $this->generateUrl('revision.new-draft', [
                    'type' => $revision->giveContentType(),
                    'ouuid' => $revision->giveOuuid(),
                ], UrlGeneratorInterface::ABSOLUTE_PATH),
            ]);
        }

        return $this->render('@EMSCore/data/edit-revision.html.twig', [
            'revision' => $revision,
            'form' => $form->createView(),
            'stylesSets' => $this->wysiwygStylesSetService->getStylesSets(),
        ]);
    }

    public function ajaxDraftInProgress(Request $request, ContentType $contentType): Response
    {
        $table = $this->draftInProgress->getDataTable($this->generateUrl(Routes::DRAFT_IN_PROGRESS_AJAX, ['contentType' => $contentType->getId()]), $contentType);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function draftInProgress(Request $request, ContentType $contentTypeId): Response
    {
        $table = $this->draftInProgress->getDataTable($this->generateUrl(Routes::DRAFT_IN_PROGRESS_AJAX, ['contentType' => $contentTypeId->getId()]), $contentTypeId);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case DraftInProgress::DISCARD_SELECTED_DRAFT:
                        foreach ($table->getSelected() as $revisionId) {
                            try {
                                $revision = $this->dataService->getRevisionById(\intval($revisionId), $contentTypeId);
                                if (!$revision->getDraft()) {
                                    continue;
                                }
                                $label = $revision->getLabel();
                                $this->dataService->discardDraft($revision);
                                $this->logger->notice('log.controller.draft-in-progress.discard_draft', ['revision' => $label]);
                            } catch (NotFoundHttpException $e) {
                                $this->logger->warning('log.controller.draft-in-progress.draft-not-found', ['revisionId' => $revisionId]);
                            }
                        }
                        break;
                    default:
                        $this->logger->error('log.controller.draft-in-progress.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.draft-in-progress.unknown_action');
            }

            return $this->redirectToRoute(Routes::DRAFT_IN_PROGRESS, ['contentTypeId' => $contentTypeId->getId()]);
        }

        return $this->render('@EMSCore/data/draft-in-progress.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentTypeId,
        ]);
    }

    /**
     * @param mixed $input
     */
    private function reorderCollection(&$input): void
    {
        if (!\is_array($input) || empty($input)) {
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
            $this->reorderCollection($elem);
        }
    }
}
