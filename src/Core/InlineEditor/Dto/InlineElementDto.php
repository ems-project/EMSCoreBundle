<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\InlineEditor\Dto;

use EMS\CommonBundle\Common\EMSLink;

readonly class InlineElementDto
{
    public EMSLink $emsLink;

    public function __construct(
        public string $emsId,
        public string $path,
        public string $id,
        public string $tag,
        public string $selector,
    ) {
        $this->emsLink = EMSLink::fromText($this->emsId);
    }

    /**
     * @param array<mixed> $element
     */
    public static function fromArray(array $element): InlineElementDto
    {
        return new self(
            emsId: $element['emsId'],
            path: $element['path'],
            id: $element['id'],
            tag: $element['tag'],
            selector: $element['selector'],
        );
    }
}
