<?php

namespace EMS\CoreBundle\Form\Field;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnalyzerPickerType extends SelectPickerType
{
    private Registry $doctrine;

    public function __construct(Registry $doctrine)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $built_in = [
                'Standard' => 'standard',
                'Simple' => 'simple',
                'Whitespace' => 'whitespace',
                'Stop' => 'stop',
                'Keyword' => 'keyword',
        ];
        $languages = [
                'Arabic' => 'arabic',
                'Armenian' => 'armenian',
                'Basque' => 'basque',
                'Brazilian' => 'brazilian',
                'Bulgarian' => 'bulgarian',
                'Catalan' => 'catalan',
                'Cjk' => 'cjk',
                'Czech' => 'czech',
                'Danish' => 'danish',
                'Dutch' => 'dutch',
                'English' => 'english',
                'Finnish' => 'finnish',
                'French' => 'french',
                'Galician' => 'galician',
                'German' => 'german',
                'Greek' => 'greek',
                'Hindi' => 'hindi',
                'Hungarian' => 'hungarian',
                'Indonesian' => 'indonesian',
                'Irish' => 'irish',
                'Italian' => 'italian',
                'Latvian' => 'latvian',
                'Lithuanian' => 'lithuanian',
                'Norwegian' => 'norwegian',
                'Persian' => 'persian',
                'Portuguese' => 'portuguese',
                'Romanian' => 'romanian',
                'Russian' => 'russian',
                'Sorani' => 'sorani',
                'Spanish' => 'spanish',
                'Swedish' => 'swedish',
                'Turkish' => 'turkish',
                'Thai' => 'thai',
        ];

        $choices = [
                'Not defined' => null,
                'Built-in' => $built_in,
                'Languages' => $languages,
                'Customized' => [
                ],
        ];

        /** @var AnalyzerRepository $repository */
        $repository = $this->doctrine->getRepository(Analyzer::class);
        /** @var Analyzer $analyzer */
        foreach ($repository->findAll() as $analyzer) {
            $choices['Customized'][$analyzer->getLabel()] = $analyzer->getName();
        }

        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'required' => false,
            'choices' => $choices,
            'attr' => [
                    'data-live-search' => true,
            ],
            'choice_attr' => function ($category, $key, $index) {
                return [
                        'data-content' => $this->humanize($key),
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ]);
    }
}
