<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Helper\AssetExtractor\ExtractedData;
use EMS\CoreBundle\Service\AssetExtractorService;
use Psr\Log\LoggerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class DataExtractorRuntime implements RuntimeExtensionInterface
{
    protected StorageManager $storageManager;
    protected LoggerInterface $logger;

    public function __construct(protected AssetExtractorService $extractorService)
    {
    }

    public function guessLocale(string $text): ?string
    {
        return $this->extractorService->getMetaFromText($text)->getLocale();
    }

    public function assetMeta(string $hash, ?string $file = null, bool $forced = false): ExtractedData
    {
        return $this->extractorService->extractMetaData($hash, $file, $forced);
    }
}
