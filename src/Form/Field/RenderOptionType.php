<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RenderOptionType extends ChoiceType
{
    public const EMBED = 'embed';
    public const EXPORT = 'export';
    public const EXTERNALLINK = 'externalLink';
    public const RAW_HTML = 'rawHTML';
    public const NOTIFICATION = 'notification';
    public const JOB = 'job';
    public const PDF = 'pdf';

    /** @var array<string, string> */
    private array $choices = [
        'Embed' => self::EMBED,
        'Export' => self::EXPORT,
        'External link' => self::EXTERNALLINK,
        'Raw HTML' => self::RAW_HTML,
        'Notification' => self::NOTIFICATION,
        'Job' => self::JOB,
        'PDF' => self::PDF,
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'choices' => $this->choices,
            'multiple' => false,
            'expanded' => false,
            'choices_as_values' => null, // to be deprecated in 3.1
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => fn ($value) => $value,
            'choice_attr' => null,
            'preferred_choices' => [],
            'group_by' => null,
            'empty_data' => '',
            'placeholder' => null,
            'error_bubbling' => false,
            'compound' => false,
            // The view data is always a string, even if the "data" option
            // is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class' => null,
            'choice_translation_domain' => true,
        ]);
    }
}
