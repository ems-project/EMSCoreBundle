<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\Field\FilterOptionsType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class FilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => t('field.name', [], 'emsco-core'),
                'required' => true,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('label', null, [
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('options', FilterOptionsType::class, [
                'attr' => ['class' => 'fields-to-display-by-value'],
                'label' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary'],
                'label' => t('action.save', [], 'emsco-core'),
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Filter::class,
        ]);
    }
}
