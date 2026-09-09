<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\DataTransformer\ArrayValuesTransformer;
use EMS\CoreBundle\Repository\FilterRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class AnalyzerOptionsType extends AbstractType
{
    final public const array FIELDS_BY_TYPE = [
        'standard' => [
            'stopwords',
            'max_token_length',
        ],
        'stop' => [
            'stopwords',
        ],
        'pattern' => [
            'stopwords',
            'lowercase',
            'flags',
            'pattern',
        ],
        'fingerprint' => [
            'separator',
            'max_output_size',
            'stopwords',
        ],
        'custom' => [
            'tokenizer',
            'char_filter',
            'filter',
            'position_increment_gap',
        ],
    ];
    private readonly ArrayValuesTransformer $arrayValuesTransformer;

    public function __construct(
        private readonly Registry $doctrine,
        private readonly TranslatorInterface $translator,
    ) {
        $this->arrayValuesTransformer = new ArrayValuesTransformer();
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translator = $this->translator;

        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'Standard' => 'standard',
                'Stop' => 'stop',
                'Pattern' => 'pattern',
                'Fingerprint' => 'fingerprint',
                'Custom' => 'custom',
            ],
            'label' => t('field.type', [], 'emsco-core'),
            'attr' => ['class' => 'fields-to-display-by-input-value'],
            'choice_translation_domain' => false,
        ])->add('tokenizer', ChoiceType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-custom'],
            'required' => false,
            'choices' => [
                'Standard' => 'standard',
                'Letter' => 'letter',
                'Lowercase' => 'lowercase',
                'Whitespace' => 'whitespace',
                'UAX URL Email' => 'uax_url_email',
                'Classic' => 'classic',
                'Thai' => 'thai',
                'N-Gram' => 'ngram',
                'Edge N-Gram' => 'edge_ngram',
                'Keyword' => 'keyword',
                'Pattern' => 'pattern',
                'Path hierarchy' => 'path_hierarchy',
            ],
            'label' => t('field.tokenizer', [], 'emsco-core'),
            'choice_translation_domain' => false,
        ])->add('max_token_length', IntegerType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-standard'],
            'required' => false,
            'label' => t('field.max_token_length', [], 'emsco-core'),
        ])->add('max_output_size', IntegerType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-fingerprint'],
            'required' => false,
            'label' => t('field.max_output_size', [], 'emsco-core'),
        ])->add('lowercase', CheckboxType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-pattern'],
            'required' => false,
            'label' => t('field.lowercase', [], 'emsco-core'),
        ])->add('pattern', TextType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-pattern'],
            'required' => false,
            'label' => t('field.pattern', [], 'emsco-core'),
        ])->add('separator', TextType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-fingerprint'],
            'required' => false,
            'label' => t('field.separator', [], 'emsco-core'),
        ])->add('flags', ChoiceType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-pattern'],
            'required' => false,
            'label' => t('field.flags', [], 'emsco-core'),
            'choices' => [
                'Canon EQ' => 'CANON_EQ',
                'Case insensitive' => 'CASE_INSENSITIVE',
                'Comments' => 'COMMENTS',
                'Dot all' => 'DOTALL',
                'Literal' => 'LITERAL',
                'Multiline' => 'MULTILINE',
                'Unicode case' => 'UNICODE_CASE',
                'Unicode character class' => 'UNICODE_CHARACTER_CLASS',
                'UNIX lines' => 'UNIX_LINES',
            ],
            'choice_translation_domain' => false,
            'multiple' => true,
        ])->add('char_filter', ChoiceType::class, [
            'required' => false,
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-custom'],
            'choice_translation_domain' => false,
            'choices' => [
                'HTML strip' => 'html_strip',
            ],
            'label' => t('field.char_filter', [], 'emsco-core'),
            'multiple' => true,
        ])->add('filter', ChoiceType::class, [
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-custom'],
            'required' => false,
            'label' => t('field.filter', [], 'emsco-core'),
            'choice_translation_domain' => false,
            'choice_loader' => new CallbackChoiceLoader(function () use ($translator) {
                $out = [
                    t('key.build_in', [], 'emsco-core')->trans($translator) => [
                        'Standard' => 'standard',
                        'ASCII folding' => 'asciifolding',
                        'Flatten graph' => 'flatten_graph',
                        'Lowercase' => 'lowercase',
                        'Uppercase' => 'uppercase',
                        'N-gram' => 'nGram',
                        'Edge-N-gram' => 'edgeNGram',
                        'Porter stem' => 'porter_stem',
                        'Stop' => 'stop',
                        'Word delimiter' => 'word_delimiter',
                    ],
                    t('key.customized', [], 'emsco-core')->trans($translator) => [
                    ],
                ];

                /** @var FilterRepository $repository */
                $repository = $this->doctrine->getRepository(Filter::class);
                /** @var Filter $filter */
                foreach ($repository->findAll() as $filter) {
                    $out['Customized'][$filter->getLabel()] = $filter->getName();
                }

                return $out;
            }),
            'multiple' => true,
        ])->add('stopwords', ChoiceType::class, [
            'label' => t('field.stopwords', [], 'emsco-core'),
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-fingerprint fields-to-display-for-standard fields-to-display-for-pattern fields-to-display-for-fingerprint fields-to-display-for-stop'],
            'required' => false,
            'choice_translation_domain' => false,
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
        ])->add('position_increment_gap', IntegerType::class, [
            'label' => t('field.postition_increment_gap', [], 'emsco-core'),
            'attr' => ['class' => 'analyzer_option fields-to-display-for fields-to-display-for-custom'],
            'required' => false,
        ]);
        $builder->get('flags')->addModelTransformer($this->arrayValuesTransformer);
        $builder->get('char_filter')->addModelTransformer($this->arrayValuesTransformer);
        $builder->get('filter')->addModelTransformer($this->arrayValuesTransformer);
    }
}
