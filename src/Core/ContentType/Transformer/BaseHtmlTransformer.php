<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use Symfony\Component\DomCrawler\Crawler;

abstract class BaseHtmlTransformer extends AbstractTransformer
{
    /** @var list<string> */
    private const array UNWRAP_BLACKLIST = [
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'p', 'section', 'article', 'header', 'footer', 'nav', 'aside',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'figure', 'figcaption', 'blockquote', 'pre',
    ];

    protected function setTransformed(TransformContext $context, Crawler $crawler): void
    {
        $data = $context->getData();
        $transformed = $crawler->outerHtml();

        if (!\str_contains((string) $data, '<html')) {
            $transformed = \str_replace(['<html>', '</html>'], '', $transformed);
        }
        if (!\str_contains((string) $data, '<body')) {
            $transformed = \str_replace(['<body>', '</body>'], '', $transformed);
        }

        if (\str_starts_with((string) $data, '<!DOCTYPE')) {
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

    protected function unwrap(\DOMElement $element): void
    {
        if (\in_array(\strtolower($element->nodeName), self::UNWRAP_BLACKLIST, true)) {
            return;
        }

        $parent = $element->parentNode;
        if (null === $parent) {
            return;
        }

        if (null === $element->firstChild) {
            $this->unwrapEmpty($element, $parent);

            return;
        }

        $this->dedentChildren($element);

        while (null !== $element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function unwrapEmpty(\DOMElement $element, \DOMNode $parent): void
    {
        $prev = $element->previousSibling;
        $next = $element->nextSibling;
        $parent->removeChild($element);

        if ($next instanceof \DOMText) {
            $next->nodeValue = \preg_replace('/^[ \t]*\n/', '', $next->nodeValue ?? '');
        }
        if ($prev instanceof \DOMText) {
            $prev->nodeValue = \rtrim($prev->nodeValue ?? '', " \t");
        }
    }

    private function dedentChildren(\DOMElement $element): void
    {
        while ($element->firstChild instanceof \DOMText && '' === \trim($element->firstChild->nodeValue ?? '')) {
            $element->removeChild($element->firstChild);
        }
        while ($element->lastChild instanceof \DOMText && '' === \trim($element->lastChild->nodeValue ?? '')) {
            $element->removeChild($element->lastChild);
        }

        $document = $element->ownerDocument;
        if (null === $document) {
            return;
        }

        $outerIndent = '';
        if ($element->previousSibling instanceof \DOMText
            && \preg_match('/\n([ \t]*)$/', $element->previousSibling->nodeValue ?? '', $m)) {
            $outerIndent = $m[1];
        }

        $textNodes = new \DOMXPath($document)->query('.//text()', $element) ?: [];
        $innerIndent = '';
        foreach ($textNodes as $textNode) {
            if (\preg_match('/\n([ \t]+)/', $textNode->nodeValue ?? '', $m)) {
                $innerIndent = $m[1];
                break;
            }
        }

        if (\strlen($innerIndent) <= \strlen($outerIndent) || !\str_starts_with($innerIndent, $outerIndent)) {
            return;
        }

        $dedent = \substr($innerIndent, \strlen($outerIndent));
        foreach ($textNodes as $textNode) {
            $textNode->nodeValue = \str_replace("\n".$dedent, "\n", $textNode->nodeValue ?? '');
        }
    }
}
