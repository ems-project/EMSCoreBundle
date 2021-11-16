<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Helper\Xliff;

use Symfony\Component\DomCrawler\Crawler;

class Extractor
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

    private const TRANSLATABLE_ATTRIBUTES = ['title', 'alt', 'aria-label'];
    public const XLIFF_1_2 = '1.2';
    public const XLIFF_2_0 = '2.0';
    public const XLIFF_VERSIONS = [self::XLIFF_1_2, self::XLIFF_2_0];

    private int $nextId = 1;
    private string $xliffVersion;
    private string $sourceLocale;
    private ?string $targetLocale;
    private \SimpleXMLElement $xliff;

    public function __construct(string $sourceLocale, ?string $targetLocale = null, string $xliffVersion = self::XLIFF_1_2)
    {
        if (!\in_array($xliffVersion, self::XLIFF_VERSIONS)) {
            throw new \RuntimeException(\sprintf('Unsupported XLIFF version "%s", use one of the supported one: %s', $xliffVersion, \join(', ', self::XLIFF_VERSIONS)));
        }

        $this->nextId = 1;
        $this->sourceLocale = $sourceLocale;
        $this->targetLocale = $targetLocale;
        $this->xliffVersion = $xliffVersion;

        switch ($xliffVersion) {
            case self::XLIFF_1_2:
                $xliffAttributes = [
                    'xmlns:xmlns:html' => 'http://www.w3.org/1999/xhtml',
                    'xmlns:xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xsi:xsi:schemaLocation' => 'urn:oasis:names:tc:xliff:document:1.2 https://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd',
                ];
                break;
            case self::XLIFF_2_0:
                $xliffAttributes = [
                    'srcLang' => $sourceLocale,
                ];
                if (null !== $targetLocale) {
                    $xliffAttributes['trgLang'] = $targetLocale;
                }
                break;
            default:
                throw new \RuntimeException('Unexpected XLIFF version');
        }

        $this->xliff = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xliff/>');
        $this->xliff->addAttribute('version', $xliffVersion);
        $this->xliff->addAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:'.$xliffVersion);
        foreach ($xliffAttributes as $attribute => $value) {
            $this->xliff->addAttribute($attribute, $value);
        }
    }

    public function addDocument(string $contentType, string $ouuid, string $revisionId): \SimpleXMLElement
    {
        $id = \join(':', [$contentType, $ouuid, $revisionId]);
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $subNode = 'body';
            $xliffAttributes = [
                'source-language' => $this->sourceLocale,
                'original' => $id,
                'datatype' => 'ems-revision',
            ];
        } else {
            $subNode = null;
            $xliffAttributes = [
                'id' => $id,
            ];
        }
        $document = $this->xliff->addChild('file');
        foreach ($xliffAttributes as $attribute => $value) {
            $document->addAttribute($attribute, $value);
        }

        if (null !== $subNode) {
            $document = $document->addChild($subNode);
        }

        return $document;
    }

    public function saveXML(string $filename): bool
    {
        return true === $this->xliff->saveXML($filename);
    }

    public function asXML(): \SimpleXMLElement
    {
        return $this->xliff;
    }

    public function addSimpleField(\SimpleXMLElement $document, string $fieldPath, string $source, ?string $target = null, bool $isFinal = false): void
    {
        $xliffAttributes = [
            'id' => $fieldPath,
        ];
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $qualifiedName = 'trans-unit';
        } else {
            $qualifiedName = 'unit';
        }
        $unit = $document->addChild($qualifiedName);
        foreach ($xliffAttributes as $attribute => $value) {
            $unit->addAttribute($attribute, $value);
        }

        $this->addSegment($unit, $source, $target, $isFinal);
    }

    public function addHtmlField(\SimpleXMLElement $document, string $fieldPath, string $sourceHtml, ?string $targetHtml = null, bool $isFinal = false): void
    {
        $sourceCrawler = new Crawler($sourceHtml);
        $targetCrawler = new Crawler($targetHtml);
        $id = $fieldPath;
        $xliffAttributes = [
            'id' => $id,
        ];
        $group = $document->addChild('group');
        foreach ($xliffAttributes as $attribute => $value) {
            $group->addAttribute($attribute, $value);
        }

        foreach ($sourceCrawler->filterXPath('//body/*') as $domNode) {
            $this->domNodeToXliff($group, $domNode, $targetCrawler, $isFinal);
        }
    }

    private function domNodeToXliff(\SimpleXMLElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler, bool $isFinal): void
    {
        if (!$this->hasSomethingToTranslate($sourceNode)) {
            return;
        }

        if ($this->isGroupNode($sourceNode)) {
            if (\version_compare($this->xliffVersion, '2.0') < 0) {
                $xliffAttributes = [
                    'restype' => $this->getRestype($sourceNode->nodeName),
                ];
                $sourceAttributes = $sourceNode->attributes;
                if (null !== $sourceAttributes) {
                    foreach ($sourceAttributes as $value) {
                        if (!$value instanceof \DOMAttr) {
                            throw new \RuntimeException('Unexpected attribute object');
                        }
                        if (\in_array($value->nodeName, self::TRANSLATABLE_ATTRIBUTES, true)) {
                            continue;
                        }
                        $xliffAttributes['html:html:'.$value->nodeName] = $value->nodeValue;
                    }
                }
            } else {
                $xliffAttributes = [];
            }
            $group = $xliffElement->addChild('group');
            foreach ($xliffAttributes as $attribute => $value) {
                $group->addAttribute($attribute, $value);
            }
            $this->addId($group, $sourceNode);
            foreach ($sourceNode->childNodes as $childNode) {
                $this->domNodeToXliff($group, $childNode, $targetCrawler, $isFinal);
            }
        } else {
            $attributes = [];
            if (\version_compare($this->xliffVersion, '2.0') < 0) {
                $qualifiedName = null;
            } else {
                $qualifiedName = 'unit';
            }
            if (null !== $qualifiedName) {
                $xliffElement = $xliffElement->addChild($qualifiedName);
                $this->addId($xliffElement, $sourceNode);
            }
            $this->addSegments($xliffElement, $sourceNode, $targetCrawler, $attributes, $isFinal);
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
        $id = $this->getId($domNode, $attributeName);
        $xliffElement->addAttribute('id', $id);
    }

    /**
     * @param string[] $attributes
     */
    private function addSegments(\SimpleXMLElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler, array $attributes = [], bool $isFinal = false): void
    {
        $sourceAttributes = $sourceNode->attributes;
        if (null !== $sourceAttributes && \version_compare($this->xliffVersion, '2.0') < 0) {
            foreach ($sourceAttributes as $value) {
                if (!$value instanceof \DOMAttr) {
                    throw new \RuntimeException('Unexpected attribute object');
                }
                if (\in_array($value->nodeName, self::TRANSLATABLE_ATTRIBUTES, true)) {
                    continue;
                }
                $attributes['html:html:'.$value->nodeName] = $value->nodeValue;
            }
        }

        $this->addAttributeSegments($xliffElement, $sourceNode, $targetCrawler);
        foreach ($sourceNode->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $targetAttributes = [];
                if (\version_compare($this->xliffVersion, '2.0') < 0) {
                    $qualifiedName = 'trans-unit';
                    $sourceAttributes = [
                        'xml:xml:lang' => $this->sourceLocale,
                    ];
                    if (null !== $this->targetLocale) {
                        $targetAttributes['xml:xml:lang'] = $this->targetLocale;
                    }
                    if ($isFinal) {
                        $targetAttributes['state'] = 'final';
                    }
                } else {
                    $qualifiedName = 'segment';
                    $sourceAttributes = [];
                }
                $segment = $xliffElement->addChild($qualifiedName);
                foreach ($attributes as $attribute => $value) {
                    $segment->addAttribute($attribute, $value);
                }
                $this->addId($segment, $child);
                $source = $segment->addChild('source', $this->trimUselessWhiteSpaces($child->textContent));
                foreach ($sourceAttributes as $attribute => $value) {
                    $source->addAttribute($attribute, $value);
                }
                $nodeXPath = $this->getXPath($child);
                if (null === $nodeXPath) {
                    continue;
                }
                $foundTarget = $targetCrawler->filterXPath($nodeXPath);

                if (1 !== $foundTarget->count()) {
                    continue;
                }
                $target = $segment->addChild('target', $this->trimUselessWhiteSpaces($foundTarget->text(null, false)));
                foreach ($targetAttributes as $attribute => $value) {
                    $target->addAttribute($attribute, $value);
                }
            } else {
                $this->addSegments($xliffElement, $child, $targetCrawler, $attributes, $isFinal);
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
                $targetAttributes = [];
                if (\version_compare($this->xliffVersion, '2.0') < 0) {
                    $qualifiedName = 'trans-unit';
                    $sourceAttributes = [
                        'xml:xml:lang' => $this->sourceLocale,
                    ];
                    if (null !== $this->targetLocale) {
                        $targetAttributes['xml:xml:lang'] = $this->targetLocale;
                    }
                } else {
                    $qualifiedName = 'segment';
                    $sourceAttributes = [];
                }

                $segment = $xliffElement->addChild($qualifiedName);
                $this->addId($segment, $sourceNode, $attributeName);
                $source = $segment->addChild('source', $attributeValue->nodeValue);
                foreach ($sourceAttributes as $key => $value) {
                    $source->addAttribute($key, $value);
                }

                $nodeXPath = $this->getXPath($sourceNode);
                if (null === $nodeXPath) {
                    continue;
                }
                $foundTarget = $targetCrawler->filterXPath($nodeXPath);
                $targetAttribute = $foundTarget->attr($attributeName);

                if (null === $targetAttribute) {
                    continue;
                }
                $target = $segment->addChild('target', $targetAttribute);
                foreach ($targetAttributes as $key => $value) {
                    $target->addAttribute($key, $value);
                }
            }
        }
    }

    private function addSegment(\SimpleXMLElement $unit, string $source, ?string $target, bool $isFinal): void
    {
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $qualifiedName = null;
            $sourceAttributes = [
                'xml:xml:lang' => $this->sourceLocale,
            ];
            $targetAttributes = [];
            if (null !== $this->targetLocale) {
                $targetAttributes['xml:xml:lang'] = $this->targetLocale;
            }
            if ($isFinal) {
                $targetAttributes['state'] = 'final';
            }
        } else {
            $qualifiedName = 'segment';
            $sourceAttributes = [];
            $targetAttributes = [];
        }
        if (null !== $qualifiedName) {
            $unit = $unit->addChild($qualifiedName);
        }
        $sourceChild = $unit->addChild('source', $source);
        foreach ($sourceAttributes as $attribute => $value) {
            $sourceChild->addAttribute($attribute, $value);
        }

        if (null === $target) {
            return;
        }
        $targetChild = $unit->addChild('target', $target);
        foreach ($targetAttributes as $attribute => $value) {
            $targetChild->addAttribute($attribute, $value);
        }
    }

    public static function getRestype(string $nodeName): string
    {
        return self::PRE_DEFINED_VALUES[$nodeName] ?? \sprintf('x-html-%s', $nodeName);
    }

    public function translateDom(Crawler $crawler, \SimpleXMLElement $field, string $nameSpace): void
    {
        $field->registerXPathNamespace('ns', $nameSpace);
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $xpath = "//ns:trans-unit[@id='%s']/ns:target";
        } else {
            $xpath = "//ns:segment[@id='%s']/ns:target";
        }

        foreach ($crawler->children() as $child) {
            $this->recursiveTranslateDom($child, $field, $xpath);
        }
    }

    private function getId(\DOMNode $domNode, ?string $attributeName = null): string
    {
        $id = $domNode->getNodePath();
        if (null === $id) {
            $id = \strval($this->nextId++);
        }
        if (null !== $attributeName) {
            $id = \sprintf('%s[@%s]', $id, $attributeName);
        }

        return $id;
    }

    private function recursiveTranslateDom(\DOMNode $node, \SimpleXMLElement $field, string $xpath): void
    {
        foreach ($node->childNodes as $domNode) {
            if (!$this->hasSomethingToTranslate($domNode)) {
                continue;
            }
            if ($domNode instanceof \DOMText) {
                $id = $this->getId($domNode);
                $targets = $field->xpath(\sprintf($xpath, $id));
                if (false === $targets || 1 !== \count($targets)) {
                    throw new \RuntimeException(\sprintf('Target not fount for DOM %s', $id));
                }
                $domNode->nodeValue = \strval($targets[0]);
            } else {
                $this->recursiveTranslateDom($domNode, $field, $xpath);
            }

            $attributes = $domNode->attributes;
            if (null === $attributes) {
                continue;
            }
            foreach (self::TRANSLATABLE_ATTRIBUTES as $attributeName) {
                if (null !== $attributeValue = $attributes->getNamedItem($attributeName)) {
                    $id = $this->getId($domNode, $attributeValue->nodeName);
                    $targets = $field->xpath(\sprintf($xpath, $id));
                    if (false === $targets || 1 !== \count($targets)) {
                        throw new \RuntimeException(\sprintf('Target not fount for attribute %s in DOM %s', $attributeValue->nodeName, $id));
                    }
                    $attributeValue->nodeValue = \strval($targets[0]);
                }
            }
        }
    }

    private function trimUselessWhiteSpaces(string $text): string
    {
        $trimmed = \preg_replace('!\s+!', ' ', $text);
        if (!\is_string($trimmed)) {
            throw new \RuntimeException('Unexpected non string preg_replace output');
        }

        return $trimmed;
    }
}
