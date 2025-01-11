<?php

namespace EMS\CoreBundle\Entity\Form;

use EMS\CommonBundle\Entity\IdentifierIntegerTrait;

class SearchFilter implements \JsonSerializable
{
    use IdentifierIntegerTrait;

    private ?Search $search = null;
    public ?string $pattern = null;
    public ?string $field = null;
    public ?string $booleanClause = 'must';
    public string $operator = 'query_and';
    public ?string $boost = null;

    public function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
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
                            $field ?: '_all' => [
                                'query' => $this->pattern ?? '',
                                'operator' => 'AND',
                                'boost' => $this->boost ?? '1.0',
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
                                'query' => $this->pattern ?: '',
                                'operator' => 'OR',
                                'boost' => $this->boost ?? '1.0',
                            ],
                        ],
                    ];
                    break;
                case 'query_and':
                    $out = [
                        'query_string' => [
                            'query' => $this->pattern ?? '*',
                            'default_operator' => 'AND',
                            'boost' => $this->boost ?? '1.0',
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
                            'boost' => $this->boost ?? '1.0',
                        ],
                    ];
                    if (!empty($field)) {
                        $out['query_string']['default_field'] = $field;
                    }
                    break;
                case 'term':
                    $out = [
                        'term' => [
                            $field ?: '_all' => [
                                'value' => $this->pattern ?? '*',
                                'boost' => $this->boost ?? '1.0',
                            ],
                        ],
                    ];
                    break;
                case 'prefix':
                    $out = [
                        'prefix' => [
                            $field ?: '_all' => [
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

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(?string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function getBoost(): ?string
    {
        return $this->boost;
    }

    public function setBoost(?string $boost): self
    {
        $this->boost = $boost ? (string) $boost : null;

        return $this;
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

    public function setBooleanClause(?string $booleanClause): self
    {
        $this->booleanClause = $booleanClause;

        return $this;
    }

    public function getBooleanClause(): ?string
    {
        return $this->booleanClause;
    }
}
