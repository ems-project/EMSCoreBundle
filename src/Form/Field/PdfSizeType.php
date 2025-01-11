<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Dompdf\Adapter\CPDF;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PdfSizeType extends ChoiceType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];
        foreach (CPDF::$PAPER_SIZES as $id => $size) {
            $choices[\sprintf('pdf_size.%s', Encoder::webalize($id))] = $id;
        }

        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'choices' => $choices,
            'label_format' => 'pdf_size.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}
