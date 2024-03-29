<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Form\Field\I18nContentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class I18nType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', null, ['required' => true])
            ->add(
                'content',
                CollectionType::class,
                [
                    'entry_type' => I18nContentType::class,
                    'allow_add' => true,
                    'label' => false,
                    'delete_empty' => true,
                    'allow_delete' => true,
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => I18n::class,
        ]);
    }
}
