<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrientationType extends ChoiceType
{
    public const PORTRAIT = 'portrait';
    public const LANDSCAPE = 'landscape';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [
            'orientation.portrait' => self::PORTRAIT,
            'orientation.landscape' => self::LANDSCAPE,
        ];

        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'choices' => $choices,
            'label_format' => 'orientation.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}
