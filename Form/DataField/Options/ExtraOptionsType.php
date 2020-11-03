<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * It's a coumpound field for field specific extra option.
 */
class ExtraOptionsType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('extra', TextareaType::class, [
                'attr' => [
                    'rows' => 8,
                ],
                'required' => false,
        ])->add('postProcessing', CodeEditorType::class, [
                'required' => false,
        ]);
    }
}
