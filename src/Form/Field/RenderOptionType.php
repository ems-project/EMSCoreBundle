<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RenderOptionType extends ChoiceType
{
    final public const string EMBED = 'embed';
    final public const string EXPORT = 'export';
    final public const string IMPORT = 'import';
    final public const string EXTERNALLINK = 'externalLink';
    final public const string RAW_HTML = 'rawHTML';
    final public const string NOTIFICATION = 'notification';
    final public const string JOB = 'job';
    final public const string PDF = 'pdf';

    /** @var array<string, string> */
    private array $choices = [
        'Embed' => self::EMBED,
        'Export' => self::EXPORT,
        'Import' => self::IMPORT,
        'External link' => self::EXTERNALLINK,
        'Raw HTML' => self::RAW_HTML,
        'Notification' => self::NOTIFICATION,
        'Job' => self::JOB,
        'PDF' => self::PDF,
    ];

    #[\Override]
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
