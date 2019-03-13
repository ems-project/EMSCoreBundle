<?php
namespace EMS\CoreBundle\Entity\Form;



/**
 * TreatNotifications
 */
class TreatNotifications{

    private $reject;

    private $accept;
    
    private $response;
    
//     private $unpublishFrom;
    
    private $publishTo;
    
    private $notifications;
    
    
    function __construct() {
    }

//     public function getUnpublishFrom() {
//         return $this->unpublishFrom;
//     }
    
    public function getPublishTo() {
        return $this->publishTo;
    }
    
    public function getReject() {
        return $this->reject;
    }
    
    public function getAccept() {
        return $this->accept;
    }
    
    public function getResponse() {
        return $this->response;
    }
    
    public function getNotifications() {
        return $this->notifications;
    }
    
//     public function setUnpublishFrom($unpublishFrom) {
//         $this->unpublishFrom = $unpublishFrom;
//         return $this;
//     }
    
    public function setPublishTo($publishTo) {
        $this->publishTo = $publishTo;
        return $this;
    }
    
    public function setReject($reject) {
        $this->reject = $reject;
        return $this;
    }
    
    public function setAccept($accept) {
        $this->accept = $accept;
        return $this;
    }
    
    public function setResponse($response) {
        $this->response = $response;
        return $this;
    }
    
    public function setNotifications($notifications) {
        $this->notifications = $notifications;
        return $this;
    }
    
}