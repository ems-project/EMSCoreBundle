<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class CancelType extends LinkType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'label' => t('action.cancel', [], 'emsco-core'),
        ]);
    }

    #[\Override]
    public function getParent(): string
    {
        return LinkType::class;
    }
}
