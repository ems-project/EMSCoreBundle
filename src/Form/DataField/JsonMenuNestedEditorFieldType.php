<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use Doctrine\Common\Collections\Collection;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\UpdateRevisionReferersEvent;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Type;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class JsonMenuNestedEditorFieldType extends DataFieldType
{
    protected EventDispatcherInterface $dispatcher;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->dispatcher = $dispatcher;
    }

    public function getLabel(): string
    {
        return 'JSON menu nested editor field';
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public static function isContainer(): bool
    {
        return true;
    }

    public static function hasMappedChildren(): bool
    {
        return false;
    }

    public function getBlockPrefix(): string
    {
        return 'json_menu_nested_editor_fieldtype';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'icon' => null,
                'json_menu_nested_modal' => true,
            ]);
    }

    /**
     * @return array<string, array{type: string}>
     */
    public function generateMapping(FieldType $current): array
    {
        return [$current->getName() => ['type' => 'text']];
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);

        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        }

        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'required' => false,
        ]);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<mixed>                 $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        /** @var Revision $revision */
        $revision = $form->getRoot()->getData();
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];

        $view->vars['disabled'] = !$this->authorizationChecker->isGranted($fieldType->getMinimumRole());
        $view->vars['revision'] = $revision;
    }

    /**
     * {@inheritDoc}
     */
    public function postFinalizeTreatment(Revision $revision, DataField $dataField, ?array $previousData): ?array
    {
        $fieldType = $dataField->giveFieldType();

        $menuCurrent = JsonMenuNested::fromStructure(Type::string($dataField->getRawData()));
        $menuPrevious = JsonMenuNested::fromStructure($previousData[$fieldType->getName()] ?? '{}');

        if ($menuCurrent->toArrayStructure() === $menuPrevious->toArrayStructure()) {
            return $previousData;
        }

        $nodeTypeDataLinksUpdateReferrer = $this->getNodeTypeDataLinksUpdateReferrer($fieldType);

        foreach ($nodeTypeDataLinksUpdateReferrer as $nodeType => $dataLinksUpdateReferrer) {
            $callbackNodeTypes = fn (JsonMenuNested $i) => $i->getType() === $nodeType;
            $menuCurrentItems = $menuCurrent->filterChildren($callbackNodeTypes);
            $menuPreviousItems = $menuPrevious->filterChildren($callbackNodeTypes);

            $menuUpdatedItems = $menuCurrentItems->diffChildren($menuPreviousItems);
            $menuRemovedItems = $menuPreviousItems->diffChildren($menuCurrentItems);

            if (0 === \count($menuUpdatedItems) && 0 === \count($menuRemovedItems)) {
                continue;
            }

            foreach ($dataLinksUpdateReferrer as $dataLink => $updateReferrer) {
                $callbackItemHasProperty = fn (JsonMenuNested $i) => $i->getObject()[$dataLink] ?? null;

                /** @var string[] $referrersUpdate */
                $referrersUpdate = \array_filter($menuUpdatedItems->getChildren($callbackItemHasProperty));
                /** @var string[] $referrersRemove */
                $referrersRemove = \array_filter($menuRemovedItems->getChildren($callbackItemHasProperty));

                $event = new UpdateRevisionReferersEvent($revision, $updateReferrer, $referrersRemove, $referrersUpdate);
                $this->dispatcher->dispatch($event);
            }
        }

        return $previousData;
    }

    /**
     * Key node type, value array with key data link field type name, value update referrers.
     *
     * @return array<string, array<string, string>>
     */
    private function getNodeTypeDataLinksUpdateReferrer(FieldType $fieldType): array
    {
        /** @var Collection<int, FieldType> $children */
        $children = $fieldType->getChildren()->filter(fn (FieldType $f) => !$f->getDeleted());

        $nodeDataLinksUpdateReferrers = [];
        foreach ($children as $node) {
            $nodeChildren = $node->getChildren(true);
            if ($nodeChildren->isEmpty()) {
                continue;
            }

            /** @var Collection<int, FieldType> $dataLinkFieldTypes */
            $dataLinkFieldTypes = $nodeChildren->filter(fn (FieldType $f) => DataLinkFieldType::class === $f->getType());
            foreach ($dataLinkFieldTypes as $dataLinkFieldType) {
                if ($updateReferrersField = $dataLinkFieldType->getExtraOption('updateReferersField', false)) {
                    $nodeDataLinksUpdateReferrers[$node->getName()][$dataLinkFieldType->getName()] = $updateReferrersField;
                }
            }
        }

        return $nodeDataLinksUpdateReferrers;
    }
}
