<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\JsonMenuNestedEditor;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class JsonMenuNestedEditorFieldType extends DataFieldType
{
    /** @var FormFactoryInterface */
    private $formFactory;

    public function __construct(
        FormFactoryInterface $formFactory,
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);

        $this->formFactory = $formFactory;
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

        $formFactory = $this->formFactory;
        $resolver
            ->setDefault('json_menu_nested_editor', null)
            ->setDefault('icon', null)
            ->setNormalizer('json_menu_nested_editor', function (Options $options) use ($formFactory) {
                /** @var FieldType $fieldType */
                $fieldType = $options['metadata'];

                return new JsonMenuNestedEditor($fieldType, $formFactory);
            });
    }

    /**
     * @param bool $withPipeline
     *
     * @return array<string, array{'type': 'string'}>
     */
    public function generateMapping(FieldType $current, $withPipeline): array
    {
        return [$current->getName() => ['type' => 'string']];
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);

        $builder->get('options')->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        $builder->get('options')->get('displayOptions')->add('icon', IconPickerType::class, [
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

        $disabled = true;
        if ($options['metadata'] instanceof FieldType) {
            $disabled = !$this->authorizationChecker->isGranted($options['metadata']->getMinimumRole());
        }

        $view->vars['disabled'] = $disabled;
        $view->vars['json_menu_nested_editor'] = $options['json_menu_nested_editor'];
    }
}
