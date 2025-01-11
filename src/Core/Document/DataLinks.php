<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Document;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Entity\ContentType;

final class DataLinks
{
    private const int SIZE = 30;

    /** @var ContentType[] */
    private array $contentTypes = [];
    /** @var array<mixed> */
    private array $items = [];
    private ?string $locale = null;
    private ?string $querySearchName = null;
    private ?Document $referrerDocument = null;
    private ?int $searchId = null;
    private int $total = 0;

    private bool $customViewRendered = false;

    public function __construct(private readonly int $page, private readonly string $pattern)
    {
    }

    public function add(string $id, string $text): self
    {
        $this->items[] = ['id' => $id, 'text' => $text];

        return $this;
    }

    public function addContentTypes(ContentType ...$contentTypes): void
    {
        foreach ($contentTypes as $contentType) {
            $this->contentTypes[$contentType->getName()] = $contentType;
        }
    }

    public function addDocument(DocumentInterface $document, string $displayLabel): void
    {
        $item = [
            'id' => $document->getEmsId(),
            'type' => $document->getContentType(),
            'ouuid' => $document->getId(),
        ];

        $contentType = $this->contentTypes[$document->getContentType()] ?? null;
        if (null === $contentType && '' === $document->getContentType()) {
            return;
        }

        $source = $document->getSource();
        if ($contentType && $contentType->hasColorField() && isset($source[$contentType->giveColorField()])) {
            $item['color'] = $source[$contentType->giveColorField()];
        }

        if ($contentType && $contentType->getIcon()) {
            $icon = $contentType->getIcon();
            $tooltipField = $contentType->field(ContentTypeFields::TOOLTIP);

            if ($tooltipField && $tooltip = $document->getValue($tooltipField, false)) {
                $item['tooltip'] = $tooltip;
            }
        } else {
            $icon = ($contentType) ? 'fa fa-question' : 'fa fa-external-link-square';
        }

        $item['text'] = \sprintf('<i class="%s"></i> %s', $icon, $displayLabel);
        $item['title'] = $displayLabel;

        $this->items[] = $item;
    }

    public function customViewRendered(): void
    {
        $this->customViewRendered = true;
    }

    /**
     * @return string[]
     */
    public function getContentTypeNames(): array
    {
        return \array_values(\array_map(fn (ContentType $contentType) => $contentType->getName(), $this->contentTypes));
    }

    /**
     * @return ContentType[]
     */
    public function getContentTypes(): array
    {
        return $this->contentTypes;
    }

    public function getFrom(): int
    {
        return ($this->page - 1) * self::SIZE;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getQuerySearchName(): string
    {
        if (null == $this->querySearchName) {
            throw new \RuntimeException('DataLink has no query search name');
        }

        return $this->querySearchName;
    }

    public function getReferrerDocument(): Document
    {
        if (null === $this->referrerDocument) {
            throw new \RuntimeException('No referrer document');
        }

        return $this->referrerDocument;
    }

    public function getSearchId(): ?int
    {
        return $this->searchId;
    }

    public function getSize(): int
    {
        return self::SIZE;
    }

    public function hasCustomViewRendered(): bool
    {
        return $this->customViewRendered;
    }

    public function hasItems(): bool
    {
        return \count($this->items) > 0;
    }

    public function hasReferrerDocument(): bool
    {
        return null !== $this->referrerDocument;
    }

    public function isQuerySearch(): bool
    {
        return null !== $this->querySearchName;
    }

    public function isSearch(): bool
    {
        return null !== $this->searchId;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function setQuerySearchName(?string $querySearchName): void
    {
        if ('' === $querySearchName) {
            return;
        }

        $this->querySearchName = $querySearchName;
    }

    public function setReferrerDocument(?Document $referrerDocument): void
    {
        $this->referrerDocument = $referrerDocument;
    }

    public function setSearchId(?int $searchId): void
    {
        $this->searchId = (0 !== $searchId ? $searchId : null);
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    /**
     * @return array{total_count: int, incomplete_results: bool, items: array<mixed>}
     */
    public function toArray(): array
    {
        return [
            'total_count' => $this->total,
            'incomplete_results' => $this->total !== \count($this->items),
            'items' => $this->items,
        ];
    }
}
