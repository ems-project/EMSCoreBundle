<?php

namespace EMS\CoreBundle\Twig;

use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Service\AssetExtractorService;
use Psr\Log\LoggerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class DataExtractorRuntime implements RuntimeExtensionInterface
{
    protected AssetExtractorService $extractorService;
    protected StorageManager $storageManager;
    protected LoggerInterface $logger;

    public function __construct(AssetExtractorService $extractorService)
    {
        $this->extractorService = $extractorService;
    }

    public function guessLocale(string $text): ?string
    {
        return $this->extractorService->getMetaFromText($text)->getLocale();
    }
}
