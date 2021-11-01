<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Export extends AbstractType implements DashboardInterface
{
    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('filename', CodeEditorType::class, [
                'required' => false,
                'attr' => [
                ],
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
                'max-lines' => 5,
                'min-lines' => 5,
            ])
            ->add('fileDisposition', ChoiceType::class, [
                'expanded' => true,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
                'attr' => [
                ],
                'choices' => [
                    'dashboard.export.none' => null,
                    'dashboard.export.attachment' => 'attachment',
                    'dashboard.export.inline' => 'inline',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_format' => 'dashboard.export.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}
