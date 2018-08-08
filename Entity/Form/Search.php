<?php
namespace EMS\CoreBundle\Entity\Form;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * Search
 *
 * @ORM\Table(name="search")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SearchRepository")
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
	 * @var SearchFilter $filters
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
	 * @var string $sortBy
	 * 
     * @ORM\Column(name="sort_by", type="string", length=100, nullable=true)
	 */
	public $sortBy;
	
	/**
	 * @var string $sortOrder
	 * 
     * @ORM\Column(name="sort_order", type="string", length=100, nullable=true)
	 */
	public $sortOrder;
	
	
	function __construct(){
		$this->filters = [];//new \Doctrine\Common\Collections\ArrayCollection();
		$this->filters[] = new SearchFilter();
		$this->default = false;
// 		$this->page = 1;
// 		$this->boolean = "and";
	}



    public function jsonSerialize()
    {
        $out = [
            'environments' => $this->environments,
            'contentTypes' => $this->contentTypes,
            'sortBy' => $this->sortBy,
        ];

        $out['filters'] = [];
        /**@var SearchFilter $filter*/
        foreach ($this->filters as $filter){
            $out['filters'][] = $filter->jsonSerialize();
        }
        return $out;
    }
	

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set user
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
     * Get user
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set name
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
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add filter
     *
     * @param \EMS\CoreBundle\Entity\Form\SearchFilter $filter
     *
     * @return Search
     */
    public function addFilter(\EMS\CoreBundle\Entity\Form\SearchFilter $filter)
    {
    	$this->filters[] = $filter;
    	
    	return $this;
    }
    
    public function resetFilters(array $filters)
    {
    	$this->filters = $filters;
    	
    	return $this;
    }

    /**
     * Remove filter
     *
     * @param \EMS\CoreBundle\Entity\Form\SearchFilter $filter
     */
    public function removeFilter(\EMS\CoreBundle\Entity\Form\SearchFilter $filter)
    {
        $this->filters->removeElement($filter);
    }

    /**
     * Get filters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Set sortBy
     *
     * @param string $sortBy
     *
     * @return Search
     */
    public function setSortBy($sortBy)
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    /**
     * Get sortBy
     *
     * @return string
     */
    public function getSortBy()
    {
        return $this->sortBy;
    }

    /**
     * Set sortOrder
     *
     * @param string $sortOrder
     *
     * @return Search
     */
    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * Get sortOrder
     *
     * @return string
     */
    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    /**
     * Set environments
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
     * Get environments
     *
     * @return array
     */
    public function getEnvironments()
    {
        return $this->environments;
    }
    
    /**
     * Set contentTypes
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
     * Get contentTypes
     *
     * @return array
     */
    public function getContentTypes()
    {
    	return $this->contentTypes;
    }
    
    /**
     * Set default
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
     * Get default
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
     * @return Search
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }


}
