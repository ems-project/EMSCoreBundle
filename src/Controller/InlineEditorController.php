<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Core\InlineEditor\Dto\InlineCollectionDto;
use EMS\CoreBundle\Core\InlineEditor\Dto\InlineElementDto;
use EMS\CoreBundle\Core\InlineEditor\InlineEditor;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class InlineEditorController
{
    public function __construct(
        private InlineEditor $inlineEditor
    ) {
    }

    public function apiAutoSave(Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);

        return $this->inlineEditor->apiAutoSave(
            element: InlineElementDto::fromArray($data['element']),
            content: $data['content'],
        );
    }

    public function apiDiscard(Request $request): JsonResponse
    {
        return $this->inlineEditor->apiDiscard(collection: $this->getCollection($request));
    }

    public function apiEdit(Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);

        $emsLink = EMSLink::fromText($data['emsId']);
        $elements = \array_map(InlineElementDto::fromArray(...), $data['elements']);

        return $this->inlineEditor->apiEdit($emsLink, $elements);
    }

    public function apiInit(Request $request): JsonResponse
    {
        return $this->inlineEditor->apiInit(collection: $this->getCollection($request));
    }

    public function apiPublish(Request $request): JsonResponse
    {
        return $this->inlineEditor->apiPublish(collection: $this->getCollection($request));
    }

    public function editor(string $channel, ?string $path): Response
    {
        return new Response($this->inlineEditor->renderEditor($channel, $path));
    }

    private function getCollection(Request $request): InlineCollectionDto
    {
        return new InlineCollectionDto($this->getJsonData($request)['collection']);
    }

    /**
     * @return array<mixed>
     */
    private function getJsonData(Request $request): array
    {
        if ('json' !== $request->getContentTypeFormat()) {
            throw new BadRequestException('Unsupported content format');
        }

        return Json::decode($request->getContent());
    }
}
