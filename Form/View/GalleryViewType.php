<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Form\View\ViewType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Form\Field\AssetType;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class GalleryViewType extends ViewType {
    
    /**@var SearchService */
    private $searchService;
    
    public function __construct($formFactory, $twig, $client, SearchService $searchService){
        parent::__construct($formFactory, $twig, $client);
        $this->searchService = $searchService;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel(){
        return "Gallery: a view where you can browse images";
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getName(){
        return "Gallery";
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        parent::buildForm($builder, $options);
        $builder->add ( 'imageField', TextType::class, [
        ] )->add ( 'sourceFields', TextType::class, [
                'required' => false,
        ] )->add ( 'imageAltFields', TextType::class, [
                'required' => false,
        ] )->add ( 'missingImageHash', TextType::class, [
                'required' => false,
        ] )->add ( 'thumbnailAssetConfigIdentifier', TextType::class, [
                'required' => false,
        ] )->add ( 'imageAssetConfigIdentifier', TextType::class, [
                'required' => false,
        ] );
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getBlockPrefix() {
        return 'gallery_view';
    }
    

    /**
     *
     * {@inheritdoc}
     *
     */
    public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request) {
        

        $search = new Search();
        if($request->query->get('search_form', false) === false) {
            $search->getFilters()[0]->setField($view->getOptions()['imageField'].'.sha1');
            $search->getFilters()[0]->setBooleanClause('must');
        }
        
        $form = $formFactoty->create(SearchFormType::class, $search, [
                'method' => 'GET',
                'light' => true,
        ]);
        
        $form->handleRequest($request);
        
        $search = $form->getData();
        
        $body = $this->searchService->generateSearchBody($search);
        
        
        $searchQuery = [
                'index' => $view->getContentType()->getEnvironment()->getAlias(),
                'type' => $view->getContentType()->getName(),
                "from" => 0,
                "size" => 1000,
                "body" => $body,
        ];
        
        if(isset($view->getOptions()['sourceFields'])) {
            $searchQuery['_source'] = $view->getOptions()['sourceFields'];
        }
        
        $data = $this->client->search($searchQuery);
        
        return [
            'view' => $view,
            'field' => $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['imageField']),
            'imageAssetConfigIdentifier' => $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['imageAssetConfigIdentifier']),
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'form' => $form->createView(),
            'data' => $data,
        ];
    }
    
}