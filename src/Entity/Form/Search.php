<?php

namespace EMS\CoreBundle\Entity\Form;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * Search.
 *
 * @ORM\Table(name="search")
 * @ORM\Entity()
 */
class Search implements JsonSerializable
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var array
     *
     * @ORM\OneToMany(targetEntity="SearchFilter", mappedBy="search", cascade={"persist", "remove"})
     */
    public $filters;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=100)
     */
    private $user;

    /**
     * @var array
     *
     * @ORM\Column(name="environments", type="json_array")
     */
    public $environments;

    /**
     * @var array
     *
     * @ORM\Column(name="contentTypes", type="json_array")
     */
    public $contentTypes;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=100)
     */
    private $name;

    /**
     * @var bool
     *
     * @ORM\Column(name="default_search", type="boolean", options={"default" : false})
     */
    private $default;

    /**
     * @ORM\OneToOne(targetEntity="EMS\CoreBundle\Entity\ContentType", cascade={})
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private $contentType;

    /**
     * @ORM\Column(name="sort_by", type="string", length=100, nullable=true)
     */
    public ?string $sortBy = null;

    /**
     * @ORM\Column(name="sort_order", type="string", length=100, nullable=true)
     */
    public ?string $sortOrder = null;

    /**
     * @var int
     *
     * @ORM\Column(name="minimum_should_match", type="integer", options={"default" : 1})
     */
    protected $minimumShouldMatch;

    public function __construct()
    {
        $this->filters = [];
        $this->filters[] = new SearchFilter();
        $this->default = false;
        $this->minimumShouldMatch = 1;
    }

    public function jsonSerialize()
    {
        $out = [
            'environments' => $this->environments,
            'contentTypes' => $this->contentTypes,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'minimumShouldMatch' => $this->minimumShouldMatch,
        ];

        $out['filters'] = [];
        /** @var SearchFilter $filter */
        foreach ($this->filters as $filter) {
            $out['filters'][] = $filter->jsonSerialize();
        }

        return $out;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set user.
     *
     * @param string $user
     *
     * @return Search
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Search
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add filter.
     *
     * @return Search
     */
    public function addFilter(SearchFilter $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function resetFilters(): Search
    {
        $filters = [];
        foreach ($this->filters as $filter) {
            $filters[] = $filter;
        }
        $this->filters = $filters;

        return $this;
    }

    /**
     * Remove filter.
     */
    public function removeFilter(SearchFilter $filter)
    {
        $this->filters = \array_diff($this->filters, [$filter]);
    }

    /**
     * Get filters.
     *
     * @return SearchFilter[]
     */
    public function getFilters()
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
     * Set environments.
     *
     * @param array $environments
     *
     * @return Search
     */
    public function setEnvironments($environments)
    {
        $this->environments = $environments;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getEnvironments(): array
    {
        return $this->environments ?? [];
    }

    /**
     * Set contentTypes.
     *
     * @param array $contentTypes
     *
     * @return Search
     */
    public function setContentTypes($contentTypes)
    {
        $this->contentTypes = $contentTypes;

        return $this;
    }

    /**
     * Get contentTypes.
     *
     * @return array
     */
    public function getContentTypes()
    {
        return $this->contentTypes;
    }

    /**
     * Set default.
     *
     * @param bool $default
     *
     * @return Search
     */
    public function setDefault($default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Get default.
     *
     * @return bool
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return mixed
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param mixed $contentType
     *
     * @return Search
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;

        return $this;
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
        if ($liveSearch && \strlen($pattern) > 0 && !\in_array(\substr($pattern, -1), [' ', '?', '*', '.', '/'])) {
            $queryString .= '*';
        }

        $filters = [];
        foreach ($this->getFilters() as &$filter) {
            if (empty($filter->getPattern())) {
                if (\in_array($filter->getOperator(), ['query_and', 'query_or'])) {
                    $filter->setPattern($queryString);
                } else {
                    $filter->setPattern($pattern);
                }
            }
            $filters[] = $filter;
        }
        $this->filters = $filters;
    }
}
