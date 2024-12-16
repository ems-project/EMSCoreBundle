<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Json;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class JsonMenuNestedDefinition
{
    private readonly FieldType $fieldType;
    private readonly JsonMenuNested $menu;
    private readonly ?Revision $revision;
    private readonly ?string $fieldDocument;
    /** @var array<mixed> */
    private readonly array $actionsNames;
    /** @var array<mixed> */
    private readonly array $blocks;
    /** @var array<mixed> */
    private readonly array $context;

    public string $id;
    /** @var array<mixed> */
    public array $nodes;
    public bool $isSilentPublish;
    public string $config;

    /**
     * @param array<mixed> $options
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        array $options = [],
    ) {
        $this->fieldType = $options['field_type'];

        $json = Json::decode($options['structure']);
        $this->menu = isset($json['id']) ? new JsonMenuNested($json) : JsonMenuNested::fromStructure($options['structure']);

        $this->id = $options['id'];
        $this->revision = $options['revision'] ?? null;
        $this->actionsNames = \array_keys($options['actions']);
        $this->fieldDocument = $options['field_document'] ?? null;
        $this->isSilentPublish = $options['silent_publish'] ?? false;
        $this->blocks = $options['blocks'] ?? [];
        $this->context = $options['context'] ?? [];

        $this->nodes = $this->buildNodes($options['actions']);

        $this->config = \base64_encode(Json::encode([
            'actions' => $options['actions'],
            'blocks' => $options['blocks'],
            'context' => $options['context'],
        ]));
    }

    public function isSortable(): bool
    {
        return $this->hasAction('move');
    }

    /**
     * @return array{paste?: string, preview?: string}
     */
    public function getUrls(): array
    {
        $urls = [];

        if ($this->hasAction('paste')) {
            $urls['paste'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_paste', [
                'revision' => $this->getRevision()->getId(),
                'fieldType' => $this->fieldType->getId(),
            ]);
        }
        if ($this->hasAction('preview')) {
            $urls['preview'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_preview', [
                'parentFieldType' => $this->fieldType->getId(),
            ]);
        }
        if ($this->isSilentPublish) {
            $urls['silentPublish'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_silent_publish', [
                'revision' => $this->getRevision()->getId(),
                'fieldType' => $this->fieldType->getId(),
                'field' => $this->fieldDocument,
            ]);
        }

        return $urls;
    }

    public function getMaxDepth(): int
    {
        return $this->fieldType->getRestrictionOption('json_nested_max_depth', 0);
    }

    public function getMenu(): JsonMenuNested
    {
        return $this->menu;
    }

    /**
     * @return array<mixed>
     */
    public function getNodeByName(string $name): array
    {
        foreach ($this->nodes as $node) {
            if ($node['name'] === $name) {
                return $node;
            }
        }

        return [];
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<int, string>
     */
    public function renderBlocks(string $type, array $context): array
    {
        $results = [];

        $contextItem = $context['item'];
        if (!$contextItem instanceof JsonMenuNested) {
            return $results;
        }

        $context = \array_merge($this->context, $context);

        foreach ($this->getBlocks($type) as $block) {
            $blockItemType = $block['item_type'] ?? null;
            if ($contextItem->getType() !== $blockItemType) {
                continue;
            }

            if (isset($block['html'])) {
                $results[] = $this->twig->createTemplate((string) $block['html'])->render($context);
            }
        }

        return $results;
    }

    /**
     * @return iterable<array{'html'?: mixed, 'type': string, 'item_type': ?string}>
     */
    private function getBlocks(string $type): iterable
    {
        foreach ($this->blocks as $block) {
            $blockType = $block['type'] ?? null;
            $blockHtml = $block['html'] ?? null;

            if ($blockType === $type && null !== $blockHtml) {
                yield $block;
            }
        }
    }

    private function getRevision(): Revision
    {
        if (null === $this->revision) {
            throw new \Exception('Revision required!');
        }

        return $this->revision;
    }

    private function isGranted(): bool
    {
        return $this->isGrantedFieldType($this->fieldType);
    }

    private function isGrantedFieldType(FieldType $fieldType): bool
    {
        $fieldMinimumRole = $fieldType->getRestrictionOption('minimum_role');
        if (!\is_string($fieldMinimumRole) || '' === $fieldMinimumRole) {
            return true;
        }

        return $this->authorizationChecker->isGranted($fieldMinimumRole);
    }

    private function hasAction(string $actionName): bool
    {
        return \in_array($actionName, $this->actionsNames, true);
    }

    /**
     * @param array<mixed> $optionActions
     *
     * @return array<mixed>
     */
    private function buildNodes(array $optionActions): array
    {
        $nodes = [
            '_root' => [
                'id' => '_root',
                'name' => '_root',
                'addNodes' => $this->buildAddNodes($this->fieldType),
                'actions' => $this->buildNodeActions($this->fieldType, '_root', $optionActions),
            ],
        ];

        foreach ($this->fieldType->getChildren() as $child) {
            /** @var DateFieldType $type */
            $type = $child->getType();
            if (!$child->getDeleted() && $type::isContainer()) {
                $nodes[$child->getId()] = $this->buildNode($child, $optionActions);
            }
        }

        return $nodes;
    }

    /**
     * @param array<mixed> $optionActions
     *
     * @return array<mixed>
     */
    private function buildNode(FieldType $child, array $optionActions): array
    {
        $nodeActions = $this->buildNodeActions($child, $child->getName(), $optionActions);
        $node = [
            'id' => $child->getId(),
            'name' => $child->getName(),
            'label' => $child->getDisplayOption('label', $child->getName()),
            'icon' => $child->getDisplayOption('icon'),
            'addNodes' => $this->buildAddNodes($child),
            'actions' => $nodeActions,
        ];

        if ($this->hasAction('add')) {
            $node['urlAdd'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_add', [
                'revision' => $this->getRevision()->getId(),
                'fieldType' => $node['id'],
            ]);
        }
        if ($this->hasAction('edit')) {
            $node['urlEdit'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_edit', [
                'revision' => $this->getRevision()->getId(),
                'fieldType' => $node['id'],
            ]);
        }

        return $node;
    }

    /**
     * @param array<mixed> $optionActions
     *
     * @return array<mixed>
     */
    private function buildNodeActions(FieldType $fieldType, string $nodeType, array $optionActions): array
    {
        $resolved = [];

        if (!$this->isGranted() || !$this->isGrantedFieldType($fieldType)) {
            $optionActions = \array_filter(['preview' => $optionActions['preview'] ?? null]);
        }

        if ('_root' === $nodeType) {
            $optionActions = \array_filter([
                'add' => $optionActions['add'] ?? ['deny' => ['_root']],
                'copy' => $optionActions['copy'] ?? ['deny' => ['_root']],
                'paste' => $optionActions['paste'] ?? ['deny' => ['_root']],
            ]);
        }

        foreach ($optionActions as $actionName => $settings) {
            if (\in_array($nodeType, $settings['deny']) || \in_array('all', $settings['deny'])) {
                continue;
            }
            if ((\is_countable($settings['allow']) ? \count($settings['allow']) : 0) > 0 && !\in_array($nodeType, $settings['allow'])) {
                continue;
            }
            if (!\in_array($actionName, ['copy', 'preview']) && null === $this->revision) {
                continue;
            }

            $resolved[] = $actionName;
        }

        return $resolved;
    }

    /**
     * @return string[]
     */
    private function buildAddNodes(FieldType $node): array
    {
        $isLeaf = $node->getRestrictionOption('json_nested_is_leaf', false);
        if (!$this->isGranted() || $isLeaf) {
            return [];
        }

        $types = [];
        $deny = $node->getRestrictionOption('json_nested_deny', []);

        foreach ($this->fieldType->getChildren() as $child) {
            /** @var DateFieldType $dataFieldType */
            $dataFieldType = $child->getType();
            if ($child->getDeleted() || !$dataFieldType::isContainer()) {
                continue;
            }

            if (\in_array($child->getName(), $deny, true) || !$this->isGrantedFieldType($child)) {
                continue;
            }

            $types[] = $child->getName();
        }

        return $types;
    }
}
