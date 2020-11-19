<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Service\Mapping;

/**
 * DataField.
 *
 * @ORM\Table(name="aggregate_option")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\AggregateOptionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class AggregateOption
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="config", type="text", nullable=true)
     */
    private $config;

    /**
     * @var string
     *
     * @ORM\Column(name="template", type="text", nullable=true)
     */
    private $template;

    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    private $orderKey;

    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="text", length=255, nullable=true)
     */
    private $icon;

    public function __construct()
    {
        $this->config = '{
    "terms" : { "field" : "'.Mapping::FINALIZED_BY_FIELD.'" }
}';
        $this->template = '{% set fieldName = \''.Mapping::FINALIZED_BY_FIELD.'\' %}
{% if aggregation.buckets|length > 1  %}

	{% for index in aggregation.buckets %}
		{% set filters = currentFilters.all.search_form.filters|merge({ (1000+id) : {\'operator\': \'term\', \'booleanClause\': \'must\', \'field\': fieldName, \'pattern\': index.key, \'boost\': \'\'}}) %}
		{% set search_form = currentFilters.all.search_form|merge({\'filters\':filters}) %}
		{% set facettedSearch = currentFilters.all|merge({\'search_form\': search_form}) %}
		<a href="{{ path(paginationPath, facettedSearch) }}" class="btn btn-block btn-social btn-primary">
			<i class="fa fa-user"></i>
			{{ index.key|displayname }}
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
		<a href="{{ path(paginationPath, facettedSearch) }}" class="btn btn-block btn-social btn-primary">
			<i class="fa fa-remove"></i>
			Remove facet "{{ aggregation.buckets[0].key|displayname }}"
		</a>
	{% else %}
		{% set filters = currentFilters.all.search_form.filters|merge({ (1000+id) : {\'operator\': \'term\', \'booleanClause\': \'must\', \'field\': fieldName, \'pattern\': aggregation.buckets[0].key, \'boost\': \'\'}}) %}
		{% set search_form = currentFilters.all.search_form|merge({\'filters\':filters}) %}
		{% set facettedSearch = currentFilters.all|merge({\'search_form\': search_form}) %}
		<a href="{{ path(paginationPath, facettedSearch) }}" class="btn btn-block btn-social btn-primary">
			<i class="fa fa-user"></i>
			{{ aggregation.buckets[0].key|displayname }}
			<span class=" badge pull-right">{{ aggregation.buckets[0].doc_count }}</span>
		</a>
	{% endif %}
{% endif %}';
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
        if (!isset($this->orderKey)) {
            $this->orderKey = 0;
        }
    }

    /******************************************************************
     *
     * Generated functions
     *
     *******************************************************************/

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
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return AggregateOption
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return AggregateOption
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
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

        $json = \json_decode($this->config, true);
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
