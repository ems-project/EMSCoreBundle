<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Json;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\TemplateWrapper;

final class JsonMenuRenderer implements RuntimeExtensionInterface
{
    private Environment $environment;
    private UrlGeneratorInterface $urlGenerator;
    private ContentTypeService $contentTypeService;
    private RevisionService $revisionService;

    public const TYPE_MODAL = 'modal';
    public const TYPE_PASTE = 'paste';
    public const TYPE_VIEW = 'view';
    public const TYPE_PREVIEW = 'preview';
    public const TYPE_REVISION_EDIT = 'revision_edit';

    public const NESTED_TEMPLATE = '@EMSCore/revision/json/json_menu_nested.html.twig';

    public function __construct(
        Environment $environment,
        UrlGeneratorInterface $urlGenerator,
        ContentTypeService $contentTypeService,
        RevisionService $revisionService
    ) {
        $this->environment = $environment;
        $this->urlGenerator = $urlGenerator;
        $this->contentTypeService = $contentTypeService;
        $this->revisionService = $revisionService;
    }

    /**
     * @param array<mixed> $options
     */
    public function generateNested(array $options, string $type = self::TYPE_VIEW): string
    {
        return $this->template()->renderBlock('render', [
            'def' => $this->createDefinition($type, $options),
        ]);
    }

    /**
     * @param array<mixed> $options
     */
    public function generateNestedItem(array $options): string
    {
        return $this->template()->renderBlock('renderItem', [
            'def' => $this->createDefinition(self::TYPE_MODAL, $options),
            'level' => $options['level'],
            'item' => [
                'id' => $options['id'],
                'type' => $options['type'],
                'label' => $options['object']['label'] ?? '',
                'object' => $options['object'],
            ],
        ]);
    }

    /**
     * @param array<mixed> $options
     */
    public function generateNestedPaste(array $options): string
    {
        return $this->template()->renderBlock('renderPaste', [
            'def' => $this->createDefinition(self::TYPE_PASTE, $options),
        ]);
    }

    private function template(): TemplateWrapper
    {
        return $this->environment->load(self::NESTED_TEMPLATE);
    }

    /**
     * @param array<mixed> $options
     */
    private function createDefinition(string $type, array $options): JsonMenuNestedDefinition
    {
        $options = $this->revolveOptions($type, $options);
        $options['type'] = $type;

        return new JsonMenuNestedDefinition($this->urlGenerator, $options);
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private function revolveOptions(string $type, array $options): array
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setRequired(['field_type'])
            ->setDefaults([
                'revision' => null,
                'structure' => '{}',
                'hidden_field_id' => null,
                'item_actions' => ['move', 'copy', 'paste', 'add', 'edit', 'delete'],
            ]);

        switch ($type) {
            case self::TYPE_VIEW:
                return $this->revolveViewOptions($optionsResolver, $options);
            case self::TYPE_PREVIEW:
                $options['item_actions'] = ['preview', 'copy'];
                $optionsResolver->setRequired(['structure']);
                break;
            case self::TYPE_REVISION_EDIT:
                $optionsResolver->setRequired(['structure', 'revision']);
                break;
            case self::TYPE_MODAL:
                $optionsResolver
                    ->setRequired(['revision', 'level', 'type', 'object', 'id']);
                break;
            case self::TYPE_PASTE:
                $optionsResolver->setRequired(['revision', 'structure']);
                break;
            default:
                throw new \Exception('Invalid render type');
        }

        return $optionsResolver->resolve($options);
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private function revolveViewOptions(OptionsResolver $optionsResolver, array $options): array
    {
        $fieldDocument = $options['field_document'] ?? $options['field'];

        $doc = Document::fromArray($options['document']);
        $options['structure'] = $doc->getValue($fieldDocument, '{}');

        $contentType = $this->contentTypeService->giveByName($doc->getContentType());
        $options['field_type'] = $contentType->getFieldType()->getChildByName($options['field']);
        $options['revision'] = $this->revisionService->getCurrentRevisionForDocument($doc);

        $optionsResolver
            ->setDefaults(['field_document' => $fieldDocument])
            ->setRequired(['document', 'field', 'field_document']);

        return $optionsResolver->resolve($options);
    }
}
