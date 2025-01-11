<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Json;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\TemplateWrapper;

final readonly class JsonMenuRenderer implements RuntimeExtensionInterface
{
    public const string TYPE_MODAL = 'modal';
    public const string TYPE_PASTE = 'paste';
    public const string TYPE_SILENT_PUBLISH = 'silent_publish';
    public const string TYPE_VIEW = 'view';
    public const string TYPE_PREVIEW = 'preview';
    public const string TYPE_REVISION_EDIT = 'revision_edit';

    public const string NESTED_TEMPLATE = '/revision/json/json_menu_nested.html.twig';
    private const array ITEM_ACTIONS = ['move', 'copy', 'paste', 'add', 'edit', 'delete', 'preview'];

    public function __construct(
        private Environment $twig,
        private AuthorizationCheckerInterface $authorizationChecker,
        private UrlGeneratorInterface $urlGenerator,
        private ContentTypeRepository $contentTypeRepository,
        private RevisionService $revisionService,
        private string $templateNamespace)
    {
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
    public function generateNestedItem(string $config, array $options): string
    {
        $config = Json::decode(\base64_decode($config));
        $options = \array_merge($options, $config);

        return $this->template()->renderBlock('renderItem', [
            'def' => $this->createDefinition(self::TYPE_MODAL, $options),
            'level' => $options['item_level'],
            'item' => new JsonMenuNested([
                'id' => $options['item_id'],
                'type' => $options['item_type'],
                'label' => $options['item_object']['label'] ?? '',
                'object' => $options['item_object'],
            ]),
        ]);
    }

    /**
     * @param array<mixed> $options
     */
    public function generateNestedPaste(string $config, array $options): string
    {
        $config = Json::decode(\base64_decode($config));
        $options = \array_merge($options, $config);

        return $this->template()->renderBlock('renderPaste', [
            'def' => $this->createDefinition(self::TYPE_PASTE, $options),
        ]);
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    public function generateSilentPublished(string $config, array $options): array
    {
        $config = Json::decode(\base64_decode($config));
        $options = \array_merge($options, $config);

        $def = $this->createDefinition(self::TYPE_SILENT_PUBLISH, $options);

        return [
            'urls' => $def->getUrls(),
            'nodes' => $def->nodes,
        ];
    }

    public function generateAlertOutOfSync(): string
    {
        return $this->template()->renderBlock('alertOutOfSync');
    }

    private function template(): TemplateWrapper
    {
        return $this->twig->load("@$this->templateNamespace".self::NESTED_TEMPLATE);
    }

    /**
     * @param array<mixed> $options
     */
    private function createDefinition(string $type, array $options): JsonMenuNestedDefinition
    {
        $options = $this->revolveOptions($type, $options);

        return new JsonMenuNestedDefinition(
            $this->twig,
            $this->authorizationChecker,
            $this->urlGenerator,
            $options
        );
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private function revolveOptions(string $type, array $options): array
    {
        $optionsResolver = $this->getOptionsResolver();

        switch ($type) {
            case self::TYPE_VIEW:
                return $this->revolveViewOptions($optionsResolver, $options);
            case self::TYPE_PREVIEW:
                $options['actions'] = ['preview' => [], 'copy' => ['deny' => ['_root']]];
                $optionsResolver->setRequired(['structure']);
                break;
            case self::TYPE_REVISION_EDIT:
                $optionsResolver->setRequired(['structure', 'revision']);
                break;
            case self::TYPE_MODAL:
                $optionsResolver->setRequired(['revision', 'item_id', 'item_level', 'item_type', 'item_object']);
                break;
            case self::TYPE_PASTE:
                $optionsResolver->setRequired(['revision', 'structure']);
                break;
            case self::TYPE_SILENT_PUBLISH:
                $optionsResolver
                    ->setDefaults(['silent_publish' => false, 'field_document' => null])
                    ->setRequired(['revision', 'structure']);
                break;
            default:
                throw new \Exception(\sprintf('Invalid render type: "%s"', $type));
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
        $optionsResolver
            ->setRequired(['document', 'field', 'field_document'])
            ->remove(['field_type'])
            ->setDefaults([
                'silent_publish' => true,
                'structure' => null,
            ])
            ->setDefault('field_document', fn (Options $options) => $options['field']);

        $options = $optionsResolver->resolve($options);

        $document = Document::fromArray($options['document']);
        if (null === $options['structure']) {
            $options['structure'] = $document->getValue($options['field_document'], '{}');
        }

        $contentType = $this->contentTypeRepository->findOneBy(['name' => $document->getContentType(), 'deleted' => false]);
        if (!$contentType instanceof ContentType) {
            throw new \Exception(\sprintf('ContentType not found %s', $document->getContentType()));
        }

        $options['field_type'] = $contentType->getFieldType()->findChildByName($options['field']);
        $options['revision'] = $this->revisionService->getCurrentRevisionForDocument($document);

        return $options;
    }

    private function getOptionsResolver(): OptionsResolver
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setRequired(['id', 'field_type'])
            ->setDefaults([
                'revision' => null,
                'structure' => '{}',
                'blocks' => [],
                'context' => [],
            ])
            ->setDefault('actions', function (Options $options) {
                $actions = [];
                foreach (self::ITEM_ACTIONS as $action) {
                    $actions[$action] = ['allow' => [], 'deny' => []];
                }

                return $actions;
            })
            ->setNormalizer('actions', function (Options $options, $value) {
                $actionResolver = new OptionsResolver();
                $actionResolver
                    ->setDefaults(['allow' => [], 'deny' => []])
                    ->setAllowedTypes('allow', ['array'])
                    ->setAllowedTypes('deny', ['array']);

                $actions = [];
                foreach ($value as $key => $valueAction) {
                    if (\is_string($valueAction)) {
                        $actions[$valueAction] = $actionResolver->resolve([]);
                    } else {
                        $actions[$key] = $actionResolver->resolve($valueAction);
                    }
                }

                return $actions;
            })
        ;

        return $optionsResolver;
    }
}
