<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RenderOptionType extends ChoiceType
{
    const EMBED = 'embed';
    const EXPORT = 'export';
    const EXTERNALLINK = 'externalLink';
    const NOTIFICATION = 'notification';
    const JOB = 'job';
    const PDF = 'pdf';

    private $choices = [
        'Embed' => self::EMBED,
        'Export' => self::EXPORT,
        'External link' => self::EXTERNALLINK,
        'Notification' => self::NOTIFICATION,
        'Job' => self::JOB,
        'PDF' => self::PDF,
    ];

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => $this->choices,
            'multiple' => false,
            'expanded' => false,
            'choices_as_values' => null, // to be deprecated in 3.1
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => function ($value) {
                return $value;
            },
            'choice_attr' => null,
            'preferred_choices' => [],
            'group_by' => null,
            'empty_data' => '',
            'placeholder' => null,
            'error_bubbling' => false,
            'compound' => null,
            // The view data is always a string, even if the "data" option
            // is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class' => null,
            'choice_translation_domain' => true,
        ]);
    }
}
