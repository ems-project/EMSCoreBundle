<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;

class AnalyzerType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, array(
                'required' => true,
                'label' => 'form.analyzer.name'
            ))
            ->add('label', null, array(
                'required' => true,
                'label' => 'form.analyzer.label'
            ))
            ->add('options', AnalyzerOptionsType::class, [
            ])
            ->add('save', SubmitEmsType::class, [
                'label' => 'form.analyzer.save',
                'attr' => [
                    'class' => 'btn btn-primary pull-right'
                ],
                'icon' => 'fa fa-save',
            ]);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Analyzer::class,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
        ]);
    }
}
