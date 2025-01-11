<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class I18nContentType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('locale', TextType::class, [
            'required' => true,
        ])
        ->add('text', TextareaType::class, [
            'required' => true,
            'attr' => [
                'rows' => 4,
            ],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'i18n_content';
    }
}
