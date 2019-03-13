<?php

namespace EMS\CoreBundle\Form\Field;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Entity\Analyzer;

class AnalyzerPickerType extends SelectPickerType
{
    
    
    /**@var Registry $doctrine */
    private $doctrine;
    
    public function __construct(Registry $doctrine)
    {
//'@doctrine'
        parent::__construct();
        $this->doctrine = $doctrine;
    }

    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
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
                'Thai' => 'thai'
        ];
        
        $choices = [
                'Not defined' => null,
                'Built-in' => $built_in,
                'Languages' => $languages,
                'Customized' =>[
                ],
        ];
        
        
        
        
        /**@var AnalyzerRepository $repository*/
        $repository = $this->doctrine->getRepository('EMSCoreBundle:Analyzer');
        /**@var Analyzer $analyzer*/
        foreach ($repository->findAll() as $analyzer) {
            $choices['Customized'][$analyzer->getLabel()] = $analyzer->getName();
        }
        
        
        parent::configureOptions($resolver);
        $resolver->setDefaults(array(
            'required' => false,
            'choices' =>$choices,
            'attr' => [
                    'data-live-search' => true
            ],
            'choice_attr' => function ($category, $key, $index) {
                return [
                        'data-content' => $this->humanize($key)
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ));
    }
}
