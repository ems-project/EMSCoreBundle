<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Symfony\Component\DomCrawler\Crawler;

class XliffService
{
    //Source: https://docs.oasis-open.org/xliff/v1.2/xliff-profile-html/xliff-profile-html-1.2.html#SectionDetailsElements
    private const PRE_DEFINED_VALUES = [
        'b' => 'bold',
        'br' => 'lb',
        'caption' => 'caption',
        'fieldset' => 'groupbox',
        'form' => 'dialog',
        'frame' => 'frame',
        'head' => 'header',
        'i' => 'italic',
        'img' => 'image',
        'li' => 'listitem',
        'menu' => 'menu',
        'table' => 'table',
        'td' => 'cell',
        'tfoot' => 'footer',
        'tr' => 'row',
        'u' => 'underlined',
    ];

    private int $nextId = 1;

    public function __construct()
    {
    }

    public function htmlNode(\SimpleXMLElement $xliff, string $sourceHtml, string $targetHtml, string $sourceLocale, string $targetLocale): void
    {
        $sourceCrawler = new Crawler($sourceHtml);
        $targetCrawler = new Crawler($targetHtml);

        foreach ($sourceCrawler->filterXPath('//body/*') as $domNode) {
            $this->domNodeToXliff($xliff, $domNode, $targetCrawler, $sourceLocale, $targetLocale);
        }
    }

    private function domNodeToXliff(\SimpleXMLElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler, string $sourceLocale, string $targetLocale): void
    {
        if ($sourceNode->hasChildNodes()) {
            $nodeName = 'group';
            if ($sourceNode->firstChild === $sourceNode->lastChild && null !== $sourceNode->firstChild && !$sourceNode->firstChild->hasChildNodes()) {
                $nodeName = 'trans-unit';
            }

            $group = $xliffElement->addChild($nodeName);
            foreach ($sourceNode->childNodes as $childNode) {
                $this->domNodeToXliff($group, $childNode, $targetCrawler, $sourceLocale, $targetLocale);
            }
            $group->addAttribute('restype', self::getRestype($sourceNode->nodeName));
            if (null !== $attributes = $sourceNode->attributes) {
                foreach ($attributes as $attribute) {
                    $group->addAttribute(\sprintf('html:html:%s', $attribute->name), $attribute->value);
                }
            }
        } else {
            $nodeValue = $sourceNode->nodeValue;
            if (\ctype_space($nodeValue) || '' === $nodeValue) {
                return;
            }

            if ('group' === $xliffElement->getName()) {
                $xliffElement = $xliffElement->addChild('trans-unit');
            }
            $xliffElement->addAttribute('id', \strval($this->nextId++));
            $source = $xliffElement->addChild('source', $nodeValue);
            $source->addAttribute('xml:xml:lang', $sourceLocale);

            $nodePath = $sourceNode->getNodePath();
            if (null === $nodePath) {
                return;
            }

            $foundTarget = $targetCrawler->filterXPath(\str_replace(['/html/'], ['//'], $nodePath));
            if (1 !== $foundTarget->count()) {
                return;
            }

            $targetValue = $foundTarget->text(null, true);
            if (\ctype_space($targetValue) || '' === $targetValue) {
                return;
            }

            $target = $xliffElement->addChild('target', $targetValue);
            $target->addAttribute('xml:xml:lang', $targetLocale);
        }
    }

    public static function getRestype(string $nodeName): string
    {
        return isset(self::PRE_DEFINED_VALUES[$nodeName]) ? self::PRE_DEFINED_VALUES[$nodeName] : \sprintf('x-html-%s', $nodeName);
    }
}
