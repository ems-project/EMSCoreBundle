<?php
namespace EMS\CoreBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Contains information to update 2 side links between objects
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class UpdateRevisionReferersEvent extends Event
{
    const NAME = 'ems_core.revision.update_referers';
    
    private $targetField;
    private $id;
    private $toClean;
    private $toCreate;
    private $type;
    
    /**
     * 
     * 
     * @param sting $targetField
     * @param array $toCleanOuuids
     * @param array $toCreateOuuids
     */
    public function __construct($type, $id, $targetField, array $toCleanOuuids, array $toCreateOuuids) {
        $this->type = $type;
        $this->id = $id;
        $this->targetField = $targetField;
        $this->toClean = $toCleanOuuids;
        $this->toCreate = $toCreateOuuids;
    }
    
    /**
     * Return the name of the computed field where the back link is store
     * @return string
     */
     public function getTargetField() {
        return $this->targetField;
    }
    
    
    /**
     * List of UUIDs where the back link should be removed
     * @return array
     */
    public function getToCleanOuuids() {
        return array_diff($this->toClean, $this->toCreate);
    }
    
    /**
     * List of UUIDs where the back link should be added
     * @return array
     */
    public function getToCreateOuuids() {
        return array_diff($this->toCreate, $this->toClean);
    }
    
    /**
     * Type of the object triggering the event
     * @return string
     */
    public function getType() {
        return $this->type;
    }
    
    /**
     * Id of the object triggering the event
     * @return string
     */
    public function getId() {
        return $this->id;
    }
    
    public function getRefererOuuid() {
        return $this->getType().':'.$this->getId();
    }
}