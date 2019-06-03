<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\View;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class KeywordsViewType extends ViewType
{

    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return "Keywords: a view where all properties of kind (such as keyword) are listed on a single page";
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getName()
    {
        return "Keywords";
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder
        ->add('aggsQuery', TextareaType::class, [
                'label' => 'The aggregations Elasticsearch query [Twig]'
        ])
        ->add('template', TextareaType::class, [
                'label' => 'The Twig template used to display each keywords'
        ])
        ->add('pathToBuckets', TextType::class, [
                'label' => 'The twig path to the buckets array'
        ]);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getBlockPrefix()
    {
        return 'keywords_view';
    }
    

    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request)
    {
        
        $searchQuery = [
            'index' => $view->getContentType()->getEnvironment()->getAlias(),
            'type' => $view->getContentType()->getName(),
//             'search_type' => 'count',
            'body' => $view->getOptions()['aggsQuery']
        ];
        
        $retDoc = $this->client->search($searchQuery);
        
        foreach (explode('.', $view->getOptions()['pathToBuckets']) as $attribute) {
            $retDoc = $retDoc[$attribute];
        }
        
        return [
            'keywords' => $retDoc,
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
        ];
    }
}
