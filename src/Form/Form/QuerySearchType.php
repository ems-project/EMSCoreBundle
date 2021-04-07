<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\DataTransformer\QuerySearchOptionsTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Subform\QuerySearchOptionsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class QuerySearchType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('name', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('options', QuerySearchOptionsType::class)
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
        $builder->get('options')->addModelTransformer(new QuerySearchOptionsTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuerySearch::class,
            'label_format' => 'form.form.querysearch.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
