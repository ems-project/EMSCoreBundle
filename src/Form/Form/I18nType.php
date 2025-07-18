<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Form\Field\I18nContentType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class I18nType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', null, [
                'label' => t('field.key', [], 'emsco-core'),
                'required' => true,
                'row_attr' => ['class' => 'col-md-6'],
            ])
            ->add('content', CollectionType::class, [
                'allow_add' => true,
                'allow_delete' => true,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => 'Add translation',
                    'data-lang-remove' => 'Remove translation',
                    'data-entry-remove-class' => 'btn btn-sm btn-danger',
                ],
                'block_prefix' => 'content',
                'entry_type' => I18nContentType::class,
                'entry_options' => [
                    'label' => false,
                    'row_attr' => ['class' => 'col-md-12'],
                ],
                'label' => t('field.translations', [], 'emsco-core'),
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-sm btn-primary'],
                'label' => t('action.save', [], 'emsco-core'),
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => I18n::class,
        ]);
    }
}
