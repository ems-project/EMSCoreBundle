<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ContentTypeJsonUpdate
{
    private ?UploadedFile $json = null;

    private bool $deleteExitingTemplates = false;

    private bool $deleteExitingViews = false;

    public function __construct()
    {
    }

    public function getJson(): ?UploadedFile
    {
        return $this->json;
    }

    public function setJson(UploadedFile $json): ContentTypeJsonUpdate
    {
        $this->json = $json;

        return $this;
    }

    public function isDeleteExitingTemplates(): bool
    {
        return $this->deleteExitingTemplates;
    }

    public function setDeleteExitingTemplates(bool $deleteExitingTemplates): ContentTypeJsonUpdate
    {
        $this->deleteExitingTemplates = $deleteExitingTemplates;

        return $this;
    }

    public function isDeleteExitingViews(): bool
    {
        return $this->deleteExitingViews;
    }

    public function setDeleteExitingViews(bool $deleteExitingViews): ContentTypeJsonUpdate
    {
        $this->deleteExitingViews = $deleteExitingViews;

        return $this;
    }
}
