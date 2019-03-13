<?php
namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\View;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * RebuildIndex
 */
class CriteriaUpdateConfig
{

    private $columnCriteria;
    
    private $rowCriteria;

    private $category;
    
    private $criterion;
    
    /**@var Session */
    private $session;


    function __construct(View $view, Session $session)
    {
        
        $this->session = $session;
        $this->criterion = [];
        $contentType = $view->getContentType();
        
        $rootFieldType = $contentType->getFieldType();
        
        if (!empty($view->getOptions()['categoryFieldPath']) && $categoryField = $rootFieldType->getChildByPath($view->getOptions()['categoryFieldPath'])) {
            $dataField = new DataField();
            $dataField->setFieldType($categoryField);
            $this->setCategory($dataField);
        }
        
        $criteriaField = $rootFieldType;
        
        if ($view->getOptions()['criteriaMode'] == 'internal') {
            $criteriaField = $rootFieldType->__get('ems_'.$view->getOptions()['criteriaField']);
        } else if ($view->getOptions()['criteriaMode'] == 'another') {
        } else {
            throw new \Exception('Should never happen');
        }
        
        
        $fieldPaths = preg_split("/\\r\\n|\\r|\\n/", $view->getOptions()['criteriaFieldPaths']);
        
        foreach ($fieldPaths as $path) {
            $child = $criteriaField->getChildByPath($path);
            if ($child) {
                $dataField = new DataField();
                $dataField->setFieldType($child);
                $this->criterion[$child->getName()] = $dataField;
            } else {
                $this->session->getFlashBag()->add('warning', 'Field path not found '.$path);
            }
        }
    }
    
    /**
     * Set the column criteria field name
     *
     * @param string $columnCriteria
     *
     * @return CriteriaUpdateConfig
     */
    public function setColumnCriteria($columnCriteria)
    {
        $this->columnCriteria = $columnCriteria;
    
        return $this;
    }
    
    /**
     * Get the column criteria field name
     *
     * @return string
     */
    public function getColumnCriteria()
    {
        return $this->columnCriteria;
    }

    /**
     * Set the row criteria field name
     *
     * @param string $rowCriteria
     *
     * @return CriteriaUpdateConfig
     */
    public function setRowCriteria($rowCriteria)
    {
        $this->rowCriteria = $rowCriteria;
    
        return $this;
    }
    
    /**
     * Get the row criteria field name
     *
     * @return string
     */
    public function getRowCriteria()
    {
        return $this->rowCriteria;
    }

    /**
     * Set the category field type
     *
     * @param DataField $category
     *
     * @return CriteriaUpdateConfig
     */
    public function setCategory($category)
    {
        $this->category = $category;
    
        return $this;
    }
    
    /**
     * Get the category field
     *
     * @return DataField
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Add criterion
     *
     * @param DataField $criterion
     *
     * @return CriteriaUpdateConfig
     */
    public function addCriterion(DataField $criterion)
    {
        $this->criterion[$criterion->getFieldType()->getName()] = $criterion;
        return $this;
    }

    /**
     * Remove criterion
     *
     * @param DataField $criterion
     */
    public function removeCriterion(DataField $criterion)
    {
        if (isset($this->criterion[$criterion->getFieldType()->getName()])) {
            unset($this->criterion[$criterion->getFieldType()->getName()]);
        }
    }

    /**
     * Get filters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCriterion()
    {
        return $this->criterion;
    }
}
