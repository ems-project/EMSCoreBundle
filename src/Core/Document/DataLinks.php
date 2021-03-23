<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Document;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Response\ResponseInterface;
use EMS\CoreBundle\Entity\ContentType;
use Symfony\Component\HttpFoundation\Request;

final class DataLinks
{
    /** @var ContentType[] */
    private array $contentTypes = [];
    /** @var array<mixed> */
    private array $items = [];
    private int $total = 0;

    private int $page;
    private string $pattern;
    private ?string $locale;
    /** @var string[] */
    private array $types = [];

    private const SIZE = 30;

    public function __construct(Request $request, ContentType ...$contentTypes)
    {
        $query = $request->query;

        $this->page = \intval($query->get('page', 1));
        $this->pattern = \strval($query->get('q', ''));
        $this->locale = $query->has('locale') ? \strval($query->get('locale')) : null;

        if (null !== $types = $query->get('type', null)) {
            $this->types = \explode(',', $types);
        }

        foreach ($contentTypes as $contentType) {
            $this->contentTypes[$contentType->getName()] = $contentType;
        }
    }

    public function add(string $id, string $text): self
    {
        $this->items[] = ['id' => $id, 'text' => $text];

        return $this;
    }

    public function addSearchResponse(ResponseInterface $response): void
    {
        $this->total = $response->getTotal();

        foreach ($response->getDocuments() as $document) {
            $this->addDocument($document);
        }
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

    public function getSize(): int
    {
        return self::SIZE;
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function addDocument(DocumentInterface $document): void
    {
        $item = [
            'id' => $document->getEmsId(),
            'type' => $document->getContentType(),
            'ouuid' => $document->getId(),
        ];

        if (null === $contentType = ($this->contentTypes[$document->getContentType()] ?? null)) {
            return;
        }

        $source = $document->getSource();
        if ($contentType->hasColorField() && isset($source[$contentType->giveColorField()])) {
            $item['color'] = $source[$contentType->giveColorField()];
        }

        if ($contentType->hasLabelField() && isset($source[$contentType->giveLabelField()])) {
            $text = $source[$contentType->giveLabelField()];
        } else {
            $text = $document->getId();
        }

        $icon = $contentType->getIcon() ?? 'fa fa-question';
        $item['text'] = \sprintf('<i class="%s"></i> %s', $icon, $text);

        $this->items[] = $item;
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
