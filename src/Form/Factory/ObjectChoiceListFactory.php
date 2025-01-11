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
    public function __construct(private readonly ContentTypeService $contentTypes, private readonly ObjectChoiceCacheService $objectChoiceCacheService)
    {
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

    #[\Override]
    public function createListFromLoader(ChoiceLoaderInterface $loader, ?callable $value = null, ?callable $filter = null): ChoiceListInterface
    {
        return $loader->loadChoiceList($value);
    }

    /**
     * @param iterable<mixed> $choices
     */
    #[\Override]
    public function createListFromChoices(iterable $choices, ?callable $value = null, ?callable $filter = null): ChoiceListInterface
    {
        return parent::createListFromChoices($choices, $value, $filter);
    }
}
