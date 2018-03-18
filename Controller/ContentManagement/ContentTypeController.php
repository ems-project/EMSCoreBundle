<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Helper\JsonNormalizer;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\ContentTypeStructureType;
use EMS\CoreBundle\Form\Form\ContentTypeType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Operations on content types such as CRUD but alose rebuild index.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class ContentTypeController extends AppController {
	
	
	public static function isValidName($name) {
		if(in_array($name, ['_sha1', '_signature', '_finalized_by', '_finalization_datetime'])){
			return false;
		}
		return preg_match('/^[a-z][a-z0-9\-_]*$/i', $name) && strlen($name) <= 100;
	}
	
	
	
	/**
	 * Logically delete a content type.
	 * GET calls aren't supported.
	 *
	 * @param integer $id
	 *        	identifier of the content type to delete
	 * @param Request $request
	 * 
	 * @Route("/content-type/remove/{id}", name="contenttype.remove"))
     * @Method({"POST"})
	 *        
	 */
	public function removeAction($id, Request $request) {
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		/** @var ContentTypeRepository $repository */
		$repository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
		
		/** @var ContentType $contentType */
		$contentType = $repository->find ( $id );
		
		if (!$contentType || count ( $contentType ) != 1) {
			throw new NotFoundHttpException('Content Type not found');
		}
		
		//TODO test if there something published for this content type 
		$contentType->setActive ( false )->setDeleted ( true );
		$em->persist ( $contentType );
		$em->flush ();
		$this->addFlash ( 'warning', 'Content type ' . $contentType->getName () . ' has been deleted' );
		
		return $this->redirectToRoute ( 'contenttype.index' );
	}
	
	/**
	 * Activate (make it available for authors) a content type.
	 * Checks that the content isn't dirty (as far as eMS knows the Mapping in Elasticsearch is up-to-date).
	 *
	 * @param integer $id        	
	 * @param Request $request
	 * 
	 * @Route("/content-type/activate/{contentType}", name="contenttype.activate"))
     * @Method({"POST"})
	 */
	public function activateAction(ContentType $contentType, Request $request) {
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		
		if ($contentType->getDirty ()) {
			$this->addFlash ( 'warning', 'Content type "' . $contentType->getName () . '" is dirty (its mapping migth be out-of date).
					Try to update its mapping.' );
			return $this->redirectToRoute ( 'contenttype.index' );
		}
		
		$contentType->setActive ( true );
		$em->persist ( $contentType );
		$em->flush ();
		return $this->redirectToRoute ( 'contenttype.index' );
	}
	
	/**
	 * Desctivate (make it unavailable for authors) a content type.
	 *
	 * @param integer $id        	
	 * @param Request $request
	 * 
	 * @Route("/content-type/desactivate/{contentType}", name="contenttype.desactivate"))
     * @Method({"POST"})
	 */
	public function desactivateAction(ContentType $contentType, Request $request) {
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		
		$contentType->setActive ( false );
		$em->persist ( $contentType );
		$em->flush ();
		return $this->redirectToRoute ( 'contenttype.index' );
	}
	
/**
	 * Try to update the Elasticsearch mapping for a specific content type
	 *
	 * @param ContentType $id        	
	 * @param Request $request        	
	 * @throws BadRequestHttpException @Route("/content-type/refresh-mapping/{id}", name="contenttype.refreshmapping"))
	 * 
     * @Method({"POST"})
	 */
	public function refreshMappingAction(ContentType $id, Request $request) {
		$this->getContentTypeService()->updateMapping($id);
		$this->getContentTypeService()->persist($id);
		return $this->redirectToRoute ( 'contenttype.index' );
	}
	
	/**
	 * Initiate a new content type as a draft
	 *
	 * @param Request $request
	 *        	@Route("/content-type/add", name="contenttype.add"))
	 */
	public function addAction(Request $request) {
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		
		/** @var EnvironmentRepository $environmetRepository */
		$environmetRepository = $em->getRepository ( 'EMSCoreBundle:Environment' );
		
		$environments = $environmetRepository->findBy ( [ 
				'managed' => true 
		] );
		
		$contentTypeAdded = new ContentType ();
		$form = $this->createFormBuilder ( $contentTypeAdded )->add ( 'name', IconTextType::class, [ 
				'icon' => 'fa fa-gear',
				'label' => "Machine name",
				'required' => true
		] )->add ( 'singularName', TextType::class, [ 
		] )->add ( 'pluralName', TextType::class, [ 
		] )->add ( 'import', FileType::class, [ 
				'label' => 'Import From JSON',
				'mapped' => false,
				'required' => false
		] )->add ( 'environment', ChoiceType::class, [
				'label' => 'Default environment',
				'choices' => $environments,
				/** @var Environment $environment */
				'choice_label' => function ($environment, $key, $index) {
					return $environment->getName ();
				}
		] )->add ( 'save', SubmitType::class, [ 
				'label' => 'Create',
				'attr' => [ 
						'class' => 'btn btn-primary pull-right' 
				] 
		] )->getForm ();
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			/** @var ContentType $contentType */
			$contentTypeAdded = $form->getData ();
			$contentTypeRepository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
			
			$contentTypes = $contentTypeRepository->findBy ( [ 
					'name' => $contentTypeAdded->getName () ,
					'deleted' => false
			] );
			
			if (count ( $contentTypes ) != 0) {
				$form->get ( 'name' )->addError ( new FormError ( 'Another content type named ' . $contentTypeAdded->getName () . ' already exists' ) );
			}
			
			if(!$this->isValidName($contentTypeAdded->getName () )){
				$form->get ( 'name' )->addError ( new FormError ( 'The content type name is malformed (format: [a-z][a-z0-9_-]*)' ) );
			}
			
			if ($form->isValid ()) {
				$normData = $form->get("import")->getNormData();
				if($normData){
					$name = $contentTypeAdded->getName();
					$pluralName = $contentTypeAdded->getPluralName();
					$environment = $contentTypeAdded->getEnvironment();
					/** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
					$file = $request->files->get('form')['import'];
					$fileContent = file_get_contents($file->getRealPath());
					
					$encoders = array(new JsonEncoder());
					$normalizers = array(new JsonNormalizer());
					$serializer = new Serializer($normalizers, $encoders);
					$contentType = $serializer->deserialize($fileContent, 
															"EMS\CoreBundle\Entity\ContentType", 
															'json');
					$contentType->setName($name);
					$contentType->setPluralName($pluralName);
					$contentType->setEnvironment($environment);
					$contentType->setActive(false);
					$contentType->setDirty(true);
					$contentType->getFieldType()->updateAncestorReferences($contentType, NULL);
					$contentType->setOrderKey($contentTypeRepository->maxOrderKey()+1);

					$em->persist ( $contentType );
				}
				else {
					$contentType = $contentTypeAdded;
					$contentType->setAskForOuuid(false);
					$contentType->setViewRole('ROLE_AUTHOR');
					$contentType->setEditRole('ROLE_AUTHOR');
					$contentType->setCreateRole('ROLE_AUTHOR');
					$contentType->setOrderKey($contentTypeRepository->maxOrderKey()+1);
					$em->persist ( $contentType );
				}
				$em->flush ();
				$this->addFlash ( 'notice', 'A new content type ' . $contentTypeAdded->getName () . ' has been created' );
				
				return $this->redirectToRoute ( 'contenttype.edit', [
						'id' => $contentType->getId ()
				] );
				
			} else {
				$this->addFlash ( 'error', 'Invalid form.' );
			}
		}
		
		return $this->render ( 'EMSCoreBundle:contenttype:add.html.twig', [ 
				'form' => $form->createView () 
		] );
	}
	
	/**
	 * List all content types
	 *
	 * @param Request $request
	 *        	@Route("/content-type", name="contenttype.index"))
	 */
	public function indexAction(Request $request) {

		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		
		/** @var ContentTypeRepository $contentTypeRepository */
		$contentTypeRepository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
		
		$contentTypes = $contentTypeRepository->findBy(['deleted' => false], ['orderKey'=>'ASC']);
		
		$builder = $this->createFormBuilder ( [] )
			->add ( 'reorder', SubmitEmsType::class, [
    				'attr' => [
    						'class' => 'btn-primary '
    				],
    				'icon' => 'fa fa-reorder'    			
    		] );
		
		$names = [];	
		foreach ($contentTypes as $contentType) {
			$names[] = $contentType->getName();
		}
		
		$builder->add('contentTypeNames', CollectionType::class, array(
				// each entry in the array will be an "email" field
				'entry_type'   => HiddenType::class,
				// these options are passed to each "email" type
				'entry_options'  => array(
				),
				'data' => $names
		));
    		
    	$form = $builder->getForm ();
    	
    	if ($request->isMethod('POST')) {
			$form = $request->get('form');
			if(isset($form['contentTypeNames']) && is_array($form['contentTypeNames'])){
				$counter = 0;
				foreach ($form['contentTypeNames'] as $name){
					
					$contentType = $contentTypeRepository->findOneBy([
							'deleted' => false,
							'name' => $name
					]);
					if($contentType){
						$contentType->setOrderKey($counter);
						$em->persist($contentType);
					}
					++$counter;
				}
				
				$em->flush();
	    		$this->addFlash('notice', 'Content types have been reordered');
			}
    	
    		return $this->redirectToRoute('contenttype.index');
    	}
		
		return $this->render ( 'EMSCoreBundle:contenttype:index.html.twig', [
				'form' => $form->createView (),
		] );
	}
	
	/**
	 * List all unreferenced content types (from external sources)
	 *
	 * @param Request $request
	 *        	@Route("/content-type/unreferenced", name="contenttype.unreferenced"))
	 */
	public function unreferencedAction(Request $request) {
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		
		/** @var EnvironmentRepository $environmetRepository */
		$environmetRepository = $em->getRepository ( 'EMSCoreBundle:Environment' );
		$contentTypeRepository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
		
		if ($request->isMethod ( 'POST' )) {
			if (null != $request->get ( 'envId' ) && null != $request->get ( 'name' )) {
				$defaultEnvironment = $environmetRepository->find ( $request->get ( 'envId' ) );
				if ($defaultEnvironment) {
					$contentType = new ContentType ();
					$contentType->setName ( $request->get ( 'name' ) );
					$contentType->setPluralName ( $contentType->getName () );
					$contentType->setSingularName( $contentType->getName () );
					$contentType->setEnvironment ( $defaultEnvironment );
					$contentType->setActive ( true );
					$contentType->setDirty ( false );
					$contentType->setOrderKey($contentTypeRepository->countContentType());
					
					$em->persist ( $contentType );
					$em->flush ();
					$this->addFlash ( 'notice', 'The content type ' . $contentType->getName () . ' is now referenced' );
					return $this->redirectToRoute ( 'contenttype.edit', [ 
							'id' => $contentType->getId () 
					] );
				}
			}
			$this->addFlash ( 'warning', 'Unreferenced content type not found.' );
			return $this->redirectToRoute ( 'contenttype.unreferenced' );
		}
		
		/** @var ContentTypeRepository $contenttypeRepository */
		$contenttypeRepository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
		
		$environments = $environmetRepository->findBy ( [ 
				'managed' => false 
		] );
		
		/** @var  Client $client */
		$client = $this->getElasticsearch();
		
		$referencedContentTypes = [ ];
		/** @var Environment $environment */
		foreach ( $environments as $environment ) {
			$alias = $environment->getAlias ();
			$mapping = $client->indices ()->getMapping ( [ 
					'index' => $alias 
			] );
			foreach ( $mapping as $indexName => $index ) {
				foreach ( $index ['mappings'] as $name => $type ) {
					$already = $contenttypeRepository->findBy ( [ 
							'name' => $name 
					] );
					if (! $already || $already [0]->getDeleted ()) {
						$referencedContentTypes [] = [ 
								'name' => $name,
								'alias' => $alias,
								'envId' => $environment->getId () 
						];
					}
				}
			}
		}
		
		return $this->render ( 'EMSCoreBundle:contenttype:unreferenced.html.twig', [ 
				'referencedContentTypes' => $referencedContentTypes 
		] );
	}
	
	/**
	 * Try to find (recursively) if there is a new field to add to the content type
	 * 
	 * @param array $formArray        	
	 * @param FieldType $fieldType        	
	 */
	private function addNewField(array $formArray, FieldType $fieldType) {
		if (array_key_exists ( 'add', $formArray )) {
			if(isset($formArray ['ems:internal:add:field:name']) 
					&& strcmp($formArray ['ems:internal:add:field:name'], '') != 0
					&& isset($formArray ['ems:internal:add:field:class']) 
					&& strcmp($formArray ['ems:internal:add:field:class'], '') != 0) {
				if($this->isValidName($formArray ['ems:internal:add:field:name'])){
					$fieldTypeNameOrServiceName = $formArray ['ems:internal:add:field:class'];
					$fieldName = $formArray ['ems:internal:add:field:name']; 
					/** @var DataFieldType $dataFieldType */
					$dataFieldType = $this->getDataFielType($fieldTypeNameOrServiceName);
					$child = new FieldType ();
					$child->setName ( $fieldName );
					$child->setType ( $fieldTypeNameOrServiceName );
					$child->setParent ( $fieldType );
					$child->setOptions( $dataFieldType->getDefaultOptions($fieldName) );
					$fieldType->addChild ( $child );
					$this->addFlash('notice', 'The field '.$child->getName().' has been prepared to be added');
					return '_ems_'.$child->getName().'_modal_options';
				}
				else {
					$this->addFlash('error', 'The field\'s name is not valid (format: [a-z][a-z0-9_-]*), _sha1 and _signature are reserved.');
				}
			}
			else {
				$this->addFlash('error', 'The field\'s name and type are mandatory');
			}
			return true;
		} else {
			/** @var FieldType $child */
			foreach ( $fieldType->getChildren () as $child ) {
				if (! $child->getDeleted ()) {
					$out = $this->addNewField ( $formArray ['ems_'.$child->getName ()], $child );
					if ( $out !== false ) {
						return '_ems_'.$child->getName ().$out;
					}					
				}
			}
		}
		return false;
	}
	
	/**
	 * Try to find (recursively) if there is a new field to add to the content type
	 * 
	 * @param array $formArray        	
	 * @param FieldType $fieldType        	
	 */
	private function addNewSubfield(array $formArray, FieldType $fieldType) {
		if (array_key_exists ( 'subfield', $formArray )) {
			if(isset($formArray ['ems:internal:add:subfield:name']) 
					&& strcmp($formArray ['ems:internal:add:subfield:name'], '') !== 0) {
				if($this->isValidName($formArray ['ems:internal:add:subfield:name'])) {
					$child = new FieldType ();
					$child->setName ( $formArray ['ems:internal:add:subfield:name'] );
					$child->setType ( SubfieldType::class );
					$child->setParent ( $fieldType );
					$fieldType->addChild ( $child );
					$this->addFlash('notice', 'The subfield '.$child->getName().' has been prepared to be added');

					return '_ems_'.$child->getName().'_modal_options';
				}
				else {
					$this->addFlash('error', 'The subfield\'s name is not valid (format: [a-z][a-z0-9_-]*)');
				}
			}
			else{
				$this->addFlash('notice', 'The subfield name is mandatory');
			}
			return true;
		} else {
			/** @var FieldType $child */
			foreach ( $fieldType->getChildren () as $child ) {
				if (! $child->getDeleted () ) {
					$out = $this->addNewSubfield ( $formArray ['ems_'.$child->getName ()], $child );
					if ( $out !== false ) {
						return '_ems_'.$child->getName ().$out;
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * Try to find (recursively) if there is a field to duplicate
	 * 
	 * @param array $formArray        	
	 * @param FieldType $fieldType        	
	 */
	private function duplicateField(array $formArray, FieldType $fieldType) {
		if (array_key_exists ( 'duplicate', $formArray )) {
			if(isset($formArray ['ems:internal:add:subfield:target_name']) 
					&& strcmp($formArray ['ems:internal:add:subfield:target_name'], '') !== 0) {
				if($this->isValidName($formArray ['ems:internal:add:subfield:target_name'])) {
					$new = clone $fieldType;
					$new->setName ( $formArray ['ems:internal:add:subfield:target_name'] );
					$new->getParent()->addChild($new);
					
					$this->addFlash('notice', 'The field '.$new->getName().' has been initialized/duplicated');
					return 'first_ems_'.$new->getName().'_modal_options';
				}
				else {
					$this->addFlash('error', 'The field\'s name is not valid (format: [a-z][a-z0-9_-]*)');
				}
			}
			else{
				$this->addFlash('notice', 'The field\'s name is mandatory');
			}
			return true;
		} else {
			/** @var FieldType $child */
			foreach ( $fieldType->getChildren () as $child ) {
				if (! $child->getDeleted () ) {
					$out = $this->duplicateField ( $formArray ['ems_'.$child->getName ()], $child );
					if ( $out !== false ) {
						if(substr($out, 0, 5) == 'first'){
							return substr($out, 5);
						}
						return '_ems_'.$child->getName ().$out;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Try to find (recursively) if there is a field to remove from the content type
	 * 
	 * @param array $formArray
	 * @param FieldType $fieldType
	 */
	private function removeField(array $formArray, FieldType $fieldType){
		if(array_key_exists('remove', $formArray)){
			$fieldType->setDeleted(true);
			$this->addFlash('notice', 'The field '.$fieldType->getName().' has been prepared to be removed');
			return true;
		}
		else{
			/** @var FieldType $child */
			foreach ($fieldType->getChildren() as $child){
				if(!$child->getDeleted() && $this->removeField($formArray['ems_'.$child->getName()], $child)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Try to find (recursively) if there is a container where subfields must be reordered in the content type
	 *
	 * @param array $formArray
	 * @param FieldType $fieldType
	 */	
	private function reorderFields(array $formArray, FieldType $fieldType){
		if(array_key_exists('reorder', $formArray)){
			$keys = array_keys($formArray);
			/** @var FieldType $child */
			foreach ($fieldType->getChildren() as $child){
				if(! $child->getDeleted() ){
					$child->setOrderKey(array_search('ems_'.$child->getName(), $keys));
				}
			}

			$this->addFlash('notice', 'Subfields in '.$fieldType->getName().' has been prepared to be reordered');
			return true;
		}
		else{
			/** @var FieldType $child */
			foreach ($fieldType->getChildren() as $child){
				if(!$child->getDeleted() && $this->reorderFields($formArray['ems_'.$child->getName()], $child)) {
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Reorder a content type
	 *
	 * @param integer $id
	 * @param Request $request
	 * @Route("/content-type/reorder/{contentType}", name="ems_contenttype_reorder"))
	 */
	public function reorderAction(ContentType $contentType, Request $request) {
		$data = [];
		$form = $this->createForm(ReorderType::class, $data, [
		]);
		
		$form->handleRequest($request);
		
		if ($form->isSubmitted()) {
			$data = $form->getData();
			$structure = json_decode($data['items'], true);
			$this->getContentTypeService()->reorderFields($contentType, $structure);
			return $this->redirectToRoute('contenttype.edit', ['id' => $contentType->getId()]);
		}
		
		return $this->render ( 'EMSCoreBundle:contenttype:reorder.html.twig', [
				'form' => $form->createView (),
				'contentType' => $contentType,
		] );
	}
	
	/**
	 * Edit a content type; generic information, but Nothing impacting its structure or it's mapping
	 *
	 * @param integer $id
	 * @param Request $request
	 *        	@Route("/content-type/{id}", name="contenttype.edit"))
	 */
	public function editAction($id, Request $request) {
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		/** @var ContentTypeRepository $repository */
		$repository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
		
		/** @var ContentType $contentType */
		$contentType = $repository->find ( $id );
		
		if (! $contentType ) {
			$this-> addFlash ( 'warning', 'Content type not found.' );
			return $this->redirectToRoute ( 'contenttype.index' );
		}
		
		$inputContentType = $request->request->get ( 'content_type' );
		
		/** @var  Client $client */
		$client = $this->getElasticsearch();
		
		try{
			$mapping = $client->indices ()->getMapping ( [
					'index' => $contentType->getEnvironment ()->getAlias (),
					'type' => $contentType->getName ()
			] );
		}
		catch(ElasticsearchException$e){
			$this-> addFlash ( 'warning', 'Mapping not found.' );
			$mapping = [];
		}
		
		$form = $this->createForm ( ContentTypeType::class, $contentType, [
			'twigWithWysiwyg' => $contentType->getEditTwigWithWysiwyg(),
			'mapping' => $mapping,
		] );
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			$contentType->getFieldType()->setName('source');
			
			if (array_key_exists ( 'save', $inputContentType ) || array_key_exists ( 'saveAndUpdateMapping', $inputContentType ) || array_key_exists ( 'saveAndClose', $inputContentType ) || array_key_exists ( 'saveAndEditStructure', $inputContentType ) || array_key_exists ( 'saveAndReorder', $inputContentType )) {
// 				$contentType->getFieldType ()->updateOrderKeys ();
// 				$contentType->setDirty ( $contentType->getEnvironment ()->getManaged () );


				if (array_key_exists ( 'saveAndUpdateMapping', $inputContentType )){
					$this->getContentTypeService()->updateMapping($contentType);
				}
// 				exit;
				$em->persist ( $contentType );
				$em->flush ();
				
				if($contentType->getDirty()){
					$this->addFlash ( 'warning', 'Content type has beend saved. Please consider to update the Elasticsearch mapping.' );					
				}
				if (array_key_exists ( 'saveAndClose', $inputContentType )){
					return $this->redirectToRoute ( 'contenttype.index' );					
				}
				else if (array_key_exists ( 'saveAndEditStructure', $inputContentType )){
					return $this->redirectToRoute ( 'contenttype.structure', [
							'id' => $id
					] );
				}
				else if (array_key_exists ( 'saveAndReorder', $inputContentType )){
					return $this->redirectToRoute ( 'ems_contenttype_reorder', [
							'contentType' => $id
					] );
				}
				return $this->redirectToRoute ( 'contenttype.edit', [
						'id' => $id
				] );
// 			} else {
// 				if ($this->addNewField ( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
// 					$contentType->getFieldType ()->updateOrderKeys ();
					
// 					$em->persist ( $contentType );
// 					$em->flush ();
// 					return $this->redirectToRoute ( 'contenttype.edit', [ 
// 							'id' => $id 
// 					] );
// 				}
				
// 				else if ($this->addNewSubfield( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
// 					$contentType->getFieldType ()->updateOrderKeys ();
// 					$em->persist ( $contentType );
// 					$em->flush ();
// 					return $this->redirectToRoute ( 'contenttype.edit', [ 
// 							'id' => $id 
// 					] );
// 				}
				
// 				else if ($this->removeField ( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
// 					$contentType->getFieldType ()->updateOrderKeys ();
// 					$em->persist ( $contentType );
// 					$em->flush ();
// 					$this->addFlash ( 'notice', 'A field has been removed.' );
// 					return $this->redirectToRoute ( 'contenttype.edit', [ 
// 							'id' => $id 
// 					] );
// 				}
				
// 				else if ($this->reorderFields ( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
// 					// $contentType->getFieldType()->updateOrderKeys();
// 					$em->persist ( $contentType );
// 					$em->flush ();
// 					$this->addFlash ( 'notice', 'Fields have been reordered.' );
// 					return $this->redirectToRoute ( 'contenttype.edit', [ 
// 							'id' => $id 
// 					] );
// 				}
			}
		}
		

		
		if($contentType->getDirty()){
			$this->addFlash('warning', $contentType->getName().' is dirty. Consider to update its mapping.');
		}
		
		return $this->render ( 'EMSCoreBundle:contenttype:edit.html.twig', [ 
				'form' => $form->createView (),
				'contentType' => $contentType,
				'mapping' => isset ( current ( $mapping ) ['mappings'] [$contentType->getName ()] ['properties'] ) ? current ( $mapping ) ['mappings'] [$contentType->getName ()] ['properties'] : false 
		] );
	}
	
	/**
	 * Edit a content type structure; add subfields.
	 * Each times that a content type strucuture is saved the flag dirty is turned on.
	 *
	 * @param integer $id        	
	 * @param Request $request
	 *        	@Route("/content-type/structure/{id}", name="contenttype.structure"))
	 */
	public function editStructureAction($id, Request $request) {
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		/** @var ContentTypeRepository $repository */
		$repository = $em->getRepository ( 'EMSCoreBundle:ContentType' );
		
		/** @var ContentType $contentType */
		$contentType = $repository->find ( $id );
		
		if (! $contentType ) {
			$this-> addFlash ( 'warning', 'Content type not found.' );
			return $this->redirectToRoute ( 'contenttype.index' );
		}
		
		$inputContentType = $request->request->get ( 'content_type_structure' );
		
		$form = $this->createForm ( ContentTypeStructureType::class, $contentType, [
// 			'twigWithWysiwyg' => $contentType->getEditTwigWithWysiwyg()
		] );
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			$contentType->getFieldType()->setName('source');
			
			if (array_key_exists ( 'save', $inputContentType ) || array_key_exists ( 'saveAndClose', $inputContentType ) || array_key_exists ( 'saveAndReorder', $inputContentType )) {
				$contentType->getFieldType ()->updateOrderKeys ();
				$contentType->setDirty ( $contentType->getEnvironment ()->getManaged () );
				
				if((array_key_exists ( 'saveAndClose', $inputContentType ) ||  array_key_exists ( 'saveAndReorder', $inputContentType )) && $contentType->getDirty()){
					$this->getContentTypeService()->updateMapping($contentType);					
				}
				
				$this->getContentTypeService()->persist($contentType);

				if($contentType->getDirty()){
					$this->addFlash ( 'warning', 'Content type has beend saved. Please consider to update the Elasticsearch mapping.' );					
				}
				if (array_key_exists ( 'saveAndClose', $inputContentType )){
					return $this->redirectToRoute ( 'contenttype.edit', [
							'id' => $id
					]);
				}
				if (array_key_exists ( 'saveAndReorder', $inputContentType )){
					return $this->redirectToRoute ( 'ems_contenttype_reorder', [
							'contentType' => $id
					]);
				}
				return $this->redirectToRoute ( 'contenttype.structure', [
						'id' => $id
				] );
			} else {
				if ($out = $this->addNewField ( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
					$contentType->getFieldType ()->updateOrderKeys ();
					
					$em->persist ( $contentType );
					$em->flush ();
					return $this->redirectToRoute ( 'contenttype.structure', [ 
							'id' => $id,
							'open' => $out,
					] );
				}
				
				else if($out = $this->addNewSubfield( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
					$contentType->getFieldType ()->updateOrderKeys ();
					$em->persist ( $contentType );
					$em->flush ();
					return $this->redirectToRoute ( 'contenttype.structure', [ 
							'id' => $id,
							'open' => $out,
					] );
				}
				
				else if ($out = $this->duplicateField( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
					$contentType->getFieldType ()->updateOrderKeys ();
					$em->persist ( $contentType );
					$em->flush ();
					return $this->redirectToRoute ( 'contenttype.structure', [ 
							'id' => $id ,
							'open' => $out,
					] );
				}
				
				else if ($this->removeField ( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
					$contentType->getFieldType ()->updateOrderKeys ();
					$em->persist ( $contentType );
					$em->flush ();
					$this->addFlash ( 'notice', 'A field has been removed.' );
					return $this->redirectToRoute ( 'contenttype.structure', [ 
							'id' => $id 
					] );
				}
				else if ($this->reorderFields ( $inputContentType ['fieldType'], $contentType->getFieldType () )) {
					// $contentType->getFieldType()->updateOrderKeys();
					$em->persist ( $contentType );
					$em->flush ();
					$this->addFlash ( 'notice', 'Fields have been reordered.' );
					return $this->redirectToRoute ( 'contenttype.structure', [ 
							'id' => $id 
					] );
				}
			}
		}
		
// 		/** @var  Client $client */
// 		$client = $this->getElasticsearch();
		
// 		$mapping = $client->indices ()->getMapping ( [ 
// 				'index' => $contentType->getEnvironment ()->getAlias (),
// 				'type' => $contentType->getName () 
// 		] );
		
		if($contentType->getDirty()){
			$this->addFlash('warning', $contentType->getName().' is dirty. Consider to update its mapping.');
		}
		
		return $this->render ( 'EMSCoreBundle:contenttype:structure.html.twig', [ 
				'form' => $form->createView (),
				'contentType' => $contentType,
// 				'mapping' => isset ( current ( $mapping ) ['mappings'] [$contentType->getName ()] ['properties'] ) ? current ( $mapping ) ['mappings'] [$contentType->getName ()] ['properties'] : false 
		] );
	}
	
	

	/**
	 * Migrate a content type from its default index
	 *
	 * @param integer $id        	
	 * @param Request $request
     * @Method({"POST"})
	 * @Route("/content-type/migrate/{contentType}", name="contenttype.migrate"))
	 */	
	 public function migrateAction(ContentType $contentType, Request $request) {
	 	return $this->startJob('ems.contenttype.migrate', [
	 			'contentTypeName'    => $contentType->getName()
	 	]);
	 }
	
	
	/**
	 * Export a content type in Json format
	 *
	 * @param integer $id        	
	 * @param Request $request
	 *        	@Route("/content-type/export/{contentType}.{_format}", defaults={"_format" = "json"}, name="contenttype.export"))
	 */
	public function exportAction(ContentType $contentType, Request $request) {
		//Sanitize the CT
		$contentType->setCreated(NULL);
		$contentType->setModified(NULL);
		$contentType->getFieldType()->removeCircularReference();		
		$contentType->setEnvironment(NULL);
		//$contentType->getTemplates()->clear();
		//$contentType->getViews()->clear();

		//Serialize the CT
		$encoders = array(new JsonEncoder());
		$normalizers = array(new JsonNormalizer());
		$serializer = new Serializer($normalizers, $encoders);
		$jsonContent = $serializer->serialize($contentType, 'json');
		$response = new Response($jsonContent);
		$diposition = $response->headers->makeDisposition(
		    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
		    $contentType->getName().'.json'
		);
		
		$response->headers->set('Content-Disposition', $diposition);
		return $response;
	}
}