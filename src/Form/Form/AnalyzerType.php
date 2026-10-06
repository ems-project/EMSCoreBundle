<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class AnalyzerType extends AbstractType
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
            ])
            ->add('label', null, [
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
            ])
            ->add('options', AnalyzerOptionsType::class, [
                'attr' => ['class' => 'fields-to-display-by-value'],
                'label' => false,
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => ['data-testid' => 'btn-action-save'],
                'label' => t('action.save', [], 'emsco-core'),
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Analyzer::class,
        ]);
    }
}
