<?php
namespace EMS\CoreBundle\Entity\Form;

use Doctrine\ORM\Mapping as ORM;

/**
 * SearchFilter
 *
 * @ORM\Table(name="search_filter")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SearchFilterRepository")
 */
class SearchFilter
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
	private $search;
	
	/**
	 * @var string $pattern
	 * 
     * @ORM\Column(name="pattern", type="string", length=200, nullable=true)
	 */
	private $pattern;
	
	/**
	 * @var string $field
	 * 
     * @ORM\Column(name="field", type="string", length=100, nullable=true)
	 */
	private $field;
	
	/**
	 * @var string $booleanClause
	 * 
     * @ORM\Column(name="boolean_clause", type="string", length=20, nullable=true)
	 */
	private $booleanClause;
	
	/**
	 * @var string $operator
	 * 
     * @ORM\Column(name="operator", type="string", length=50)
	 */
	private $operator;
	
	/**
	 * @var float $boost
	 * 
     * @ORM\Column(name="boost", type="decimal", scale=2, nullable=true)
	 */
	private $boost;
	
	function __construct(){
		$this->operator = "query_and";
	}
	
	function generateEsFilter(){
		$out = false;
		if($this->field || $this->pattern) {
			
			$field = $this->field;
// 			if($this->field){
// 				$path = explode('.', $this->field);
// 				$field = $path[count($path)-1];
// 			}
			
			
			switch ($this->operator){
				case 'match_and':
					$out = [
						"match" => [
								$field?$field:"_all" => [
								"query" =>  $this->pattern?$this->pattern:"",
								"operator" => "AND",
								"boost" => $this->boost?$this->boost:1,
							]
						]
					];
					break;
				case 'match_or':
					$out = [
						"match" => [
								$field?$field:"_all" => [
								"query" =>  $this->pattern?$this->pattern:"",
								"operator" => "OR",
								"boost" => $this->boost?$this->boost:1,
							]
						]
					];
					break;
				case 'query_and':
					$out = [
						"query_string" => [
							"default_field" => $field?$field:"_all",
							"query" =>  $this->pattern?$this->pattern:"*",
							"default_operator" => "AND",
							"boost" => $this->boost?$this->boost:1,
						]
					];
					break;
				case 'query_or':
					$out = [
						"query_string" => [
							"default_field" => $field?$field:"_all",
							"query" =>  $this->pattern?$this->pattern:"*",
							"default_operator" => "OR",
							"boost" => $this->boost?$this->boost:1,
						]
					];
					break;
				case 'term':
					$out = [
						"term" => [
							$field?$field:"_all" => [
								"value" => $this->pattern?$this->pattern:"*",
								"boost" => $this->boost?$this->boost:1,
							]
						]
					];
					break;
			}
			
			if($this->field){
				$path = explode('.', $this->field);
				for($i=count($path)-2; $i >= 0; --$i){
					$out = [
						"nested" => [
								"path" => $path[$i],
								"query" => $out,
						]
					];
				}
			}
		}		
		
		return $out;
	}

    /**
     * Get pattern
     *
     * @return string
     */
	public function getPattern(){
		return $this->pattern;
	}	
	/**
	 * Set pattern
	 * 
	 * @param string $pattern
	 */
	public function setPattern($pattern){
		$this->pattern = $pattern;
		return $this;
	}

    /**
     * Get field
     *
     * @return string
     */
	public function getField(){
		return $this->field;
	}	
	/**
	 * Set field
	 * 
	 * @param string $field
	 */
	public function setField($field){
		$this->field = $field;
		return $this;
	}

	/**
	 * Get operator
	 *
	 * @return string
	 */
	public function getOperator(){
		return $this->operator;
	}
	/**
	 * Set operator
	 *
	 * @param string $operator
	 */
	public function setOperator($operator){
		$this->operator = $operator;
		return $this;
	}

	/**
	 * Get boost
	 *
	 * @return float
	 */
	public function getBoost(){
		return $this->boost;
	}
	/**
	 * Set boost
	 *
	 * @param float $boost
	 */
	public function setBoost($boost){
		$this->boost = $boost;
		return $this;
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
     * Set search
     *
     * @param \EMS\CoreBundle\Entity\Form\Search $search
     *
     * @return SearchFilter
     */
    public function setSearch(\EMS\CoreBundle\Entity\Form\Search $search = null)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * Get search
     *
     * @return \EMS\CoreBundle\Entity\Form\Search
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * Set booleanClause
     *
     * @param boolean $booleanClause
     *
     * @return SearchFilter
     */
    public function setBooleanClause($booleanClause)
    {
        $this->booleanClause = $booleanClause;

        return $this;
    }

    /**
     * Get booleanClause
     *
     * @return boolean
     */
    public function getBooleanClause()
    {
        return $this->booleanClause;
    }
}
