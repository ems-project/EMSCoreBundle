<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\File\File;
use Mcp\Exception\ToolCallException;
use Mcp\Server\Builder;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

final readonly class ElasticmsMcpToolAssetService extends AbstractElasticmsMcpToolService
{
    public function __construct(
        UserService $userService,
        private FileService $fileService,
        LoggerInterface $logger,
        LoggerInterface $auditLogger,
    ) {
        parent::__construct($userService, $logger, $auditLogger);
    }

    /**
     * @return array{algorithm:string}
     */
    public function currentStorageAlgorithm(): array
    {
        $toolName = 'current_storage_algorithm';

        return $this->wrapToolCall($toolName, [], fn (): array => [
            'algorithm' => $this->fileService->getAlgo(),
        ]);
    }

    /**
     * @return array{hash:string, name:string, type:string, size:int, algo:string, available:bool, uploaded:int, status:?string, user:string, chunkSize:int}
     */
    public function initAssetUpload(string $hash, int $size, string $name, string $type, ?string $algo = null): array
    {
        $toolName = 'init_asset_upload';
        $resolvedAlgo = $algo ?? $this->fileService->getAlgo();

        return $this->wrapToolCall($toolName, [
            'hash' => $hash,
            'size' => $size,
            'name' => $name,
            'type' => $type,
            'algo' => $resolvedAlgo,
        ], function () use ($hash, $size, $name, $type, $resolvedAlgo): array {
            if ('' === $hash || '' === $name || '' === $type || $size < 0) {
                throw new ToolCallException('Invalid asset upload initialization arguments.');
            }

            $uploadedAsset = $this->fileService->initUploadFile($hash, $size, $name, $type, $this->userService->getCurrentUser()->getUsername(), $resolvedAlgo);

            return $this->buildAssetUploadState($uploadedAsset);
        });
    }

    /**
     * @return array{hash:string, name:string, type:string, size:int, algo:string, available:bool, uploaded:int, status:?string, user:string, chunkSize:int}
     */
    public function uploadAssetChunk(string $hash, string $chunkBase64): array
    {
        $toolName = 'upload_asset_chunk';

        return $this->wrapToolCall($toolName, [
            'hash' => $hash,
        ], function () use ($hash, $chunkBase64): array {
            $chunk = \base64_decode($chunkBase64, true);
            if (!\is_string($chunk)) {
                throw new ToolCallException('The chunkBase64 argument must be a valid base64 string.');
            }
            if ('' === $hash) {
                throw new ToolCallException('The hash argument must not be empty.');
            }
            if (\strlen($chunk) > File::DEFAULT_CHUNK_SIZE) {
                throw new ToolCallException(\sprintf('Chunk size exceeds %d bytes.', File::DEFAULT_CHUNK_SIZE));
            }

            $uploadedAsset = $this->fileService->addChunk($hash, $chunk, $this->userService->getCurrentUser()->getUsername());

            return $this->buildAssetUploadState($uploadedAsset);
        });
    }

    /**
     * @return array{hash:string, name:string, type:string, size:int, algo:string, offset:int, requestedLength:int, bytesRead:int, nextOffset:int, eof:bool, chunkBase64:string}
     */
    public function downloadAssetChunk(string $hash, int $offset = 0, ?int $length = null): array
    {
        $toolName = 'download_asset_chunk';
        $resolvedLength = $length ?? File::DEFAULT_CHUNK_SIZE;

        return $this->wrapToolCall($toolName, [
            'hash' => $hash,
            'offset' => $offset,
            'length' => $resolvedLength,
        ], function () use ($hash, $offset, $resolvedLength): array {
            if ('' === $hash) {
                throw new ToolCallException('The hash argument must not be empty.');
            }
            if ($offset < 0) {
                throw new ToolCallException('The offset argument must be greater than or equal to 0.');
            }
            if ($resolvedLength < 1 || $resolvedLength > File::DEFAULT_CHUNK_SIZE) {
                throw new ToolCallException(\sprintf('The length argument must be between 1 and %d bytes.', File::DEFAULT_CHUNK_SIZE));
            }

            $stream = $this->fileService->getResource($hash);
            if (!$stream instanceof StreamInterface) {
                throw new ToolCallException(\sprintf('Asset "%s" was not found.', $hash));
            }

            $fileObject = $this->fileService->getFileObject($hash);
            $this->seekStream($stream, $offset);
            $chunk = $stream->read($resolvedLength);
            $bytesRead = \strlen($chunk);
            $nextOffset = $offset + $bytesRead;
            $size = (int) $fileObject['_size'];

            return [
                'hash' => $hash,
                'name' => (string) $fileObject['_name'],
                'type' => (string) $fileObject['_type'],
                'size' => $size,
                'algo' => (string) $fileObject['_algo'],
                'offset' => $offset,
                'requestedLength' => $resolvedLength,
                'bytesRead' => $bytesRead,
                'nextOffset' => $nextOffset,
                'eof' => $nextOffset >= $size,
                'chunkBase64' => \base64_encode($chunk),
            ];
        });
    }

    /**
     * @return array{hash:string, name:string, type:string, size:int, algo:string, fileObject:array{sha1:string, _hash:string, filesize:int, _size:int, filename:string, _name:string, mimetype:string, _type:string, _algo:string}}
     */
    public function getAssetInfo(string $hash): array
    {
        $toolName = 'get_asset_info';

        return $this->wrapToolCall($toolName, [
            'hash' => $hash,
        ], function () use ($hash): array {
            if ('' === $hash) {
                throw new ToolCallException('The hash argument must not be empty.');
            }

            $fileObject = $this->fileService->getFileObject($hash);

            return [
                'hash' => $hash,
                'name' => (string) $fileObject['_name'],
                'type' => (string) $fileObject['_type'],
                'size' => (int) $fileObject['_size'],
                'algo' => (string) $fileObject['_algo'],
                'fileObject' => $fileObject,
            ];
        });
    }

    public function addAssetTools(Builder $builder): void
    {
        $builder
            ->addTool(
                handler: $this->currentStorageAlgorithm(...),
                name: 'current_storage_algorithm',
                description: 'Return the currently configured storage hash algorithm for assets.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                    'additionalProperties' => false,
                ],
                outputSchema: $this->buildCurrentStorageAlgorithmSchema(),
            )
            ->addTool(
                handler: $this->initAssetUpload(...),
                name: 'init_asset_upload',
                description: \sprintf('Initialize or resume a chunked asset upload. Chunks must not exceed %d bytes and the hash must use the current storage algorithm.', File::DEFAULT_CHUNK_SIZE),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'hash' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'algo' => ['type' => 'string'],
                    ],
                    'required' => ['hash', 'size', 'name', 'type'],
                    'additionalProperties' => false,
                ],
                outputSchema: $this->buildAssetUploadStateSchema(),
            )
            ->addTool(
                handler: $this->uploadAssetChunk(...),
                name: 'upload_asset_chunk',
                description: \sprintf('Upload one asset chunk encoded as base64. The decoded chunk size must not exceed %d bytes.', File::DEFAULT_CHUNK_SIZE),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'hash' => ['type' => 'string'],
                        'chunkBase64' => ['type' => 'string'],
                    ],
                    'required' => ['hash', 'chunkBase64'],
                    'additionalProperties' => false,
                ],
                outputSchema: $this->buildAssetUploadStateSchema(),
            )
            ->addTool(
                handler: $this->downloadAssetChunk(...),
                name: 'download_asset_chunk',
                description: \sprintf('Download one asset chunk encoded as base64. The requested chunk length defaults to and must not exceed %d bytes.', File::DEFAULT_CHUNK_SIZE),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'hash' => ['type' => 'string'],
                        'offset' => ['type' => 'integer'],
                        'length' => ['type' => 'integer'],
                    ],
                    'required' => ['hash'],
                    'additionalProperties' => false,
                ],
                outputSchema: $this->buildAssetDownloadChunkSchema(),
            )
            ->addTool(
                handler: $this->getAssetInfo(...),
                name: 'get_asset_info',
                description: 'Return the metadata and file object of an uploaded asset.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'hash' => ['type' => 'string'],
                    ],
                    'required' => ['hash'],
                    'additionalProperties' => false,
                ],
                outputSchema: $this->buildAssetInfoSchema(),
            );
    }

    /**
     * @return array{hash:string, name:string, type:string, size:int, algo:string, available:bool, uploaded:int, status:?string, user:string, chunkSize:int}
     */
    private function buildAssetUploadState(object $uploadedAsset): array
    {
        if (!$uploadedAsset instanceof UploadedAsset) {
            throw new \RuntimeException('Unexpected uploaded asset type.');
        }

        return [
            'hash' => $uploadedAsset->getSha1(),
            'name' => $uploadedAsset->getName(),
            'type' => $uploadedAsset->getType(),
            'size' => $uploadedAsset->getSize(),
            'algo' => $uploadedAsset->getHashAlgo(),
            'available' => $uploadedAsset->getAvailable(),
            'uploaded' => $uploadedAsset->getUploaded(),
            'status' => $uploadedAsset->getStatus(),
            'user' => $uploadedAsset->getUser(),
            'chunkSize' => File::DEFAULT_CHUNK_SIZE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssetUploadStateSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hash' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'type' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
                'algo' => ['type' => 'string'],
                'available' => ['type' => 'boolean'],
                'uploaded' => ['type' => 'integer'],
                'status' => ['type' => ['string', 'null']],
                'user' => ['type' => 'string'],
                'chunkSize' => ['type' => 'integer'],
            ],
            'required' => ['hash', 'name', 'type', 'size', 'algo', 'available', 'uploaded', 'status', 'user', 'chunkSize'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCurrentStorageAlgorithmSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'algorithm' => ['type' => 'string'],
            ],
            'required' => ['algorithm'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssetDownloadChunkSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hash' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'type' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
                'algo' => ['type' => 'string'],
                'offset' => ['type' => 'integer'],
                'requestedLength' => ['type' => 'integer'],
                'bytesRead' => ['type' => 'integer'],
                'nextOffset' => ['type' => 'integer'],
                'eof' => ['type' => 'boolean'],
                'chunkBase64' => ['type' => 'string'],
            ],
            'required' => ['hash', 'name', 'type', 'size', 'algo', 'offset', 'requestedLength', 'bytesRead', 'nextOffset', 'eof', 'chunkBase64'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssetInfoSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hash' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'type' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
                'algo' => ['type' => 'string'],
                'fileObject' => [
                    'type' => 'object',
                    'properties' => [
                        'sha1' => ['type' => 'string'],
                        '_hash' => ['type' => 'string'],
                        'filesize' => ['type' => 'integer'],
                        '_size' => ['type' => 'integer'],
                        'filename' => ['type' => 'string'],
                        '_name' => ['type' => 'string'],
                        'mimetype' => ['type' => 'string'],
                        '_type' => ['type' => 'string'],
                        '_algo' => ['type' => 'string'],
                    ],
                    'required' => ['sha1', '_hash', 'filesize', '_size', 'filename', '_name', 'mimetype', '_type', '_algo'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['hash', 'name', 'type', 'size', 'algo', 'fileObject'],
            'additionalProperties' => false,
        ];
    }

    private function seekStream(StreamInterface $stream, int $offset): void
    {
        if (0 === $offset) {
            return;
        }

        if ($stream->isSeekable()) {
            $stream->seek($offset);

            return;
        }

        $remaining = $offset;
        while ($remaining > 0 && !$stream->eof()) {
            $chunk = $stream->read(\min($remaining, File::DEFAULT_CHUNK_SIZE));
            $remaining -= \strlen($chunk);
        }

        if ($remaining > 0) {
            throw new ToolCallException('Offset is beyond the end of the asset stream.');
        }
    }
}
