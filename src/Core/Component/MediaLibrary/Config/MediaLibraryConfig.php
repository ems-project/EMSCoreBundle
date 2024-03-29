<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Config;

use EMS\CoreBundle\Core\Config\ConfigInterface;
use EMS\CoreBundle\Entity\ContentType;

class MediaLibraryConfig implements ConfigInterface
{
    public const DEFAULT_SEARCH_FILE_QUERY = [
        'bool' => [
            'minimum_should_match' => 1,
            'should' => [
                ['multi_match' => [
                    'fields' => ['live_search', 'live_search._2gram', 'live_search._3gram'],
                    'query' => '%query%',
                    'operator' => 'and',
                    'type' => 'bool_prefix',
                ]],
                ['query_string' => ['default_field' => '_all', 'query' => '%query%']],
                ['wildcard' => ['_all' => ['value' => '%query%']]],
            ],
        ],
    ];
    public const DEFAULT_SEARCH_SIZE = 100;
    /** @var array<string, mixed> */
    public array $context = [];
    /** @var array<mixed> */
    public array $defaultValue = [];
    public ?string $fieldPathOrder = null;
    /** @var array<mixed> */
    public array $searchFileQuery = [];
    /** @var array<mixed> */
    public array $searchQuery = [];
    public int $searchSize = self::DEFAULT_SEARCH_SIZE;
    public ?string $template = null;

    public function __construct(
        private readonly string $hash,
        private readonly string $id,
        public readonly ContentType $contentType,
        public readonly string $fieldPath,
        public readonly string $fieldFolder,
        public readonly string $fieldFile
    ) {
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
