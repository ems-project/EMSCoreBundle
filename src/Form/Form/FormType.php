<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
        /** @var Form $form */
        $form = $options['data'];

        $builder
            ->add('name', TextType::class, [
                'label' => t('field.name', [], 'emsco-core'),
                'required' => true,
                'col' => 4,
            ])
            ->add('label', TextType::class, [
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
                'col' => 4,
            ]);

        if ($options['create'] ?? false) {
            $builder
                ->add('save', SubmitEmsType::class, [
                    'label' => t('action.create', [], 'emsco-core'),
                    'attr' => ['data-testid' => 'btn-action-create'],
                ]);
        } else {
            $builder
                ->add('fieldType', FieldTypeType::class, [
                    'data' => $form->getFieldType(),
                ])
                ->add('save', SubmitEmsType::class, [
                    'attr' => ['data-testid' => 'btn-action-save'],
                    'label' => t('action.save', [], 'emsco-core'),
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
