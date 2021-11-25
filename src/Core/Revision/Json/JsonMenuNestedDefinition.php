<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Json;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class JsonMenuNestedDefinition
{
    private AuthorizationCheckerInterface $authorizationChecker;
    private UrlGeneratorInterface $urlGenerator;
    private FieldType $fieldType;
    private JsonMenuNested $menu;
    private ?Revision $revision;
    private ?string $fieldDocument;
    /** @var array<mixed> */
    private array $actions = [];

    public string $id;
    /** @var array<mixed> */
    public array $nodes;
    public bool $isSilentPublish;

    public string $config = '';

    /**
     * @param array<mixed> $options
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        UrlGeneratorInterface $urlGenerator,
        array $options = []
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->urlGenerator = $urlGenerator;
        $this->fieldType = $options['field_type'];
        $this->menu = JsonMenuNested::fromStructure($options['structure']);

        $this->config = \base64_encode(Json::encode([
            'actions' => $options['actions'],
        ]));

        $this->id = $options['id'];
        $this->revision = $options['revision'] ?? null;
        $this->actions = \array_keys($options['actions']);
        $this->fieldDocument = $options['field_document'] ?? null;
        $this->isSilentPublish = $options['silent_publish'] ?? false;

        $this->nodes = $this->buildNodes($options['actions']);
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

        return $this->authorizationChecker->isGranted($fieldMinimumRole);
    }

    private function hasAction(string $action): bool
    {
        return \in_array($action, $this->actions, true);
    }

    /**
     * @param array<mixed> $optionActions
     *
     * @return array<mixed>
     */
    private function buildNodes(array $optionActions): array
    {
        $nodes = [
            'root' => [
                'id' => 'root',
                'name' => 'root',
                'minimumRole' => $this->fieldType->getRestrictionOption('minimum_role'),
                'addNodes' => $this->buildAddNodes($this->fieldType),
                'actions' => $this->buildNodeActions($this->fieldType, 'root', $optionActions),
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
            'minimumRole' => $child->getRestrictionOption('minimum_role'),
            'label' => $child->getDisplayOption('label', $child->getName()),
            'icon' => $child->getDisplayOption('icon'),
            'addNodes' => $this->buildAddNodes($child),
            'actions' => $nodeActions,
        ];

        if (\in_array('add', $nodeActions, true)) {
            $node['urlAdd'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_add', [
                'revision' => $this->getRevision()->getId(),
                'fieldType' => $node['id'],
            ]);
        }
        if (\in_array('edit', $nodeActions, true)) {
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

        if ('root' === $nodeType) {
            $optionActions = \array_filter([
                'add' => $optionActions['add'] ?? ['deny' => ['root']],
                'copy' => $optionActions['copy'] ?? ['deny' => ['root']],
                'paste' => $optionActions['paste'] ?? ['deny' => ['root']],
            ]);
        }

        foreach ($optionActions as $actionName => $settings) {
            if (\in_array($nodeType, $settings['deny'])) {
                continue;
            }
            if (\count($settings['allow']) > 0 && !\in_array($nodeType, $settings['allow'])) {
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
