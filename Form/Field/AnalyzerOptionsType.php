<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Entity\Filter;

class AnalyzerOptionsType extends AbstractType
{


    const FIELDS_BY_TYPE = [
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


    /**@var Registry $doctrine */
    private $doctrine;

    public function __construct(Registry $doctrine)
    {
//'@doctrine'
        $this->doctrine = $doctrine;
    }


    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'form.analyzer.type.standard' => 'standard',
                'form.analyzer.type.stop' => 'stop',
                'form.analyzer.type.pattern' => 'pattern',
                'form.analyzer.type.fingerprint' => 'fingerprint',
                'form.analyzer.type.custom' => 'custom',
            ],
            'label' => 'form.analyzer.type'
        ])->add('tokenizer', ChoiceType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'choices' => [
                'form.analyzer.tokenizer.standard' => 'standard',
                'form.analyzer.tokenizer.letter' => 'letter',
                'form.analyzer.tokenizer.lowercase' => 'lowercase',
                'form.analyzer.tokenizer.whitespace' => 'whitespace',
                'form.analyzer.tokenizer.uax_url_email' => 'uax_url_email',
                'form.analyzer.tokenizer.classic' => 'classic',
                'form.analyzer.tokenizer.thai' => 'thai',
                'form.analyzer.tokenizer.ngram' => 'ngram',
                'form.analyzer.tokenizer.edge_ngram' => 'edge_ngram',
                'form.analyzer.tokenizer.keyword' => 'keyword',
                'form.analyzer.tokenizer.pattern' => 'pattern',
                'form.analyzer.tokenizer.path_hierarchy' => 'path_hierarchy',
            ],
            'label' => 'form.analyzer.tokenizer',
        ])->add('max_token_length', IntegerType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.max_token_length',
        ])->add('max_output_size', IntegerType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.max_output_size',
        ])->add('lowercase', CheckboxType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.lowercase',
        ])->add('pattern', TextType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.pattern',
        ])->add('separator', TextType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.separator',
        ])->add('flags', ChoiceType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.flags',
            'choices' => [
                'form.analyzer.flags.canon_eq' => 'CANON_EQ',
                'form.analyzer.flags.case_insensitive' => 'CASE_INSENSITIVE',
                'form.analyzer.flags.comments' => 'COMMENTS',
                'form.analyzer.flags.dotall' => 'DOTALL',
                'form.analyzer.flags.literal' => 'LITERAL',
                'form.analyzer.flags.multiline' => 'MULTILINE',
                'form.analyzer.flags.unicode_case' => 'UNICODE_CASE',
                'form.analyzer.flags.unicode_character_class' => 'UNICODE_CHARACTER_CLASS',
                'form.analyzer.flags.unix_lines' => 'UNIX_LINES',
            ],
            'multiple' => true,
        ])->add('char_filter', ChoiceType::class, [
            'required' => false,
            'attr' => ['class' => 'analyzer_option'],
            'choices' => [
                'form.analyzer.char_filter.html_strip' => 'html_strip',
            ],
            'label' => 'form.analyzer.char_filter',
            'multiple' => true,
        ])->add('filter', ChoiceType::class, [
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'label' => 'form.analyzer.filter',
            'choice_loader' => new CallbackChoiceLoader(function () {
                $out = [
                    'form.analyzer.filter.built_in' => [
                        'form.analyzer.filter.built_in.standard' => 'standard',
                        'form.analyzer.filter.built_in.asciifolding' => 'asciifolding',
                        'form.analyzer.filter.built_in.flatten_graph' => 'flatten_graph',
                        'form.analyzer.filter.built_in.lowercase' => 'lowercase',
                        'form.analyzer.filter.built_in.uppercase' => 'uppercase',
                        'form.analyzer.filter.built_in.n_gram' => 'nGram',
                        'form.analyzer.filter.built_in.edge_n_gram' => 'edgeNGram',
                        'form.analyzer.filter.built_in.porter_stem' => 'porter_stem',
                        'form.analyzer.filter.built_in.stop' => 'stop',
                        'form.analyzer.filter.built_in.word_delimiter' => 'word_delimiter',
                    ],
                    'form.analyzer.filter.customized' => [

                    ],
                ];

                /**@var FilterRepository $repository */
                $repository = $this->doctrine->getRepository('EMSCoreBundle:Filter');
                /**@var Filter $filter */
                foreach ($repository->findAll() as $filter) {
                    $out['Customized'][$filter->getLabel()] = $filter->getName();
                }

                return $out;
            }),
            'multiple' => true,
        ])->add('stopwords', ChoiceType::class, [
            'label' => 'form.analyzer.stopwords',
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
            'choices' => [
                'form.analyzer.stopwords._none_' => '_none_',
                'form.analyzer.stopwords._arabic_' => '_arabic_',
                'form.analyzer.stopwords._armenian_' => '_armenian_',
                'form.analyzer.stopwords._basque_' => '_basque_',
                'form.analyzer.stopwords._brazilian_' => '_brazilian_',
                'form.analyzer.stopwords._bulgarian_' => '_bulgarian_',
                'form.analyzer.stopwords._catalan_' => '_catalan_',
                'form.analyzer.stopwords._cjk_' => '_cjk_',
                'form.analyzer.stopwords._czech_' => '_czech_',
                'form.analyzer.stopwords._danish_' => '_danish_',
                'form.analyzer.stopwords._dutch_' => '_dutch_',
                'form.analyzer.stopwords._english_' => '_english_',
                'form.analyzer.stopwords._finnish_' => '_finnish_',
                'form.analyzer.stopwords._french_' => '_french_',
                'form.analyzer.stopwords._galician_' => '_galician_',
                'form.analyzer.stopwords._german_' => '_german_',
                'form.analyzer.stopwords._greek_' => '_greek_',
                'form.analyzer.stopwords._hindi_' => '_hindi_',
                'form.analyzer.stopwords._hungarian_' => '_hungarian_',
                'form.analyzer.stopwords._indonesian_' => '_indonesian_',
                'form.analyzer.stopwords._irish_' => '_irish_',
                'form.analyzer.stopwords._italian_' => '_italian_',
                'form.analyzer.stopwords._latvian_' => '_latvian_',
                'form.analyzer.stopwords._lithuanian_' => '_lithuanian_',
                'form.analyzer.stopwords._norwegian_' => '_norwegian_',
                'form.analyzer.stopwords._persian_' => '_persian_',
                'form.analyzer.stopwords._portuguese_' => '_portuguese_',
                'form.analyzer.stopwords._romanian_' => '_romanian_',
                'form.analyzer.stopwords._russian_' => '_russian_',
                'form.analyzer.stopwords._sorani_' => '_sorani_',
                'form.analyzer.stopwords._spanish_' => '_spanish_',
                'form.analyzer.stopwords._swedish_' => '_swedish_',
                'form.analyzer.stopwords._turkish_' => '_turkish_',
                'form.analyzer.stopwords._thai_' => '_thai_',
            ],
        ])->add('position_increment_gap', IntegerType::class, [
            'label' => 'form.analyzer.position_increment_gap',
            'attr' => ['class' => 'analyzer_option'],
            'required' => false,
        ]);
    }
}
