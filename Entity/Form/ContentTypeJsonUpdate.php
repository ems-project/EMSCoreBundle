<?php


namespace EMS\CoreBundle\Entity\Form;


use Symfony\Component\HttpFoundation\File\UploadedFile;

class ContentTypeJsonUpdate
{
    /** @var UploadedFile */
    private $json;

    public function getJson(): ?UploadedFile
    {
        return $this->json;
    }

    public function setJson(UploadedFile $json): ContentTypeJsonUpdate
    {
        $this->json = $json;
        return $this;
    }
}
