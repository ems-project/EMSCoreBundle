<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ObjectChoiceList implements ChoiceListInterface
{
    /** @var array<mixed> */
    private array $choices = [];

    public function __construct(private readonly ObjectChoiceCacheService $objectChoiceCacheService, private readonly string $types, private readonly bool $loadAll = false, private readonly bool $circleOnly = false, private readonly bool $withWarning = true, private readonly ?string $querySearchName = null)
    {
    }

    public function getTypes(): string
    {
        return $this->types;
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getChoices(): array
    {
        $this->loadAll();

        return $this->choices;
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getValues(): array
    {
        return \array_keys($this->choices);
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getStructuredValues(): array
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return [$values];
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getOriginalKeys(): array
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return $values;
    }

    /**
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getChoicesForValues(array $choices): array
    {
        $this->choices = $this->objectChoiceCacheService->load($choices, $this->circleOnly, $this->withWarning);

        return \array_keys($this->choices);
    }

    /**
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    #[\Override]
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
