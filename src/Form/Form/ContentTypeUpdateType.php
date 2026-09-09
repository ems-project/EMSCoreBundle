<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeUpdateType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('json', FileType::class, [
            'label' => t('field.json_file', [], 'emsco-core'),
        ]);
        $builder->add('deleteExitingTemplates', CheckboxType::class, [
            'required' => false,
            'label' => t('option.delete_existing_templates', [], 'emsco-core'),
        ]);
        $builder->add('deleteExitingViews', CheckboxType::class, [
            'required' => false,
            'label' => t('option.delete_existing_views', [], 'emsco-core'),
        ]);
        $builder->add('update', SubmitEmsType::class, [
            'label' => t('action.update_from_json', [], 'emsco-core'),
            'attr' => [
                'class' => 'btn btn-primary',
                'data-testid' => 'btn-action-update',
            ],
            'icon' => 'fa fa-save',
        ]);
        parent::buildForm($builder, $options);
    }
}
