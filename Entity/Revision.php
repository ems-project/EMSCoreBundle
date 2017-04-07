<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Exception\NotLockedException;

/**
 * Revision
 *
 * @ORM\Table(name="revision", uniqueConstraints={@ORM\UniqueConstraint(name="tuple_index", columns={"end_time", "ouuid"})})
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\RevisionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Revision
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
     * @var \DateTime
     *
     * @ORM\Column(name="auto_save_at", type="datetime", nullable=true)
     */
    private $autoSaveAt;

    /**
     * @var bool
     *
     * @ORM\Column(name="deleted", type="boolean")
     */
    private $deleted;

    /**
     * @var ContentType
     *
     * @ORM\ManyToOne(targetEntity="ContentType")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private $contentType;
    
    private $dataField;
    
    /**
     * @var integer
     * 
     * @ORM\Column(name="version", type="integer")
     * @ORM\Version
     */
    private $version;
    
    /**
     * @var string
     *
     * @ORM\Column(name="ouuid", type="string", length=255, nullable=true)
     */
    private $ouuid;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start_time", type="datetime")
     */
    private $startTime;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end_time", type="datetime", nullable=true)
     */
    private $endTime;

    /**
     * @var bool
     *
     * @ORM\Column(name="draft", type="boolean")
     */
    private $draft;

    /**
     * @var string
     *
     * @ORM\Column(name="lock_by", type="string", length=255, nullable=true)
     */
    private $lockBy;

    /**
     * @var string
     *
     * @ORM\Column(name="auto_save_by", type="string", length=255, nullable=true)
     */
    private $autoSaveBy;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="lock_until", type="datetime", nullable=true)
     */
    private $lockUntil;

    /**
     * @ORM\ManyToMany(targetEntity="Environment", inversedBy="revisions", cascade={"persist"})
     * @ORM\JoinTable(name="environment_revision")
     */
    private $environments;

    /**
     * @ORM\OneToMany(targetEntity="Notification", mappedBy="revision", cascade={"persist", "remove"})
     * @ORM\OrderBy({"created" = "ASC"})
     */
    private $notifications;

    /**
     * @var array
     *
     * @ORM\Column(name="raw_data", type="json_array", nullable=true)
     */
    private $rawData;

    /**
     * @var array
     *
     * @ORM\Column(name="auto_save", type="json_array", nullable=true)
     */
    private $autoSave;

    /**
     * @var array
     *
     * @ORM\Column(name="circles", type="simple_array", nullable=true)
     */
    private $circles;
    
     /**
     * @var string
     *
     * @ORM\Column(name="labelField", type="string", nullable=true)
     */
    private $labelField;
    
	/**not persisted field to ensure that they are all there after a submit */
    private $allFieldsAreThere;
    
    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
    	$this->modified = new \DateTime();
        if(!isset($this->created)){
    		$this->created = $this->modified;
    	}
    	if(!isset($this->orderKey)){
    		$this->orderKey = 0;
    	}
    	
    	if(null == $this->lockBy || null == $this->lockUntil || new \DateTime() > $this->lockUntil){
    		throw new NotLockedException($this);
    	}
    }
    
    
    public function buildObject(){
    	return [
    		'_id' => $this->ouuid,
    		'_type' => $this->contentType->getName(),
    		'_source' => $this->rawData
    	];
    }
    
    function __construct()
    {
    	$this->deleted = false;
    	$this->allFieldsAreThere = false;
    	//TODO? 
    	//$this->setStartTime(new \DateTime());
		//$this->setDraft(false);
    	$this->environments = new \Doctrine\Common\Collections\ArrayCollection();
    	$this->notifications = new \Doctrine\Common\Collections\ArrayCollection();
    	
    	$a = func_get_args();
    	$i = func_num_args();
    	if($i == 1){
    		if($a[0] instanceof Revision){
    			/** @var \Revision $ancestor */
    			$ancestor = $a[0];
    			$this->deleted = $ancestor->deleted;
    			$this->draft = true;
    			$this->allFieldsAreThere = $ancestor->allFieldsAreThere;
    			$this->ouuid = $ancestor->ouuid;
    			$this->contentType = $ancestor->contentType;
    			$this->rawData =  $ancestor->rawData;
    			$this->circles =  $ancestor->circles;
    			$this->dataField = new DataField($ancestor->dataField);
    		}
    	}
    	//TODO: Refactoring: Dependency injection of the first Datafield in the Revision.
    }
    
    public function __toString()
    {
    	$out = 'New instance';
    	if($this->ouuid){
    		$out = $this->ouuid;
    	}
    	if($this->contentType) {
    		$out = $this->contentType->getName().':'.$out;
    		if(!empty($this->id)) $out .=  '#'.$this->id;
    	}
    	
    	
    	if($this->contentType && $this->contentType->getLabelField() && $this->rawData && isset($this->rawData[$this->contentType->getLabelField()])){
    		return $this->rawData[$this->contentType->getLabelField()]." ($out)";
    	}
    	return $out;
    }

    public function getObject($object){
    	$object = [
    			'_index' => 'N/A',
    			'_source' => $object,
    			'_id' => $this->ouuid,
    			'_type' => $this->getContentType()->getName()
    	];
    	
    	return $object;
    }

    /**
     * Get allFieldAreThere
     *
     * @return bool
     */
    public function getAllFieldsAreThere()
    {
        return $this->allFieldsAreThere;
    }


    /**
     * 
     */
    public function setAllFieldsAreThere($allFieldsAreThere)
    {
    	$this->allFieldsAreThere = !empty($allFieldsAreThere);
    	return $this;
    }
    
    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return Revision
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return Revision
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set deleted
     *
     * @param boolean $deleted
     *
     * @return Revision
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set ouuid
     *
     * @param string $ouuid
     *
     * @return Revision
     */
    public function setOuuid($ouuid)
    {
        $this->ouuid = $ouuid;

        return $this;
    }

    /**
     * Get ouuid
     *
     * @return string
     */
    public function getOuuid()
    {
        return $this->ouuid;
    }

    /**
     * Set startTime
     *
     * @param \DateTime $startTime
     *
     * @return Revision
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get startTime
     *
     * @return \DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set endTime
     *
     * @param \DateTime $endTime
     *
     * @return Revision
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;

        return $this;
    }

    /**
     * Get endTime
     *
     * @return \DateTime
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * Set draft
     *
     * @param boolean $draft
     *
     * @return Revision
     */
    public function setDraft($draft)
    {
        $this->draft = $draft;

        return $this;
    }

    /**
     * Get draft
     *
     * @return bool
     */
    public function getDraft()
    {
        return $this->draft;
    }

    /**
     * Set lockBy
     *
     * @param string $lockBy
     *
     * @return Revision
     */
    public function setLockBy($lockBy)
    {
        $this->lockBy = $lockBy;

        return $this;
    }

    /**
     * Get lockBy
     *
     * @return string
     */
    public function getLockBy()
    {
        return $this->lockBy;
    }

    /**
     * Set lockUntil
     *
     * @param \DateTime $lockUntil
     *
     * @return Revision
     */
    public function setLockUntil($lockUntil)
    {
        $this->lockUntil = $lockUntil;

        return $this;
    }

    /**
     * Get lockUntil
     *
     * @return \DateTime
     */
    public function getLockUntil()
    {
        return $this->lockUntil;
    }

    /**
     * Set contentType
     *
     * @param \EMS\CoreBundle\Entity\ContentType $contentType
     *
     * @return Revision
     */
    public function setContentType(\EMS\CoreBundle\Entity\ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get contentType
     *
     * @return \EMS\CoreBundle\Entity\ContentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Set version
     *
     * @param integer $version
     *
     * @return Revision
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return integer
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set dataField
     *
     * @param \EMS\CoreBundle\Entity\DataField $dataField
     *
     * @return Revision
     */
    public function setDataField(\EMS\CoreBundle\Entity\DataField $dataField = null)
    {
        $this->dataField = $dataField;

        return $this;
    }

    /**
     * Get dataField
     *
     * @return \EMS\CoreBundle\Entity\DataField
     */
    public function getDataField()
    {
        return $this->dataField;
    }

    /**
     * Add environment
     *
     * @param \EMS\CoreBundle\Entity\Environment $environment
     *
     * @return Revision
     */
    public function addEnvironment(\EMS\CoreBundle\Entity\Environment $environment)
    {
        $this->environments[] = $environment;

        return $this;
    }

    /**
     * Remove environment
     *
     * @param \EMS\CoreBundle\Entity\Environment $environment
     */
    public function removeEnvironment(\EMS\CoreBundle\Entity\Environment $environment)
    {
        $this->environments->removeElement($environment);
    }

    /**
     * Get environments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEnvironments()
    {
        return $this->environments;
    }


    /**
     * Set rawData
     *
     * @param array $rawData
     *
     * @return Revision
     */
    public function setRawData($rawData)
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Get rawData
     *
     * @return array
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * Set autoSaveAt
     *
     * @param \DateTime $autoSaveAt
     *
     * @return Revision
     */
    public function setAutoSaveAt($autoSaveAt)
    {
        $this->autoSaveAt = $autoSaveAt;

        return $this;
    }

    /**
     * Get autoSaveAt
     *
     * @return \DateTime
     */
    public function getAutoSaveAt()
    {
        return $this->autoSaveAt;
    }

    /**
     * Set autoSaveBy
     *
     * @param string $autoSaveBy
     *
     * @return Revision
     */
    public function setAutoSaveBy($autoSaveBy)
    {
        $this->autoSaveBy = $autoSaveBy;

        return $this;
    }

    /**
     * Get autoSaveBy
     *
     * @return string
     */
    public function getAutoSaveBy()
    {
        return $this->autoSaveBy;
    }

    /**
     * Set autoSave
     *
     * @param array $autoSave
     *
     * @return Revision
     */
    public function setAutoSave($autoSave)
    {
        $this->autoSave = $autoSave;

        return $this;
    }

    /**
     * Get autoSave
     *
     * @return array
     */
    public function getAutoSave()
    {
        return $this->autoSave;
    }

    /**
     * Set localField
     *
     * @param string$labelField
     *
     * @return Revision
     */
    public function setLabelField($labelField)
    {
    	$this->labelField = $labelField;
    
    	return $this;
    }
    
    /**
     * Get labelField
     *
     * @return string
     */
    public function getLabelField()
    {
    	return $this->labelField;
    }
    
    /**
     * Set circles
     *
     * @param array $circles
     *
     * @return Revision
     */
    public function setCircles($circles)
    {
    	$this->circles = $circles;
    
    	return $this;
    }
    
    /**
     * Get circles
     *
     * @return array
     */
    public function getCircles()
    {
    	return $this->circles;
    }

    /**
     * Add notification
     *
     * @param \EMS\CoreBundle\Entity\Notification $notification
     *
     * @return Revision
     */
    public function addNotification(\EMS\CoreBundle\Entity\Notification $notification)
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /**
     * Remove notification
     *
     * @param \EMS\CoreBundle\Entity\Notification $notification
     */
    public function removeNotification(\EMS\CoreBundle\Entity\Notification $notification)
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * Get notifications
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }
}
