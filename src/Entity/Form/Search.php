<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Search implements \JsonSerializable
{
    /** @var Collection<int, SearchFilter> */
    public Collection $filters;
    /** @var string[] */
    public array $environments = [];
    /** @var string[] */
    public array $contentTypes = [];
    public ?string $sortBy = null;
    public ?string $sortOrder = null;
    /** @var int */
    protected $minimumShouldMatch = 1;

    public function __construct()
    {
        $this->filters = new ArrayCollection();
        $this->filters->add(new SearchFilter());
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        $out = [
            'environments' => $this->environments,
            'contentTypes' => $this->contentTypes,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'minimumShouldMatch' => $this->minimumShouldMatch,
        ];

        $out['filters'] = [];
        foreach ($this->filters as $filter) {
            $out['filters'][] = $filter->jsonSerialize();
        }

        return $out;
    }

    public function getFirstFilter(): SearchFilter
    {
        if (!$firstFilter = $this->filters->first()) {
            $newFilter = new SearchFilter();
            $this->addFilter($newFilter);

            return $newFilter;
        }

        return $firstFilter;
    }

    public function addFilter(SearchFilter $filter): self
    {
        if (!$this->filters->contains($filter)) {
            $this->filters->add($filter);
        }

        return $this;
    }

    public function removeFilter(SearchFilter $filter): void
    {
        if ($this->filters->contains($filter)) {
            $this->filters->removeElement($filter);
        }
    }

    public function clearFilters(): void
    {
        $this->filters->clear();
    }

    /**
     * @return Collection<int, SearchFilter>
     */
    public function getFilters(): Collection
    {
        return $this->filters;
    }

    public function setSortBy(?string $sortBy): Search
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    public function setSortOrder(?string $sortOrder): Search
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getSortOrder(): ?string
    {
        return $this->sortOrder;
    }

    /**
     * @param string[] $environments
     */
    public function setEnvironments(array $environments): self
    {
        $this->environments = $environments;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    /**
     * @param string[] $contentTypes
     */
    public function setContentTypes(array $contentTypes): self
    {
        $this->contentTypes = $contentTypes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getContentTypes(): array
    {
        return \array_values($this->contentTypes);
    }

    public function getMinimumShouldMatch(): int
    {
        return $this->minimumShouldMatch;
    }

    public function setMinimumShouldMatch(int $minimumShouldMatch): Search
    {
        $this->minimumShouldMatch = $minimumShouldMatch;

        return $this;
    }

    public function setSearchPattern(string $pattern, bool $liveSearch = false): void
    {
        $queryString = $pattern;
        if ($liveSearch && '' !== $pattern && !\in_array(\substr($pattern, -1), [' ', '?', '*', '.', '/'], true)) {
            $queryString .= '*';
        }

        $filters = $this->getFilters()->isEmpty() ? [$this->getFirstFilter()] : $this->getFilters();

        foreach ($filters as $filter) {
            if (empty($filter->getPattern())) {
                if (\in_array($filter->getOperator(), ['query_and', 'query_or'])) {
                    $filter->setPattern($queryString);
                } else {
                    $filter->setPattern($pattern);
                }
            }
        }
    }
}
