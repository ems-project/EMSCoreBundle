<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Entity\ContentType;

class ObjectChoiceListItem
{
    /** @var string */
    private $label;
    /** @var string|null */
    private $title;
    /** @var string */
    private $value;
    /** @var string|null */
    private $group = null;
    /** @var string|null */
    private $color = null;

    public function __construct(Document $document, ?ContentType $contentType)
    {
        $source = $document->getSource();
        $this->value = $document->getEmsId();
        $icon = 'fa fa-question';
        $this->title = $this->value;

        if (null !== $contentType) {
            $labelField = $contentType->getLabelField();
            if (null !== $labelField && isset($source[$labelField])) {
                $this->title = \strval($source[$labelField]);
            }
            $categoryField = $contentType->getCategoryField();
            if (null !== $categoryField && isset($source[$categoryField])) {
                $this->group = \strval($source[$categoryField]);
            }
            $colorField = $contentType->getColorField();
            if (null !== $colorField && isset($source[$colorField])) {
                $this->color = \strval($source[$colorField]);
            }
            $contentTypeIcon = $contentType->getIcon();
            if (null !== $contentTypeIcon) {
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
