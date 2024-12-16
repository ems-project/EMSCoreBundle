<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\FieldType;

use Doctrine\Common\Collections\ArrayCollection;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Repository\FieldTypeRepository;

class FieldTypeService
{
    /** @var ?ArrayCollection<int, FieldType> */
    private ?ArrayCollection $fieldTypes = null;

    public function __construct(
        private readonly FieldTypeRepository $fieldTypeRepository,
    ) {
    }

    public function getTree(ContentType $contentType): FieldTypeTreeItem
    {
        return new FieldTypeTreeItem($contentType->getFieldType(), $this->getFieldTypes());
    }

    /**
     * @return ArrayCollection<int, FieldType>
     */
    private function getFieldTypes(): ArrayCollection
    {
        if (null === $this->fieldTypes) {
            $this->fieldTypes = new ArrayCollection($this->fieldTypeRepository->findBy(['deleted' => false]));
        }

        return $this->fieldTypes;
    }
}
