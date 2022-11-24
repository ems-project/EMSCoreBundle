<?php

namespace EMS\CoreBundle\Form\Factory;

use EMS\CoreBundle\Exception\PerformanceException;
use EMS\CoreBundle\Form\Field\ObjectChoiceLoader;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ObjectChoiceListFactory extends DefaultChoiceListFactory
{
    private ContentTypeService $contentTypes;
    private ObjectChoiceCacheService $objectChoiceCacheService;

    public function __construct(
        ContentTypeService $contentTypes,
        ObjectChoiceCacheService $objectChoiceCacheService
    ) {
        $this->contentTypes = $contentTypes;
        $this->objectChoiceCacheService = $objectChoiceCacheService;
    }

    public function createLoader(?string $types = null, bool $loadAll = false, bool $circleOnly = false, bool $withWarning = true, ?string $querySearchName = null): ObjectChoiceLoader
    {
        if (null === $types) {
            if ($loadAll && null === $querySearchName) {
                throw new PerformanceException('Try to load all objects of all content types');
            }
            $types = $this->contentTypes->getAllTypes();
        }

        return new ObjectChoiceLoader($this->objectChoiceCacheService, $types, $loadAll, $circleOnly, $withWarning, $querySearchName);
    }

    public function createListFromLoader(ChoiceLoaderInterface $loader, callable $value = null, callable $filter = null): ChoiceListInterface
    {
        return $loader->loadChoiceList($value);
    }
}
