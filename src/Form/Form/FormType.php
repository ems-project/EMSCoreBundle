<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class FormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
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
                'label' => t('field.name', [], 'emsco-core'),
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('label', null, [
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ]);

        if ($options['create'] ?? false) {
            $builder
                ->add('create', SubmitEmsType::class, [
                    'label' => t('action.create', [], 'emsco-core'),
                    'attr' => [
                        'class' => 'btn btn-primary btn-sm ',
                        'data-testid' => 'btn-action-create',
                    ],
                    'icon' => 'fa fa-save',
                ]);
        } else {
            $builder
                ->add('fieldType', FieldTypeType::class, [
                    'data' => $form->getFieldType(),
                ])
                ->add('save', SubmitEmsType::class, [
                    'label' => t('action.save', [], 'emsco-core'),
                    'attr' => [
                        'class' => 'btn btn-primary btn-sm ',
                        'data-testid' => 'btn-action-save',
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
            'create' => false,
        ]);
    }
}
