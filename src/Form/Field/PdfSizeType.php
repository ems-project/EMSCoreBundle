<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Dompdf\Adapter\CPDF;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class PdfSizeType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];
        foreach (CPDF::$PAPER_SIZES as $id => $size) {
            $choices[\ucfirst((string) $id)] = $id;
        }

        $resolver->setDefaults([
            'label' => t('field.pdf_size', [], 'emsco-core'),
            'choices' => $choices,
            'choice_translation_domain' => false,
        ]);
    }
}
