<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Repository\AnalyzerRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class AnalyzerPickerType extends Select2Type
{
    public function __construct(private readonly AnalyzerRepository $repository)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $built_in = [
            'Standard' => 'standard',
            'Simple' => 'simple',
            'Whitespace' => 'whitespace',
            'Stop' => 'stop',
            'Keyword' => 'keyword',
            'Version' => 'version',
        ];
        $languages = [
            'Arabic' => 'arabic',
            'Armenian' => 'armenian',
            'Basque' => 'basque',
            'Brazilian' => 'brazilian',
            'Bulgarian' => 'bulgarian',
            'Catalan' => 'catalan',
            'CJK' => 'cjk',
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
            'Customized' => [],
        ];

        foreach ($this->repository->findBy([], ['orderKey' => 'asc']) as $analyzer) {
            $choices['Custom'][$analyzer->getLabel()] = $analyzer->getName();
        }

        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'required' => false,
            'choices' => $choices,
            'label' => t('field.analyzer', [], 'emsco-core'),
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_value' => fn ($value) => $value,
            'translation_domain' => false,
        ]);
    }
}
