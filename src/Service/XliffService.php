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

        if (0 === $sourceCrawler->count()) {
            return;
        }

        foreach ($sourceCrawler->filter('body')->children() as $domNode) {
            $this->domNodeToXliff($xliff, $domNode, $domNode, $sourceLocale, $targetLocale);
        }
    }

    private function domNodeToXliff(\SimpleXMLElement $xliffElement, \DOMNode $sourceNode, \DOMNode $targetNode, string $sourceLocale, string $targetLocale): void
    {
        if ($sourceNode->hasChildNodes()) {
            $nodeName = 'group';
            if ($sourceNode->firstChild === $sourceNode->lastChild && null !== $sourceNode->firstChild && !$sourceNode->firstChild->hasChildNodes()) {
                $nodeName = 'trans-unit';
            }

            $group = $xliffElement->addChild($nodeName);
            foreach ($sourceNode->childNodes as $childNode) {
                $this->domNodeToXliff($group, $childNode, $targetNode, $sourceLocale, $targetLocale);
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
            $xliffElement->addAttribute('id', \strval($this->nextId++));
            $source = $xliffElement->addChild('source', $nodeValue);
            $source->addAttribute('xml:xml:lang', $sourceLocale);
        }
    }

    public static function getRestype(string $nodeName): string
    {
        return isset(self::PRE_DEFINED_VALUES[$nodeName]) ? self::PRE_DEFINED_VALUES[$nodeName] : \sprintf('x-html-%s', $nodeName);
    }
}
