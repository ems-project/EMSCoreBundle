<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\InlineEditor\Dto;

use EMS\CommonBundle\Common\EMSLink;

class InlineCollectionDto
{
    /** @var array<string, InlineElementDto[]> */
    public array $data = [];

    /**
     * @param array<string, array<mixed>> $collection
     */
    public function __construct(array $collection)
    {
        foreach ($collection as $emsId => $elements) {
            foreach ($elements as $element) {
                $this->data[$emsId][] = InlineElementDto::fromArray($element);
            }
        }
    }

    /**
     * @return EMSLink[]
     */
    public function getEMSLinks(): array
    {
        $emsIds = \array_keys($this->data);

        return \array_map(EMSLink::fromText(...), $emsIds);
    }
}
