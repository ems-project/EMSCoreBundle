<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ObjectChoiceList implements ChoiceListInterface
{
    /** @var ObjectChoiceCacheService */
    private $objectChoiceCacheService;

    private $types;
    private $choices;
    /** @var bool */
    private $loadAll;
    /** @var bool */
    private $circleOnly;
    /** @var bool */
    private $withWarning;

    public function __construct(
        ObjectChoiceCacheService $objectChoiceCacheService,
        $types = false,
        bool $loadAll = false,
        bool $circleOnly = false,
        bool $withWarning = true
    ) {
        $this->objectChoiceCacheService = $objectChoiceCacheService;
        $this->choices = [];
        $this->types = $types;
        $this->loadAll = $loadAll;
        $this->circleOnly = $circleOnly;
        $this->withWarning = $withWarning;
    }

    public function getTypes()
    {
        return $this->types;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        $this->loadAll();

        return $this->choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return \array_keys($this->choices);
    }

    /**
     * @return array
     */
    public function getStructuredValues()
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return [$values];
    }

    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys()
    {
        return $this->choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $choices)
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return \array_keys($this->choices);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return \array_keys($this->choices);
    }

    public function loadAll()
    {
        if ($this->loadAll) {
            $this->objectChoiceCacheService->loadAll($this->choices, $this->types, $this->circleOnly, $this->withWarning);
        }

        return $this->choices;
    }

    /**
     * intiate (or re-initiate) the choices array based on the list of key passed in parameter.
     */
    public function loadChoices(array $choices)
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return $this->choices;
    }
}
