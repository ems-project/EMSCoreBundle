<?php
namespace EMS\CoreBundle\Form\Factory;

use EMS\CoreBundle\Form\Field\ObjectChoiceLoader;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Elasticsearch\Client;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Exception\PerformanceException;


class ObjectChoiceListFactory extends DefaultChoiceListFactory{

    private $client;
    /**@var Session $session*/
    private $session;
    /**@var ContentTypeService $contentTypes*/
    private $contentTypes;
    /**@var ObjectChoiceCacheService $objectChoiceCacheService*/
    private $objectChoiceCacheService;

    /**
     * constructor called by the service mechanisme
     */
    public function __construct(
            ContentTypeService $contentTypes,
            ObjectChoiceCacheService $objectChoiceCacheService){
        $this->contentTypes = $contentTypes;
        $this->objectChoiceCacheService = $objectChoiceCacheService;
    }
    
    /**
     * instanciate a ObjectChoiceLoader (with the required services)
     */
    public function createLoader($types = null, $loadAll = false, $circleOnly=false){
        if(null === $types || $loadAll === ""){
            if($loadAll) {
                throw new PerformanceException('Try to load all objects of all content types');
            }
            $types = $this->contentTypes->getAllTypes();
        }
        return new ObjectChoiceLoader($this->objectChoiceCacheService, $types, $loadAll, $circleOnly);
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        return $loader->loadChoiceList($value);
    }
}