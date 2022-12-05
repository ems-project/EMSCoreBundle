<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ObjectChoiceList implements ChoiceListInterface
{
    /** @var array<mixed> */
    private array $choices = [];

    public function __construct(private readonly ObjectChoiceCacheService $objectChoiceCacheService, private string $types, private readonly bool $loadAll = false, private bool $circleOnly = false, private bool $withWarning = true, private ?string $querySearchName = null)
    {
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
