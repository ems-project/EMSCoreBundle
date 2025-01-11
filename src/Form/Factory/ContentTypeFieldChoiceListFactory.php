<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Factory;

use EMS\CoreBundle\Form\Field\ContentTypeFieldChoiceLoader;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ContentTypeFieldChoiceListFactory extends DefaultChoiceListFactory
{
    /**
     * @param array<mixed> $mapping
     * @param array<mixed> $types
     */
    public function createLoader(array $mapping, array $types, bool $firstLevelOnly): ContentTypeFieldChoiceLoader
    {
        return new ContentTypeFieldChoiceLoader($mapping, $types, $firstLevelOnly);
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
