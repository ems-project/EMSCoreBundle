<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\InlineEditor;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Common\DocumentInfo;
use EMS\CoreBundle\Core\InlineEditor\Dto\InlineCollectionDto;
use EMS\CoreBundle\Core\InlineEditor\Dto\InlineElementDto;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

readonly class InlineEditor
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private RevisionService $revisionService,
        private DataService $dataService,
        private EnvironmentService $environmentService,
        private UserService $userService,
    ) {
    }

    public function apiAutoSave(InlineElementDto $element, string $content): JsonResponse
    {
        if (null === $revision = $this->revisionService->getByEmsLink($element->emsLink)) {
            throw new \RuntimeException('Revision not found');
        }

        if (!$revision->isDraft()) {
            throw new \RuntimeException('Not in draft');
        }

        $autoSave = $revision->getAutoSave() ?? $revision->getRawData();
        $propertyAccess = PropertyAccess::createPropertyAccessor();
        $propertyAccess->setValue($autoSave, $element->path, $content);

        $this->revisionService->autoSave($revision, $autoSave);

        return new JsonResponse(['success' => true]);
    }

    public function apiDiscard(InlineCollectionDto $collection): JsonResponse
    {
        $drafts = $this->getDrafts($collection);
        foreach ($drafts as $draft) {
            $this->dataService->discardDraft($draft);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param InlineElementDto[] $elements
     */
    public function apiEdit(EMSLink $emsLink, array $elements): JsonResponse
    {
        $draft = $this->dataService->initNewDraft($emsLink->getContentType(), $emsLink->getOuuid());

        $data = [];
        $autoSave = $draft->getAutoSave() ?? [];
        $propertyAccess = PropertyAccess::createPropertyAccessor();

        foreach ($elements as $element) {
            $data[$element->selector] = $propertyAccess->getValue($autoSave, $element->path);
        }

        return new JsonResponse([
            'data' => $data,
            'render' => [
                '.editor-actions' => $this->getTemplateRender()->renderBlock('actions', [
                    'draftId' => $draft->getId(),
                ]),
            ],
        ]);
    }

    public function apiInit(InlineCollectionDto $collection): JsonResponse
    {
        $title = 'Inline Editor';
        $records = [];
        $validSelectors = [];
        $documentsInfo = $this->revisionService->getDocumentsInfo(...$collection->getEMSLinks());

        foreach ($collection->data as $emsId => $elements) {
            $emsLink = EMSLink::fromText($emsId);
            $documentInfo = $documentsInfo[$emsLink->getEmsId()] ?? null;

            if (null === $documentInfo || null === $revision = $documentInfo->getCurrentRevision()) {
                continue;
            }

            $label = $this->revisionService->display($revision);

            foreach ($elements as $element) {
                $validSelectors[] = $element->selector;

                if ('h1' === $element->tag) {
                    $title = $label;
                }
            }

            $records[] = [
                'label' => $label,
                'revision' => $revision,
                'info' => $documentInfo,
                'elements' => $elements,
            ];
        }

        return new JsonResponse([
            'elements' => $validSelectors,
            'render' => [
                '.editor-title' => $title,
                '.editor-sidebar-content' => $this->getTemplateRender()->renderBlock('elements', [
                    'records' => $records,
                    'environments' => $this->environmentService->getUserPublishEnvironments(),
                ]),
            ],
        ]);
    }

    public function apiPublish(InlineCollectionDto $collection): JsonResponse
    {
        $drafts = $this->getDrafts($collection);

        foreach ($drafts as $draft) {
            $this->dataService->finalizeDraft($draft->autoSaveToRawData());
            $this->dataService->refresh($draft->giveContentType()->giveEnvironment());
        }

        return new JsonResponse(['success' => true]);
    }

    public function renderEditor(string $channel, ?string $path): string
    {
        $prefix = \sprintf('/channel/%s', $channel);

        return $this->twig->render('@EMSAdminUI/inline-editor/editor.html.twig', [
            'baseUrl' => $this->urlGenerator->generate(Routes::INLINE_EDIT_EDITOR, ['channel' => $channel]),
            'iframeUrl' => $prefix.$path,
            'routePrefix' => $prefix,
        ]);
    }

    public function renderInjectHead(): string
    {
        return $this->getTemplateInject()->renderBlock('head');
    }

    public function renderInjectBody(Request $request): string
    {
        $channel = $request->attributes->getString(ChannelRegistrar::ATTRIBUTE_CHANNEL_NAME);
        $routePrefix = \sprintf('/channel/%s', $channel);

        $editorUrl = $this->urlGenerator->generate(Routes::INLINE_EDIT_EDITOR, [
            'path' => \substr($request->getPathInfo(), \strlen($routePrefix)),
            'channel' => $channel,
        ]);

        return $this->getTemplateInject()->renderBlock('body', [
            'editorUrl' => $editorUrl,
        ]);
    }

    private function getTemplateInject(): TemplateWrapper
    {
        return $this->twig->load('@EMSAdminUI/inline-editor/inject.html.twig');
    }

    private function getTemplateRender(): TemplateWrapper
    {
        return $this->twig->load('@EMSAdminUI/inline-editor/render.html.twig');
    }

    /**
     * @return Revision[]
     */
    private function getDrafts(InlineCollectionDto $collection): array
    {
        $username = $this->userService->getCurrentUser()->getUsername();

        $documentsInfo = $this->revisionService->getDocumentsInfo(...$collection->getEMSLinks());
        /** @var Revision[] $revisions */
        $revisions = \array_values(\array_map(fn (DocumentInfo $info) => $info->getCurrentRevision(), $documentsInfo));

        return \array_filter($revisions, fn (Revision $revision) => $revision->isDraftForUser($username));
    }
}
