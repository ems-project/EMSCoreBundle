<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
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
                t('color.red', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'red',
                t('color.maroon', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'maroon',
                t('color.fuchsia', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'fuchsia',
                t('color.orange', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'orange',
                t('color.yellow', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'yellow',
                t('color.olive', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'olive',
                t('color.green', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'green',
                t('color.lime', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'lime',
                t('color.teal', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'teal',
                t('color.aqua', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'aqua',
                t('color.light-blue', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'light-blue',
                t('color.blue', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'blue',
                t('color.purple', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'purple',
                t('color.navy', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'navy',
                t('color.black', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'black',
                t('color.gray', [], EMSCoreBundle::TRANS_CORE)->getMessage() => 'gray',
            ],
            'required' => false,
            'choice_translation_domain' => EMSCoreBundle::TRANS_CORE,
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_attr' => fn ($key) => [
                'data-icon' => \sprintf('fa fa-square text-%s', $key ?? 'muted'),
            ],
        ]);
    }
}
