<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class FileDispositionType extends ChoiceType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'expanded' => true,
            'choices' => [
                null,
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                ResponseHeaderBag::DISPOSITION_INLINE,
            ],
            'label' => t('field.file.disposition', [], 'emsco-core'),
            'choice_label' => fn (?string $value): TranslatableMessage => match ($value) {
                null => t('key.not_defined', [], 'emsco-core'),
                ResponseHeaderBag::DISPOSITION_ATTACHMENT => t('field.file.disposition_attachment', [], 'emsco-core'),
                ResponseHeaderBag::DISPOSITION_INLINE => t('field.file.disposition_inline', [], 'emsco-core'),
                default => new TranslatableMessage($value)
            },
        ]);
    }
}
