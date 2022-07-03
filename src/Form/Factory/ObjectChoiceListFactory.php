<?php

namespace EMS\CoreBundle\Form\Factory;

use EMS\CoreBundle\Exception\PerformanceException;
use EMS\CoreBundle\Form\Field\ObjectChoiceLoader;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class ObjectChoiceListFactory extends DefaultChoiceListFactory
{
    private $client;
    /** @var Session */
    private $session;
    /** @var ContentTypeService */
    private $contentTypes;
    /** @var ObjectChoiceCacheService */
    private $objectChoiceCacheService;

    /**
     * constructor called by the service mechanisme.
     */
    public function __construct(
        ContentTypeService $contentTypes,
        ObjectChoiceCacheService $objectChoiceCacheService
    ) {
        $this->contentTypes = $contentTypes;
        $this->objectChoiceCacheService = $objectChoiceCacheService;
    }

    /**
     * instanciate a ObjectChoiceLoader (with the required services).
     */
    public function createLoader($types = null, $loadAll = false, $circleOnly = false, bool $withWarning = true, ?string $querySearchName = null)
    {
        if (null === $types || '' === $loadAll) {
            if ($loadAll && null === $querySearchName) {
                throw new PerformanceException('Try to load all objects of all content types');
            }
            $types = $this->contentTypes->getAllTypes();
        }

        return new ObjectChoiceLoader($this->objectChoiceCacheService, $types, $loadAll, $circleOnly, $withWarning, $querySearchName);
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        return $loader->loadChoiceList($value);
    }
}
