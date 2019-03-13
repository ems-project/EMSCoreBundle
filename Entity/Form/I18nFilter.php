<?php
namespace EMS\CoreBundle\Entity\Form;

/**
 * I18nFilter
 */
class I18nFilter
{

    /**
     * @var identifier
     */
    private $identifier;
    

    /**
     * Set the identifier filter
     *
     * @param string  $identifier
     *
     * @return NotificationFilter
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    
        return $this;
    }
    
    /**
     * Get the selected identifier filter
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }
}
