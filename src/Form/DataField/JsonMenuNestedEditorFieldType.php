<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class JsonMenuNestedEditorFieldType extends DataFieldType
{
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(
        FormFactoryInterface $formFactory,
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);

        $this->formFactory = $formFactory;
        $this->urlGenerator = $urlGenerator;
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
}
