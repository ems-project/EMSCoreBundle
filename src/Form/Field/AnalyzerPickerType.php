<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
            'analyzer.type.standard' => 'standard',
            'analyzer.type.simple' => 'simple',
            'analyzer.type.whitespace' => 'whitespace',
            'analyzer.type.stop' => 'stop',
            'analyzer.type.keyword' => 'keyword',
            'analyzer.type.version' => 'version',
        ];
        $languages = [
            'analyzer.type.arabic' => 'arabic',
            'analyzer.type.armenian' => 'armenian',
            'analyzer.type.basque' => 'basque',
            'analyzer.type.brazilian' => 'brazilian',
            'analyzer.type.bulgarian' => 'bulgarian',
            'analyzer.type.catalan' => 'catalan',
            'analyzer.type.cjk' => 'cjk',
            'analyzer.type.czech' => 'czech',
            'analyzer.type.danish' => 'danish',
            'analyzer.type.dutch' => 'dutch',
            'analyzer.type.english' => 'english',
            'analyzer.type.finnish' => 'finnish',
            'analyzer.type.french' => 'french',
            'analyzer.type.galician' => 'galician',
            'analyzer.type.german' => 'german',
            'analyzer.type.greek' => 'greek',
            'analyzer.type.hindi' => 'hindi',
            'analyzer.type.hungarian' => 'hungarian',
            'analyzer.type.indonesian' => 'indonesian',
            'analyzer.type.irish' => 'irish',
            'analyzer.type.italian' => 'italian',
            'analyzer.type.latvian' => 'latvian',
            'analyzer.type.lithuanian' => 'lithuanian',
            'analyzer.type.norwegian' => 'norwegian',
            'analyzer.type.persian' => 'persian',
            'analyzer.type.portuguese' => 'portuguese',
            'analyzer.type.romanian' => 'romanian',
            'analyzer.type.russian' => 'russian',
            'analyzer.type.sorani' => 'sorani',
            'analyzer.type.spanish' => 'spanish',
            'analyzer.type.swedish' => 'swedish',
            'analyzer.type.turkish' => 'turkish',
            'analyzer.type.thai' => 'thai',
        ];

        $choices = [
            'analyzer.category.not_defined' => null,
            'analyzer.category.built_in' => $built_in,
            'analyzer.category.languages' => $languages,
            'analyzer.category.customized' => [],
        ];

        foreach ($this->repository->findBy([], ['orderKey' => 'asc']) as $analyzer) {
            $choices['analyzer.category.customized'][$analyzer->getLabel()] = $analyzer->getName();
        }

        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'required' => false,
            'choices' => $choices,
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'label' => 'form.analyzer_picker.label',
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_value' => fn ($value) => $value,
        ]);
    }
}
