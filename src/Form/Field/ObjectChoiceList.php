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

    public function __construct(
        ObjectChoiceCacheService $objectChoiceCacheService,
        string $types,
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

    public function getTypes(): string
    {
        return $this->types;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function getChoices(): array
    {
        $this->loadAll($this->types);

        return $this->choices;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function getOriginalKeys(): array
    {
        return $this->choices;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
    public function loadAll(string $types): array
    {
        if ($this->loadAll) {
            $this->objectChoiceCacheService->loadAll($this->choices, $types, $this->circleOnly, $this->withWarning);
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
