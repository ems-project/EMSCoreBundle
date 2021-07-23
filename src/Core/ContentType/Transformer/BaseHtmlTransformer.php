<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use Symfony\Component\DomCrawler\Crawler;

abstract class BaseHtmlTransformer extends AbstractTransformer
{
    protected function setTransformed(TransformContext $context, Crawler $crawler): void
    {
        $data = $context->getData();
        $transformed = $crawler->outerHtml();

        if (false === \strpos($data, '<html')) {
            $transformed = \str_replace(['<html>', '</html>'], '', $transformed);
        }
        if (false === \strpos($data, '<body')) {
            $transformed = \str_replace(['<body>', '</body>'], '', $transformed);
        }

        if ('<!DOCTYPE' === \substr($data, 0, 9)) {
            $transformed = <<<transformed
<!DOCTYPE html>
$transformed
transformed;
        }

        $context->setTransformed($transformed);
    }

    /**
     * @return \Generator|\DOMElement[]
     */
    protected function crawl(Crawler $crawler, string $xPath): \Generator
    {
        foreach ($crawler->filterXPath($xPath) as $element) {
            if ($element instanceof \DOMElement) {
                yield $element;
            }
        }
    }
}
