<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class FilterOptionsType extends AbstractType
{
    final public const array FIELDS_BY_TYPE = [
        'standard' => [],
        'stop' => [
            'stopwords',
            'ignore_case',
            'remove_trailing',
        ],
        'keyword_marker' => [
            'keywords',
            'keywords_pattern',
            'ignore_case',
        ],
        'stemmer' => [
            'name',
        ],
        'elision' => [
            'articles_case',
            'articles',
        ],
        'asciifolding' => [
            'preserve_original',
        ],
        'synonym' => [
            'synonyms',
        ],
    ];

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'Standard' => 'standard',
                'Stop' => 'stop',
                'Keyword Marker' => 'keyword_marker',
                'Stemmer' => 'stemmer',
                'Elision' => 'elision',
                'ASCII Folding' => 'asciifolding',
                'Synonym' => 'synonym',
            ],
            'attr' => [
                'class' => 'fields-to-display-by-input-value',
            ],
        ])->add('stopwords', ChoiceType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-stop'],
            'required' => false,
            'choices' => [
                'None' => '_none_',
                'Arabic' => '_arabic_',
                'Armenian' => '_armenian_',
                'Basque' => '_basque_',
                'Brazilian' => '_brazilian_',
                'Bulgarian' => '_bulgarian_',
                'Catalan' => '_catalan_',
                'Cjk' => '_cjk_',
                'Czech' => '_czech_',
                'Danish' => '_danish_',
                'Dutch' => '_dutch_',
                'English' => '_english_',
                'Finnish' => '_finnish_',
                'French' => '_french_',
                'Galician' => '_galician_',
                'German' => '_german_',
                'Greek' => '_greek_',
                'Hindi' => '_hindi_',
                'Hungarian' => '_hungarian_',
                'Indonesian' => '_indonesian_',
                'Irish' => '_irish_',
                'Italian' => '_italian_',
                'Latvian' => '_latvian_',
                'Lithuanian' => '_lithuanian_',
                'Norwegian' => '_norwegian_',
                'Persian' => '_persian_',
                'Portuguese' => '_portuguese_',
                'Romanian' => '_romanian_',
                'Russian' => '_russian_',
                'Sorani' => '_sorani_',
                'Spanish' => '_spanish_',
                'Swedish' => '_swedish_',
                'Turkish' => '_turkish_',
                'Thai' => '_thai_',
            ],
        ])->add('name', ChoiceType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-stemmer'],
            'required' => false,
            'choices' => [
                'Arabic' => 'arabic',
                'Armenian' => 'armenian',
                'Basque' => 'basque',
                'Brazilian Portuguese' => 'brazilian',
                'Bulgarian' => 'bulgarian',
                'Catalan' => 'catalan',
                'Czech' => 'czech',
                'Danish' => 'danish',
                'Dutch' => 'dutch',
                'Dutch KP' => 'dutch_kp',
                'English' => 'english',
                'English (light)' => 'light_english',
                'English (minimal)' => 'minimal_english',
                'English (possessive)' => 'possessive_english',
                'English (porter2)' => 'porter2',
                'English (lovins)' => 'lovins',
                'Finnish' => 'finnish',
                'Finnish (light)' => 'light_finnish',
                'French' => 'french',
                'French (light)' => 'light_french',
                'French (minimal)' => 'minimal_french',
                'Galician' => 'galician',
                'Galician (minimal)' => 'minimal_galician',
                'German' => 'german',
                'German 2' => 'german2',
                'German (light)' => 'light_german',
                'German (minimal)' => 'minimal_german',
                'Greek' => 'greek',
                'Hindi' => 'hindi',
                'Hungarian' => 'hungarian',
                'Hungarian (light)' => 'light_hungarian',
                'Indonesian' => 'indonesian',
                'Irish' => 'irish',
                'Italian' => 'italian',
                'Italian (light)' => 'light_italian',
                'Kurdish (Sorani)' => 'sorani',
                'Latvian' => 'latvian',
                'Lithuanian' => 'lithuanian',
                'Norwegian (Bokmål)' => 'norwegian',
                'Norwegian (Bokmål)(light)' => 'light_norwegian',
                'Norwegian (Bokmål)(minimal)' => 'minimal_norwegian',
                'Norwegian (Nynorsk)(light)' => 'light_nynorsk',
                'Norwegian (Nynorsk)(minimal)' => 'minimal_nynorsk',
                'Portuguese' => 'portuguese',
                'Portuguese (light)' => 'light_portuguese',
                'Portuguese (minimal)' => 'minimal_portuguese',
                'Portuguese (RSLP)' => 'portuguese_rslp',
                'Romanian' => 'romanian',
                'Russian' => 'russian',
                'Russian (light)' => 'light_russian',
                'Spanish' => 'spanish',
                'Spanish (light)' => 'light_spanish',
                'Swedish' => 'swedish',
                'Swedish (light)' => 'light_swedish',
                'Turkish' => 'turkish',
            ],
        ])->add('keywords', TextareaType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-keyword_marker'],
            'required' => false,
        ])->add('keywords_pattern', TextType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-keyword_marker'],
            'required' => false,
        ])->add('ignore_case', CheckboxType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-stop fields-to-display-for-keyword_marker'],
            'required' => false,
        ])->add('remove_trailing', CheckboxType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-stop'],
            'required' => false,
        ])->add('articles_case', CheckboxType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-elision'],
            'required' => false,
        ])->add('preserve_original', CheckboxType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-asciifolding'],
            'required' => false,
        ])->add('articles', TextareaType::class, [
            'attr' => ['class' => 'filter_option fields-to-display-for fields-to-display-for-elision'],
            'required' => false,
        ])->add('synonyms', CollectionType::class, [
            'entry_type' => TextType::class,
            'attr' => [
                'class' => 'a2lix_lib_sf_collection filter_option fields-to-display-for fields-to-display-for-synonym',
                'data-lang-add' => 'Add synonyms',
                'data-entry-remove-class' => 'btn btn-danger',
            ],
            'entry_options' => [
                'label' => false,
                'row_attr' => [
                    'class' => 'input-group filter-container',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
        ]);

        $textArea2Array = new CallbackTransformer(
            function ($tagsAsArray) {
                if (\is_array($tagsAsArray)) {
                    // transform the array to a string
                    return \implode(', ', $tagsAsArray);
                }

                return $tagsAsArray;
            },
            fn ($tagsAsString) => // transform the string back to an array
\explode(', ', (string) $tagsAsString)
        );

        $builder->get('articles')->addModelTransformer($textArea2Array);
        $builder->get('keywords')->addModelTransformer($textArea2Array);
    }
}
