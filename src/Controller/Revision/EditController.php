<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionDraftsDataTableType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Data\TableInterface;
use EMS\CoreBundle\Form\Form\RevisionJsonType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class EditController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly DataService $dataService,
        private readonly LocalizedLoggerInterface $logger,
        private readonly PublishService $publishService,
        private readonly RevisionService $revisionService,
        private readonly TranslatorInterface $translator,
        private readonly DataTableFactory $dataTableFactory,
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace,
    ) {
    }

    public function editJsonRevision(Revision $revision, Request $request): Response
    {
        if (!$this->isGranted($revision->giveContentType()->role(ContentTypeRoles::EDIT))) {
            throw new AccessDeniedException($request->getPathInfo());
        }
        if (!$revision->getDraft()) {
            throw new ElasticmsException($this->translator->trans('log.data.revision.only_draft_can_be_json_edited', LogRevisionContext::read($revision), EMSCoreBundle::TRANS_DOMAIN));
        }

        $this->dataService->lockRevision($revision);
        if ($request->isMethod('GET') && null != $revision->getAutoSave()) {
            $data = $revision->getAutoSave();
            $this->logger->notice('log.data.revision.load_from_auto_save', LogRevisionContext::read($revision));
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

        return $this->render("@$this->templateNamespace/data/edit-json-revision.html.twig", [
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
        $contentType = $revision->giveContentType();

        if ($revision->hasEndTime() && !$this->isGranted(Roles::ROLE_SUPER)) {
            throw new ElasticmsException($this->translator->trans('log.data.revision.only_super_can_finalize_an_archive', LogRevisionContext::read($revision), EMSCoreBundle::TRANS_DOMAIN));
        }

        if (!$revision->getDraft() && $revision->isPublished($contentType->giveEnvironment()->getName())) {
            throw new \RuntimeException('Only a draft is allowed for editing the revision!');
        }

        if ($request->isMethod('GET') && null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $this->logger->notice('log.data.revision.load_from_auto_save', LogRevisionContext::read($revision));
        }

        $form = $this->createForm(RevisionType::class, $revision, [
            'has_clipboard' => $request->getSession()->has('ems_clipboard'),
            'has_copy' => $this->isGranted('ROLE_COPY_PASTE'),
            'raw_data' => $revision->getRawData(),
        ]);
        $this->logger->debug('Revision\'s form created');

        /** @var array<string, mixed> $requestRevision */
        $requestRevision = $request->request->all('revision');

        /**little trick to reorder collection*/
        $this->reorderCollection($requestRevision);
        $request->request->set('revision', $requestRevision);
        /**end little trick to reorder collection*/

        $form->handleRequest($request);
        $this->logger->debug('Revision request form handled');

        if ($form->isSubmitted()) {// Save, Finalize or Discard
            $allFieldsAreThere = $requestRevision['allFieldsAreThere'] ?? false;
            if (empty($requestRevision) || !$allFieldsAreThere) {
                $this->logger->error('log.data.revision.not_completed_request', LogRevisionContext::read($revision));

                return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
                    'ouuid' => $revision->getOuuid(),
                    'type' => $contentType->getName(),
                    'revisionId' => $revision->getId(),
                ]);
            }

            $revision->setAutoSave(null);
            if (!isset($requestRevision['discard'])) {// Save, Copy, Paste or Finalize
                // Save anyway
                /** @var Revision $revision */
                $revision = $form->getData();
                $objectArray = $revision->getRawData();

                $this->logger->debug('Revision extracted from the form');

                if (isset($requestRevision['paste'])) {
                    $this->logger->notice('log.data.revision.paste', LogRevisionContext::update($revision));
                    $objectArray = \array_merge($objectArray, $request->getSession()->get('ems_clipboard', []));
                    $this->revisionService->save($revision, $objectArray);
                    $this->logger->debug('Paste data have been merged');
                }

                if (isset($requestRevision['copy'])) {
                    $request->getSession()->set('ems_clipboard', $objectArray);
                    $this->logger->notice('log.data.document.copy', LogRevisionContext::update($revision));
                }

                $user = $this->getUser();
                if (!$user instanceof UserInterface) {
                    throw new \RuntimeException('Unexpect user object');
                }
                $revision->setAutoSaveBy($user->getUsername());

                if (isset($requestRevision['save'])) {
                    $this->revisionService->save($revision, $objectArray);
                    foreach ($revision->getEnvironments() as $publishedEnvironment) {
                        $this->publishService->publish($revision, $publishedEnvironment); // edit revision not default environment
                    }
                }

                if (isset($requestRevision['publish'])) {// Finalize
                    $revision = $this->dataService->finalizeDraft($revision, $form);

                    if (0 === (\is_countable($form->getErrors(true)) ? \count($form->getErrors(true)) : 0)) {
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
            }

            if (isset($requestRevision['paste']) || isset($requestRevision['copy'])) {
                return $this->redirectToRoute(Routes::EDIT_REVISION, ['revisionId' => $revisionId]);
            }

            // if Save or Discard
            if (!isset($requestRevision['publish'])) {
                if (null != $revision->getOuuid()) {
                    if (0 === (\is_countable($form->getErrors()) ? \count($form->getErrors()) : 0) && $contentType->isAutoPublish()) {
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
                $this->logger->warning('log.data.revision.can_finalized', LogRevisionContext::update($revision));
            }
        }

        if ($contentType->isAutoPublish()) {
            $this->logger->warning('log.data.revision.auto_save_off_with_auto_publish', LogRevisionContext::update($revision));
        }

        $objectArray = $revision->getRawData();
        $this->dataService->propagateDataToComputedField($form->get('data'), $objectArray, $contentType, $contentType->getName(), $revision->getOuuid(), false, false);

        if ($revision->getOuuid()) {
            $this->logger->info('log.data.revision.start_edit', LogRevisionContext::read($revision));
        } else {
            $this->logger->info('log.data.revision.start_edit_new_document', LogRevisionContext::read($revision));
        }

        if (!$revision->getDraft()) {
            $this->logger->warning('controller.revision.edit-controller.warning.edit-draft', [
                'path' => $this->generateUrl('revision.new-draft', [
                    'type' => $revision->giveContentType(),
                    'ouuid' => $revision->giveOuuid(),
                ], UrlGeneratorInterface::ABSOLUTE_PATH),
            ]);
        }

        return $this->render("@$this->templateNamespace/data/edit-revision.html.twig", [
            'revision' => $revision,
            'form' => $form->createView(),
        ]);
    }

    public function draftInProgress(Request $request, ContentType $contentTypeId): Response
    {
        $table = $this->dataTableFactory->create(RevisionDraftsDataTableType::class, [
            'content_type_name' => $contentTypeId->getName(),
        ]);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                RevisionDraftsDataTableType::DISCARD_SELECTED_DRAFT => $this->discardRevisions($table, $contentTypeId),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::DRAFT_IN_PROGRESS, ['contentTypeId' => $contentTypeId->getId()]);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-fire',
            'title' => t('revision.draft.title', ['pluralName' => $contentTypeId->getPluralName()], 'emsco-core'),
            'breadcrumb' => [
                'contentType' => $contentTypeId,
                'page' => t('revision.draft.label', [], 'emsco-core'),
            ],
        ]);
    }

    public function archiveRevision(Revision $revision): Response
    {
        $contentType = $revision->giveContentType();

        if (!$this->isGranted($contentType->role(ContentTypeRoles::ARCHIVE))) {
            throw $this->createAccessDeniedException('Archive not granted!');
        }
        if ($revision->hasEndTime()) {
            throw new \RuntimeException('Only a current revision can be archived');
        }
        if ($revision->isArchived()) {
            throw new \RuntimeException('This revision is already archived');
        }
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpect user object');
        }

        $this->dataService->lockRevision($revision);
        $this->revisionService->archive($revision, $user->getUsername());

        return $this->contentTypeService->redirectOverview($contentType);
    }

    private function reorderCollection(mixed &$input): void
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

    private function discardRevisions(TableInterface $table, ContentType $contentType): void
    {
        foreach ($table->getSelected() as $revisionId) {
            try {
                $revision = $this->dataService->getRevisionById(\intval($revisionId), $contentType);
                if (!$revision->getDraft()) {
                    continue;
                }
                $label = $this->revisionService->display($revision);
                $this->dataService->discardDraft($revision);

                $this->logger->messageNotice(t('log.notice.draft_deleted', ['revision' => $label], 'emsco-core'));
            } catch (NotFoundHttpException) {
                $this->logger->messageWarning(t('log.warning.draft_not_found', ['revisionId' => $revisionId], 'emsco-core'));
            }
        }
    }
}
