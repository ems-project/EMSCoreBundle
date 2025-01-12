<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Service\Mapping;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Json;

class AggregateOption
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private string $name;
    private string $config;
    private string $template;
    private int $orderKey = 0;
    private string $icon;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');

        $this->config = '{
    "terms" : { "field" : "'.Mapping::FINALIZED_BY_FIELD.'" }
}';
        $this->template = '{% set fieldName = \''.Mapping::FINALIZED_BY_FIELD.'\' %}
{% if aggregation.buckets|length > 1  %}

	{% for index in aggregation.buckets %}
		{% set filters = currentFilters.all.search_form.filters|merge({ (1000+id) : {\'operator\': \'term\', \'booleanClause\': \'must\', \'field\': fieldName, \'pattern\': index.key, \'boost\': \'\'}}) %}
		{% set search_form = currentFilters.all.search_form|merge({\'filters\':filters}) %}
		{% set facettedSearch = currentFilters.all|merge({\'search_form\': search_form}) %}
		<a href="{{ path(paginationPath, facettedSearch) }}" class="btn btn-block btn-social btn-default">
			<i class="fa fa-user"></i>
			{{ index.key|emsco_display_name }}
			<span class=" badge pull-right">{{ index.doc_count }}</span>
		</a>
	{% endfor %}
{% elseif aggregation.buckets|length  == 1 and currentFilters.all.search_form.filters is defined %}
	{% set filters = {} %}
	{% set filterFound = false %}
    {% for idx, filter in currentFilters.all.search_form.filters %}
		{% if filter.operator == \'term\' and filter.field == fieldName and filter.pattern == aggregation.buckets[0].key %}
            {% set filterFound = true %}
	    {% else %}
	        {% set filters = filters|merge({ (idx) : filter}) %}
		{% endif %}
    {% endfor %}
	{% if filterFound %}
	   	{% set search_form = currentFilters.all.search_form|merge({\'filters\':filters}) %}
		{% set facettedSearch = currentFilters.all|merge({\'search_form\': search_form}) %}
		<a href="{{ path(paginationPath, facettedSearch) }}" class="btn btn-block btn-social btn-default">
			<i class="fa fa-remove"></i>
			Remove facet "{{ aggregation.buckets[0].key|emsco_display_name }}"
		</a>
	{% else %}
		{% set filters = currentFilters.all.search_form.filters|merge({ (1000+id) : {\'operator\': \'term\', \'booleanClause\': \'must\', \'field\': fieldName, \'pattern\': aggregation.buckets[0].key, \'boost\': \'\'}}) %}
		{% set search_form = currentFilters.all.search_form|merge({\'filters\':filters}) %}
		{% set facettedSearch = currentFilters.all|merge({\'search_form\': search_form}) %}
		<a href="{{ path(paginationPath, facettedSearch) }}" class="btn btn-block btn-social btn-default">
			<i class="fa fa-user"></i>
			{{ aggregation.buckets[0].key|emsco_display_name }}
			<span class=" badge pull-right">{{ aggregation.buckets[0].doc_count }}</span>
		</a>
	{% endif %}
{% endif %}';
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return AggregateOption
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
     * Set config.
     *
     * @param string $config
     *
     * @return AggregateOption
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get template.
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set template.
     *
     * @param string $template
     *
     * @return AggregateOption
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get config.
     *
     * @return string
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Decode config.
     *
     * @see https://github.com/elastic/elasticsearch-php/issues/660
     *
     * @return array<mixed>
     */
    public function getConfigDecoded(): array
    {
        $recursiveCheck = function (array &$json) use (&$recursiveCheck) {
            foreach ($json as $field => &$data) {
                if ('reverse_nested' === $field && empty($data)) {
                    $data = new \stdClass();
                } elseif (\is_array($data)) {
                    $recursiveCheck($data);
                }
            }
        };

        $json = Json::decode($this->config);
        $recursiveCheck($json);

        return $json;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return AggregateOption
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey.
     *
     * @return int
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    /**
     * Set icon.
     *
     * @param string $icon
     *
     * @return AggregateOption
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }
}
