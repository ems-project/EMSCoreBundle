<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnalyzerType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'label' => 'form.analyzer.name',
            ])
            ->add('label', null, [
                'required' => true,
                'label' => 'form.analyzer.label',
            ])
            ->add('options', AnalyzerOptionsType::class, [
                'attr' => [
                    'class' => 'fields-to-display-by-value',
                ],
            ])
            ->add('save', SubmitEmsType::class, [
                'label' => 'form.analyzer.save',
                'attr' => [
                    'class' => 'btn btn-primary pull-right',
                ],
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Analyzer::class,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
