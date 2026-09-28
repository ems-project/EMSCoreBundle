<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class I18nContentType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('locale', TextType::class, [
            'label' => t('field.locale', [], 'emsco-core'),
            'required' => true,
            'row_attr' => ['class' => 'col-md-2'],
        ])
        ->add('text', TextareaType::class, [
            'label' => t('field.text', [], 'emsco-core'),
            'attr' => ['rows' => 4],
            'required' => true,
            'row_attr' => ['class' => 'col-md-10'],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'i18n_content';
    }
}
