<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Entity\ContentType;

class ObjectChoiceListItem
{

    /** @var string */
    private $label;
    /** @var null|string */
    private $title;
    /** @var string */
    private $value;
    /** @var null|string */
    private $group = null;
    /** @var null|string */
    private $color = null;
    
    
    public function __construct(Document $document, ?ContentType $contentType)
    {
        $source = $document->getSource();
        $this->value = $document->getEmsId();
        $icon = 'fa fa-question';
        $this->title = $this->value;

        if ($contentType !== null) {
            $labelField = $contentType->getLabelField();
            if ($labelField !== null) {
                $this->title = $source[$labelField] ?? $this->title;
            }
            $categoryField = $contentType->getCategoryField();
            if ($categoryField !== null) {
                $this->group = $source[$categoryField] ?? null;
            }
            $colorField = $contentType->getColorField();
            if ($colorField !== null) {
                $this->color = $source[$colorField] ?? null;
            }
            $contentTypeIcon = $contentType->getIcon();
            if ($contentTypeIcon !== null) {
                $icon = $contentTypeIcon;
            }
        }

        $this->label = \sprintf('<i class="%s" data-ouuid="%s"></i>&nbsp;&nbsp;%s', $icon, $this->value, $this->title);
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
    
    public function getGroup(): ?string
    {
        return $this->group;
    }
    
    public function getColor(): ?string
    {
        return $this->color;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}
