<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class TranslationType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', ChoiceType::class, [
                'label' => t('field.locale', [], 'emsco-core'),
                'col' => 4,
                'required' => true,
                'choices' => \array_flip(Locales::getNames()),
                'choice_translation_domain' => false,
            ])
            ->add('label', $options['label_type'], [
                'label' => t('field.label', [], 'emsco-core'),
                'col' => 8,
                'required' => true,
            ]);
        if ($options['with_advanced_options']) {
            $builder->add('gender', ChoiceType::class, [
                'label' => t('field.gender', [], 'emsco-core'),
                'col' => 4,
                'required' => false,
                'choices' => [
                    t('key.gender.male', [], 'emsco-core')->getMessage() => 'male',
                    t('key.gender.female', [], 'emsco-core')->getMessage() => 'female',
                    t('key.gender.neutral', [], 'emsco-core')->getMessage() => 'neutral',
                ],
                'choice_translation_domain' => 'emsco-core',
            ])->add('number', ChoiceType::class, [
                'label' => t('field.number', [], 'emsco-core'),
                'col' => 4,
                'required' => false,
                'choices' => [
                    t('key.singular', [], 'emsco-core')->getMessage() => 'singular',
                    t('key.plural', [], 'emsco-core')->getMessage() => 'plural',
                ],
                'choice_translation_domain' => 'emsco-core',
            ])->add('elision', ChoiceType::class, [
                'label' => t('field.elision', [], 'emsco-core'),
                'col' => 4,
                'required' => false,
                'choices' => [
                    t('key.false', [], 'emsco-core')->getMessage() => 'false',
                    t('key.true', [], 'emsco-core')->getMessage() => 'true',
                ],
                'choice_translation_domain' => 'emsco-core',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_type' => TextType::class,
            'with_advanced_options' => false,
        ]);
    }
}
