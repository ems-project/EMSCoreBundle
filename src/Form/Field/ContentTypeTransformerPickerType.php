<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContentTypeTransformerPickerType extends SelectPickerType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [
                'test' => 'test22',
            ],
            'attr' => [
                'data-live-search' => true,
                'class' => 'content-type-transformer-picker',
            ],
        ]);
    }
}
