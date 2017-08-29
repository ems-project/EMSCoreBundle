<?php
namespace EMS\CoreBundle\Controller\Views;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\CriteriaUpdateConfig;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;
use EMS\CoreBundle\Form\View\Criteria\CriteriaFilterType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HierarchicalController extends AppController {
	
	
	
	/**
	 * @Route("/views/hierarchical/item/{view}/{key}", name="views.hierarchical.item"))
	 */
	public function itemAction(View $view, $key, Request $request) {
		$ouuid = explode(':', $key);
		$contentType = $this->getContentTypeService()->getByName($ouuid[0]);
		$item = $this->getElasticsearch()->get([
				'index' => $contentType->getEnvironment()->getAlias(),
				'type' => $ouuid[0],
				'id' => $ouuid[1],
		]);
		
		return $this->render( 'EMSCoreBundle:view:custom/hierarchical_add_item.html.twig', [
				'data' => $item['_source'],
				'view' => $view,
				'contentType' => $contentType,
				'key' => $ouuid,
				'child' => $key
		] );
	}
}