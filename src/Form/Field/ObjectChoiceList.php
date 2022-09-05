<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ObjectChoiceList implements ChoiceListInterface
{
    private ObjectChoiceCacheService $objectChoiceCacheService;

    private string $types;
    /** @var array<mixed> */
    private array $choices;
    private bool $loadAll;
    private bool $circleOnly;
    private bool $withWarning;
    private ?string $querySearchName;

    public function __construct(
        ObjectChoiceCacheService $objectChoiceCacheService,
        string $types,
        bool $loadAll = false,
        bool $circleOnly = false,
        bool $withWarning = true,
        ?string $querySearchName = null
    ) {
        $this->objectChoiceCacheService = $objectChoiceCacheService;
        $this->choices = [];
        $this->types = $types;
        $this->loadAll = $loadAll;
        $this->circleOnly = $circleOnly;
        $this->withWarning = $withWarning;
        $this->querySearchName = $querySearchName;
    }

    public function getTypes(): string
    {
        return $this->types;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<mixed>
     */
    public function getChoices(): array
    {
        $this->loadAll();

        return $this->choices;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<mixed>
     */
    public function getValues(): array
    {
        return \array_keys($this->choices);
    }

    /**
     * @return array<mixed>
     */
    public function getStructuredValues(): array
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return [$values];
    }

    /**
     * {@inheritDoc}
     *
     * @return array<mixed>
     */
    public function getOriginalKeys(): array
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return $values;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function getChoicesForValues(array $choices): array
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return \array_keys($this->choices);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function getValuesForChoices(array $choices): array
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return \array_keys($this->choices);
    }

    /**
     * @return array<mixed>
     */
    public function loadAll(): array
    {
        if ($this->loadAll) {
            $this->objectChoiceCacheService->loadAll($this->choices, $this->types, $this->circleOnly, $this->withWarning, $this->querySearchName);
        }

        return $this->choices;
    }

    /**
     * intiate (or re-initiate) the choices array based on the list of key passed in parameter.
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function loadChoices(array $choices): array
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return $this->choices;
    }
}
