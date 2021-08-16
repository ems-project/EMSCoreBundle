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

    public function __construct()
    {
    }

    public function htmlNode(string $ouuid, string $sourceHtml, string $targetHtml, string $sourceLocale, string $targetLocale): \SimpleXMLElement
    {
        $sourceCrawler = new Crawler($sourceHtml);
        $targetCrawler = new Crawler($targetHtml);

        $xliffNode = new \SimpleXMLElement('<body></body>');
        $this->domToXliff($xliffNode, $sourceCrawler, $targetCrawler, $sourceLocale, $targetLocale);

        return $xliffNode;
    }

    /**
     * @param \DOMNode[] $sourceNodeList
     * @param \DOMNode[] $targetNodeList
     */
    private function domToXliff(\SimpleXMLElement $xliffNode, iterable $sourceNodeList, iterable $targetNodeList, string $sourceLocale, string $targetLocale): void
    {
        foreach ($sourceNodeList as $sourceNode) {
            if (!$sourceNode instanceof \DOMNode) {
                throw new \RuntimeException('Unexpected DOM object');
            }

            if ($sourceNode->hasChildNodes()) {
                $child = $xliffNode->addChild('group');
                $child->addAttribute('restype', self::getRestype($sourceNode->nodeName));
                $this->domToXliff($child, $sourceNode->childNodes, $sourceNode->childNodes, $sourceLocale, $targetLocale);
            }
        }
    }

    public static function getRestype(string $nodeName): string
    {
        return isset(self::PRE_DEFINED_VALUES[$nodeName]) ? self::PRE_DEFINED_VALUES[$nodeName] : \sprintf('x-html-%s', $nodeName);
    }
}
