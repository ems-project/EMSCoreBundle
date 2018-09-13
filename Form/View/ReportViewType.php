<?php

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\View\ViewType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class ReportViewType extends ViewType {

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return "Report: perform an elasticsearch query and generate a report with a twig template";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getName(){
		return "Report";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		parent::buildForm($builder, $options);
		$builder
		->add ( 'body', CodeEditorType::class, [
				'label' => 'The Elasticsearch body query [JSON Twig]',
				'attr' => [
				],
				'slug' => 'report_query',
		] )
		->add ( 'size', IntegerType::class, [
				'label' => 'Limit the result to the x first results',
		] )
		->add ( 'template', CodeEditorType::class, [
				'label' => 'The Twig template used to display each keywords',
				'attr' => [
				],
				'slug' => 'report_template',
        ] )
        ->add ( 'header', CodeEditorType::class, [
                'label' => 'The HTML template included at the end of the header',
                'attr' => [
                ],
		] )
		->add ( 'javascript', CodeEditorType::class, [
				'label' => 'The HTML template included at the end of the page (after jquery and bootstrap)',
				'attr' => [
				],
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'report_view';
	}
	

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request) {

		try {
			$renderQuery = $this->twig->createTemplate($view->getOptions()['body'])->render([
					'view' => $view,
					'contentType' => $view->getContentType(),
					'environment' => $view->getContentType()->getEnvironment(),
			]);
		}
		catch (\Exception $e){
			$renderQuery = "{}";
		}		
		
		$searchQuery = [
			'index' => $view->getContentType()->getEnvironment()->getAlias(),
			'type' => $view->getContentType()->getName(),
			'body' => $renderQuery,
		];
		
		if(isset($view->getOptions()['size'])){
			$searchQuery['size'] = $view->getOptions()['size'];
		}
		
		$result = $this->client->search($searchQuery);
		
		try {
			$render = $this->twig->createTemplate($view->getOptions()['template'])->render([
				'view' => $view,
				'contentType' => $view->getContentType(),
				'environment' => $view->getContentType()->getEnvironment(),
				'result' => $result,
			]);
		}
		catch (\Exception $e){
			$render = "Something went wrong with the template of the view ".$view->getName()." for the content type ".$view->getContentType()->getName()." (".$e->getMessage().")";
		}
        try {
            $javascript = $this->twig->createTemplate($view->getOptions()['javascript'])->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
                'result' => $result,
            ]);
        }
        catch (\Exception $e){
            $javascript = "";
        }
        try {
            $header = $this->twig->createTemplate($view->getOptions()['header'])->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
                'result' => $result,
            ]);
        }
        catch (\Exception $e){
            $header = "";
        }

		return [
			'render' => $render,
			'header' => $header,
			'javascript' => $javascript,
			'view' => $view,
			'contentType' => $view->getContentType(),
			'environment' => $view->getContentType()->getEnvironment(),
		];
	}
	
}