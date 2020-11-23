<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * It's a coumpound field for field specific migration option.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class MigrationOptionsType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('protected', CheckboxType::class, [
                'required' => false,
        ])
        ;
    }
}
