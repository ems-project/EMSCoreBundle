<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\ChoiceList;

use EMS\CoreBundle\Entity\ChoiceList;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\TranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class ChoiceListType extends AbstractType
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
            ->add('labelTranslations', TranslationsType::class, [
                'label' => t('field.label_translations', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('options', ChoiceListOptionType::class, [
                'label' => t('field.options', [], 'emsco-core'),
                'required' => false,
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
            'data_class' => ChoiceList::class,
            'create' => false,
        ]);
    }
}
