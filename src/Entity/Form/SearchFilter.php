<?php

namespace EMS\CoreBundle\Entity\Form;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * SearchFilter.
 *
 * @ORM\Table(name="search_filter")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SearchFilterRepository")
 */
class SearchFilter implements JsonSerializable
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
     * @ORM\ManyToOne(targetEntity="Search", inversedBy="filters")
     * @ORM\JoinColumn(name="search_id", referencedColumnName="id")
     */
    private ?Search $search = null;

    /**
     * @var string
     *
     * @ORM\Column(name="pattern", type="string", length=200, nullable=true)
     */
    public $pattern;

    /**
     * @var string
     *
     * @ORM\Column(name="field", type="string", length=100, nullable=true)
     */
    public $field;

    /**
     * @var string
     *
     * @ORM\Column(name="boolean_clause", type="string", length=20, nullable=true)
     */
    public $booleanClause;

    /**
     * @var string
     *
     * @ORM\Column(name="operator", type="string", length=50)
     */
    public $operator;

    /**
     * @var float
     *
     * @ORM\Column(name="boost", type="decimal", scale=2, nullable=true)
     */
    public $boost;

    public function __construct()
    {
        $this->operator = 'query_and';
        $this->booleanClause = 'must';
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize()
    {
        return [
            'pattern' => $this->pattern,
            'field' => $this->field,
            'booleanClause' => $this->booleanClause,
            'operator' => $this->operator,
            'boost' => $this->boost,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function generateEsFilter(): ?array
    {
        $out = null;
        if ($this->field || $this->pattern) {
            $field = $this->field;

            switch ($this->operator) {
                case 'match_and':
                    $out = [
                        'match' => [
                            $field ? $field : '_all' => [
                                'query' => $this->pattern ?? '',
                                'operator' => 'AND',
                                'boost' => $this->boost ?? 1,
                            ],
                        ],
                    ];
                    break;
                case 'match_phrase':
                    $out = [
                        'match_phrase' => [
                            $field ?? '_all' => $this->pattern ?? '',
                        ],
                    ];
                    break;
                case 'match_phrase_prefix':
                    $out = [
                        'match_phrase_prefix' => [
                            $field ?? '_all' => ['query' => $this->pattern ?? ''],
                        ],
                    ];
                    break;
                case 'match_or':
                    $out = [
                        'match' => [
                                $field ?? '_all' => [
                                'query' => $this->pattern ? $this->pattern : '',
                                'operator' => 'OR',
                                'boost' => $this->boost ?? 1,
                                ],
                        ],
                    ];
                    break;
                case 'query_and':
                    $out = [
                        'query_string' => [
                            'query' => $this->pattern ?? '*',
                            'default_operator' => 'AND',
                            'boost' => $this->boost ?? 1,
                        ],
                    ];
                    if (!empty($field)) {
                        $out['query_string']['default_field'] = $field;
                    }
                    break;
                case 'query_or':
                    $out = [
                        'query_string' => [
                            'query' => $this->pattern ?? '*',
                            'default_operator' => 'OR',
                            'boost' => $this->boost ?? 1,
                        ],
                    ];
                    if (!empty($field)) {
                        $out['query_string']['default_field'] = $field;
                    }
                    break;
                case 'term':
                    $out = [
                        'term' => [
                            $field ? $field : '_all' => [
                                'value' => $this->pattern ?? '*',
                                'boost' => $this->boost ?? 1,
                            ],
                        ],
                    ];
                    break;
                case 'prefix':
                    $out = [
                        'prefix' => [
                            $field ? $field : '_all' => [
                                'value' => $this->pattern ?? '*',
                            ],
                        ],
                    ];
                    break;
                case 'lt':
                    $out = [
                        'range' => [
                            $field ?? '_all' => [
                                'lt' => $this->pattern ?? '*',
                            ],
                        ],
                    ];
                    break;
                case 'lte':
                    $out = [
                        'range' => [
                            $field ?? '_all' => [
                                'lte' => $this->pattern ?? '*',
                            ],
                        ],
                    ];
                    break;
                case 'gt':
                    $out = [
                        'range' => [
                            $field ?? '_all' => [
                                'gt' => $this->pattern ?? '*',
                            ],
                        ],
                    ];
                    break;
                case 'gte':
                    $out = [
                        'range' => [
                            $field ?? '_all' => [
                                'gte' => $this->pattern ?? '*',
                            ],
                        ],
                    ];
                    break;
            }
        }

        return $out;
    }

    /**
     * Get pattern.
     *
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @param string $pattern
     *
     * @return SearchFilter
     */
    public function setPattern($pattern)
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * Get field.
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Set field.
     *
     * @param string $field
     *
     * @return SearchFilter
     */
    public function setField($field)
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Get operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Set operator.
     *
     * @param string $operator
     */
    public function setOperator($operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * Get boost.
     *
     * @return float
     */
    public function getBoost()
    {
        return $this->boost;
    }

    /**
     * Set boost.
     *
     * @param float $boost
     */
    public function setBoost($boost): self
    {
        $this->boost = $boost;

        return $this;
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

    public function setSearch(?Search $search = null): self
    {
        $this->search = $search;

        return $this;
    }

    public function getSearch(): ?Search
    {
        return $this->search;
    }

    /**
     * Set booleanClause.
     *
     * @param string $booleanClause
     *
     * @return SearchFilter
     */
    public function setBooleanClause($booleanClause)
    {
        $this->booleanClause = $booleanClause;

        return $this;
    }

    /**
     * Get booleanClause.
     *
     * @return string
     */
    public function getBooleanClause()
    {
        return $this->booleanClause;
    }
}
