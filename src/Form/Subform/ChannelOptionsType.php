<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Subform;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ChannelOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entryPath', null, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('attributes', CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/json',
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('searchConfig', CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/json',
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_format' => 'form.subform.channel.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
