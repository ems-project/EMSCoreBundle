<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FileDispositionType extends ChoiceType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'expanded' => true,
            'choices' => [
                'file_disposition.not-defined' => null,
                'file_disposition.attachment' => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'file_disposition.inline' => ResponseHeaderBag::DISPOSITION_INLINE,
            ],
            'label_format' => 'file_disposition.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}
