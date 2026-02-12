<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Helper\AssetExtractor\ExtractedData;
use EMS\CoreBundle\Service\AssetExtractorService;
use Twig\Attribute\AsTwigFilter;

class DataExtractorExtension
{
    public function __construct(private readonly AssetExtractorService $extractorService)
    {
    }

    #[AsTwigFilter(name: 'emsco_asset_meta')]
    public function assetMeta(string $hash, ?string $file = null, bool $forced = false): ExtractedData
    {
        return $this->extractorService->extractMetaData($hash, $file, $forced);
    }

    #[AsTwigFilter(name: 'emsco_guess_locale')]
    public function guessLocale(string $text): ?string
    {
        return $this->extractorService->getMetaFromText($text)->getLocale();
    }
}
