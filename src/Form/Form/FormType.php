<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FormType extends AbstractType
{
    public function __construct()
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $form = $options['data'] ?? null;
        if (!$form instanceof Form) {
            throw new \RuntimeException('Unexpected data type');
        }

        $builder
            ->add('name', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('label', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ]);

        if ($options['create'] ?? false) {
            $builder
                ->add('create', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn btn-primary btn-sm ',
                    ],
                    'icon' => 'fa fa-save',
                ]);
        } else {
            $builder->add('fieldType', FieldTypeType::class, [
                'data' => $form->getFieldType(),
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Form::class,
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'create' => false,
        ]);
    }
}
