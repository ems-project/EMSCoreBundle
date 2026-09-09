<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ColorPickerType extends AbstractType
{
    public function getParent(): string
    {
        return Select2Type::class;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [
                t('color.red', [], 'emsco-core')->getMessage() => 'red',
                t('color.maroon', [], 'emsco-core')->getMessage() => 'maroon',
                t('color.fuchsia', [], 'emsco-core')->getMessage() => 'fuchsia',
                t('color.orange', [], 'emsco-core')->getMessage() => 'orange',
                t('color.yellow', [], 'emsco-core')->getMessage() => 'yellow',
                t('color.olive', [], 'emsco-core')->getMessage() => 'olive',
                t('color.green', [], 'emsco-core')->getMessage() => 'green',
                t('color.lime', [], 'emsco-core')->getMessage() => 'lime',
                t('color.teal', [], 'emsco-core')->getMessage() => 'teal',
                t('color.aqua', [], 'emsco-core')->getMessage() => 'aqua',
                t('color.light-blue', [], 'emsco-core')->getMessage() => 'light-blue',
                t('color.blue', [], 'emsco-core')->getMessage() => 'blue',
                t('color.purple', [], 'emsco-core')->getMessage() => 'purple',
                t('color.navy', [], 'emsco-core')->getMessage() => 'navy',
                t('color.black', [], 'emsco-core')->getMessage() => 'black',
                t('color.gray', [], 'emsco-core')->getMessage() => 'gray',
            ],
            'required' => false,
            'choice_translation_domain' => 'emsco-core',
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_attr' => fn ($key) => [
                'data-icon' => \sprintf('fa fa-square text-%s', $key ?? 'muted'),
            ],
        ]);
    }
}
