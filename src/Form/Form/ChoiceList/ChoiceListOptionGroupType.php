<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\ChoiceList;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class ChoiceListOptionGroupType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => t('field.name', [], 'emsco-core'),
            ])
            ->add('label', TextType::class, [
                'required' => true,
                'label' => t('field.label', [], 'emsco-core'),
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($options): void {
            $data = $event->getData();
            $data = \is_array($data) ? $data : [];
            $labels = \is_array($data['labels'] ?? null) ? $data['labels'] : [];
            $data['labels'] = \array_replace(\array_fill_keys($options['locales'], ''), $labels);
            $event->setData($data);

            $form = $event->getForm();
            $form->add('labels', FormType::class, ['label' => false]);
            foreach ($options['locales'] as $locale) {
                $form->get('labels')->add($locale, TextType::class, [
                    'label' => Locales::getName($locale),
                    'required' => false,
                    'translation_domain' => false,
                ]);
            }
        });
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'locales' => [],
        ]);
        $resolver->setAllowedTypes('locales', 'string[]');
    }
}
