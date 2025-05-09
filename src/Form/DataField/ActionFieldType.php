<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActionFieldType extends DataFieldType
{
    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm
            ->remove('mappingOptions')
            ->remove('migrationOptions');

        $displayOptions = $optionsForm->get('displayOptions');
        $displayOptions->add('icon', IconPickerType::class, ['required' => false]);

        $restrictionOptions = $optionsForm->get('restrictionOptions');
        $restrictionOptions->remove('mandatory')->remove('mandatory_if');
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['icon' => null]);
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        /** @var DataField $dataField */
        $dataField = $view->vars['data'];
        /** @var Revision $revision */
        $revision = $form->getRoot()->getData();

        $view->vars['fieldId'] = $dataField->giveFieldType()->getId();
        $view->vars['revisionId'] = $revision->getId();
    }

    public function getDefaultOptions(string $name): array
    {
        $defaultOptions = parent::getDefaultOptions($name);

        $defaultOptions['extraOptions']['config'] = Json::encode([
            'type' => null,
            'input' => [],
            'output' => [],
        ], true);

        return $defaultOptions;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'action';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-cog';
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Action field';
    }
}
