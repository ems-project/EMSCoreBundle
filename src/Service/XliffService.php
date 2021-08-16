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
    private const TRANSLATABLE_ATTRIBUTES = ['title', 'alt'];

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
        if (!$this->hasSomethingToTranslate($sourceNode)) {
            return;
        }

        if ($this->isGroupNode($sourceNode)) {
            $group = $xliffElement->addChild('group');
            $this->addId($group, $sourceNode);
            foreach ($sourceNode->childNodes as $childNode) {
                $this->domNodeToXliff($group, $childNode, $targetCrawler, $sourceLocale, $targetLocale);
            }
        } else {
            $unit = $xliffElement->addChild('unit');
            $this->addId($unit, $sourceNode);
            $this->addSegments($unit, $sourceNode, $targetCrawler);
        }
    }

    private function isGroupNode(\DOMNode $sourceNode): bool
    {
        foreach ($sourceNode->childNodes as $child) {
            if ($child instanceof \DOMText && !$this->empty($child->nodeValue)) {
                return false;
            }
        }

        return true;
    }

    private function addId(\SimpleXMLElement $xliffElement, \DOMNode $domNode, string $attributeName = null): void
    {
        $id = $domNode->getNodePath();
        if (null === $id) {
            $id = \strval($this->nextId++);
        }
        if (null !== $attributeName) {
            $id = \sprintf('%s/%s', $id, $attributeName);
        }
        $xliffElement->addAttribute('id', $id);
    }

    private function addSegments(\SimpleXMLElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler): void
    {
        $this->addAttributeSegments($xliffElement, $sourceNode, $targetCrawler);
        foreach ($sourceNode->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $segment = $xliffElement->addChild('segment');
                $this->addId($segment, $child);
                $segment->addChild('source', $child->nodeValue);
                $nodeXPath = $this->getXPath($child);
                if (null === $nodeXPath) {
                    continue;
                }
                $foundTarget = $targetCrawler->filterXPath($nodeXPath);

                if (1 !== $foundTarget->count()) {
                    continue;
                }
                foreach ($foundTarget as $item) {
                    $segment->addChild('target', $foundTarget->text(null, true));
                }
            } else {
                $this->addSegments($xliffElement, $child, $targetCrawler);
            }
        }
    }

    private function empty(string $nodeValue): bool
    {
        if (\ctype_space($nodeValue) || '' === $nodeValue) {
            return true;
        }

        return false;
    }

    private function hasSomethingToTranslate(\DOMNode $sourceNode): bool
    {
        if (!$this->empty($sourceNode->nodeValue)) {
            return true;
        }
        $attributes = $sourceNode->attributes;
        if (null === $attributes) {
            return false;
        }
        foreach (self::TRANSLATABLE_ATTRIBUTES as $attributeName) {
            if (null !== $attributes->getNamedItem($attributeName)) {
                return true;
            }
        }

        return false;
    }

    private function getXPath(\DOMNode $sourceNode): ?string
    {
        $nodePath = $sourceNode->getNodePath();
        if (null === $nodePath) {
            return null;
        }

        return \str_replace('/html/', '//', $nodePath);
    }

    private function addAttributeSegments(\SimpleXMLElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler): void
    {
        $attributes = $sourceNode->attributes;
        if (null === $attributes) {
            return;
        }
        foreach (self::TRANSLATABLE_ATTRIBUTES as $attributeName) {
            if (null !== $attributeValue = $attributes->getNamedItem($attributeName)) {
                $segment = $xliffElement->addChild('segment');
                $this->addId($segment, $sourceNode, $attributeName);
                $segment->addChild('source', $attributeValue->nodeValue);

                $nodeXPath = $this->getXPath($sourceNode);
                if (null === $nodeXPath) {
                    continue;
                }
                $foundTarget = $targetCrawler->filterXPath($nodeXPath);
                $targetAttribute = $foundTarget->attr($attributeName);

                if (null === $targetAttribute) {
                    continue;
                }
                $segment->addChild('target', $targetAttribute);
            }
        }
    }
}
