<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Json;

use EMS\CoreBundle\Service\ContentTypeService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\TemplateWrapper;

final class JsonMenuRenderer implements RuntimeExtensionInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private ContentTypeService $contentTypeService;
    private Environment $environment;

    public const TYPE_MODAL = 'modal';
    public const TYPE_PASTE = 'paste';
    public const TYPE_VIEW = 'view';
    public const TYPE_PREVIEW = 'preview';
    public const TYPE_REVISION_EDIT = 'revision_edit';

    public const NESTED_TEMPLATE = '@EMSCore/revision/json/json_menu_nested.html.twig';

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        ContentTypeService $contentTypeService,
        Environment $environment
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->contentTypeService = $contentTypeService;
        $this->environment = $environment;
    }

    /**
     * @param array<mixed> $options
     */
    public function generateNested(array $options, string $type = self::TYPE_VIEW): string
    {
        $definitions[] = $this->createDefinition($type, $options);

//        foreach ($items as $item) {
//            $doc = Document::fromArray($item['document']);
//            $structure = $doc->getValue($item['field_document'], '{}');
//
//            $contentType = $this->contentTypeService->giveByName($doc->getContentType());
//            $fieldType = $contentType->getFieldType()->getChildByName($item['field_name']);
//
//            $definitions[] = new JsonMenuNestedDefinition($fieldType, $structure);
//        }

        return $this->template()->renderBlock('render', ['definitions' => $definitions]);
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
                'id' => $options['id'] ?? Uuid::uuid4(),
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
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setDefaults([
                'revision' => null,
                'field_type' => null,
                'structure' => '{}',
                'hidden_field_id' => null,
                'item_move' => true,
                'item_copy' => true,
                'item_paste' => true,
                'item_add' => true,
                'item_edit' => true,
                'item_delete' => true,
                'item_preview' => false,
            ]);

        switch ($type) {
            case self::TYPE_VIEW:
                $optionsResolver->setDefaults(['documents' => []]);
                break;
            case self::TYPE_PREVIEW:
                $options = \array_merge($options, [
                    'item_move' => false,
                    'item_copy' => false,
                    'item_paste' => false,
                    'item_add' => false,
                    'item_edit' => false,
                    'item_delete' => false,
                    'item_preview' => true,
                ]);
                $optionsResolver->setRequired(['field_type', 'structure']);
                break;
            case self::TYPE_REVISION_EDIT:
                $optionsResolver->setRequired(['field_type', 'structure', 'revision']);
                break;
            case self::TYPE_MODAL:
                $optionsResolver
                    ->setDefaults(['id' => null])
                    ->setRequired(['field_type', 'revision', 'level', 'type', 'object']);
                break;
            case self::TYPE_PASTE:
                $optionsResolver->setRequired(['field_type', 'revision', 'structure']);
                break;
            default:
                throw new \Exception('Invalid render type');
        }

        $options = $optionsResolver->resolve($options);
        $options['type'] = $type;

        return new JsonMenuNestedDefinition($this->urlGenerator, $options);
    }
}
