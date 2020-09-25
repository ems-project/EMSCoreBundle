<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\JsonMenuNestedEditor;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class JsonMenuNestedEditorFieldType extends JsonMenuEditorFieldType
{
    private FormFactoryInterface $formFactory;

    public function __construct(
        FormFactoryInterface $formFactory,
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);

        $this->formFactory = $formFactory;
    }

    public function getLabel()
    {
        return 'JSON menu nested editor field';
    }

    public static function isContainer()
    {
        return true;
    }

    public static function hasMappedChildren()
    {
        return false;
    }

    public function getBlockPrefix()
    {
        return 'json_menu_nested_editor_fieldtype';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $formFactory = $this->formFactory;

        $resolver
            ->setDefault('json_menu_nested_editor', null)
            ->setNormalizer('json_menu_nested_editor', function (Options $options) use ($formFactory) {
                /** @var FieldType $fieldType */
                $fieldType = $options['metadata'];

                return new JsonMenuNestedEditor($fieldType, $formFactory);
            });
    }

    public function generateMapping(FieldType $current, $withPipeline)
    {
        return [$current->getName() => ['type' => 'string']];
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['json_menu_nested_editor'] = $options['json_menu_nested_editor'];
    }
}
