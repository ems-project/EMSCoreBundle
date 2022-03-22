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
    private const INTERNAL_TAGS = [
        'a',
        'abbr',
        'acronym',
        'applet',
        'b',
        'bdo',
        'big',
        'blink',
        'br',
        'button',
        'cite',
        'code',
        'del',
        'dfn',
        'em',
        'embed',
        'face',
        'font',
        'i',
        'iframe',
        'img',
        'input',
        'ins',
        'kbd',
        'label',
        'map',
        'nobr',
        'object',
        'param',
        'q',
        'rb',
        'rbc',
        'rp',
        'rt',
        'rtc',
        'ruby',
        's',
        'samp',
        'select',
        'small',
        'span',
        'spacer',
        'strike',
        'strong',
        'sub',
        'sup',
        'symbol',
        'textarea',
        'tt',
        'u',
        'var',
        'wbr',
    ];
    public const XLIFF_1_2 = '1.2';
    public const XLIFF_2_0 = '2.0';
    public const XLIFF_VERSIONS = [self::XLIFF_1_2, self::XLIFF_2_0];

    private int $nextId = 1;
    private string $xliffVersion;
    private string $sourceLocale;
    private ?string $targetLocale;
    private \DOMElement $xliff;
    private \DOMDocument $dom;

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
                    'xmlns:html' => 'http://www.w3.org/1999/xhtml',
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns' => 'urn:oasis:names:tc:xliff:document:'.$xliffVersion,
                    'version' => $xliffVersion,
                    'xsi:schemaLocation' => 'urn:oasis:names:tc:xliff:document:1.2 https://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd',
                ];
                break;
            case self::XLIFF_2_0:
                $xliffAttributes = [
                    'version' => $xliffVersion,
                    'xmlns' => 'urn:oasis:names:tc:xliff:document:'.$xliffVersion,
                    'srcLang' => $sourceLocale,
                ];
                if (null !== $targetLocale) {
                    $xliffAttributes['trgLang'] = $targetLocale;
                }
                break;
            default:
                throw new \RuntimeException('Unexpected XLIFF version');
        }

        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;

        $this->xliff = new \DOMElement('xliff');
        $this->dom->appendChild($this->xliff);
        foreach ($xliffAttributes as $attribute => $value) {
            $this->xliff->setAttribute($attribute, $value);
        }
    }

    public function addDocument(string $contentType, string $ouuid, string $revisionId): \DOMElement
    {
        $id = \join(':', [$contentType, $ouuid, $revisionId]);
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $subNode = 'body';
            $documentAttributes = [
                'source-language' => $this->sourceLocale,
                'original' => $id,
                'datatype' => 'database',
            ];
            if (null !== $this->targetLocale) {
                $documentAttributes['target-language'] = $this->targetLocale;
            }
        } else {
            $subNode = null;
            $documentAttributes = [
                'id' => $id,
            ];
        }
        $document = new \DOMElement('file');
        $this->xliff->appendChild($document);
        foreach ($documentAttributes as $attribute => $value) {
            $document->setAttribute($attribute, $value);
        }

        if (null !== $subNode) {
            $subElement = new \DOMElement($subNode);
            $document->appendChild($subElement);

            return $subElement;
        }

        return $document;
    }

    public function saveXML(string $filename, string $encoding = 'UTF-8'): bool
    {
        $this->dom->encoding = $encoding;

        return false !== $this->dom->save($filename);
    }

    public function getDom(): \DOMDocument
    {
        return $this->dom;
    }

    public function addSimpleField(\DOMElement $document, string $fieldPath, string $source, ?string $target = null, bool $isFinal = false): void
    {
        $xliffAttributes = [
            'id' => $fieldPath,
        ];
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $qualifiedName = 'trans-unit';
        } else {
            $qualifiedName = 'unit';
        }
        $unit = new \DOMElement($qualifiedName);
        $document->appendChild($unit);
        foreach ($xliffAttributes as $attribute => $value) {
            $unit->setAttribute($attribute, $value);
        }

        $this->addTextSegment($unit, $this->escapeSpecialCharacters($source), null === $target ? null : $this->escapeSpecialCharacters($target), $isFinal);
    }

    public function addHtmlField(\DOMElement $document, string $fieldPath, string $sourceHtml, ?string $targetHtml = null, bool $isFinal = false): void
    {
        $sourceCrawler = new Crawler($sourceHtml);
        $targetCrawler = new Crawler($targetHtml);
        $added = false;
        foreach ($sourceCrawler->filterXPath('//body') as $domNode) {
            $this->addGroupNode($document, $domNode, $targetCrawler, $isFinal, $fieldPath);
            $added = true;
        }
        if (!$added) {
            $group = new \DOMElement('group');
            $document->appendChild($group);
            $group->setAttribute('id', $fieldPath);
        }
    }

    private function addGroupNode(\DOMElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler, bool $isFinal, ?string $id = null): void
    {
        if (!$this->hasSomethingToTranslate($sourceNode)) {
            return;
        }

        if ($this->isSegmentNode($sourceNode)) {
            $this->addSegmentNode($xliffElement, $sourceNode, $targetCrawler, $isFinal);

            return;
        }

        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $groupAttributes = [];
            if (null === $id) {
                $groupAttributes['restype'] = $this->getRestype($sourceNode->nodeName);
            }
            if (null !== $sourceNode->attributes) {
                foreach ($sourceNode->attributes as $value) {
                    if (!$value instanceof \DOMAttr) {
                        throw new \RuntimeException('Unexpected attribute object');
                    }
                    if (\in_array($value->nodeName, self::TRANSLATABLE_ATTRIBUTES, true)) {
                        continue;
                    }
                    $groupAttributes['html:'.$value->nodeName] = $value->nodeValue;
                }
            }
        } else {
            $groupAttributes = [];
        }
        $group = new \DOMElement('group');
        $xliffElement->appendChild($group);
        foreach ($groupAttributes as $attribute => $value) {
            $group->setAttribute($attribute, $value);
        }
        if (null === $id) {
            $this->addId($group, $sourceNode);
        } else {
            $group->setAttribute('id', $id);
        }
        foreach ($sourceNode->childNodes as $childNode) {
            $this->addGroupNode($group, $childNode, $targetCrawler, $isFinal);
        }
    }

    private function addId(\DOMElement $xliffElement, \DOMNode $domNode, string $attributeName = null): void
    {
        $id = $this->getId($domNode, $attributeName);
        $xliffElement->setAttribute('id', $id);
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

    private function addTextSegment(\DOMElement $unit, string $source, ?string $target, bool $isFinal): void
    {
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $qualifiedName = null;
            $sourceAttributes = [
                'xml:lang' => $this->sourceLocale,
            ];
            $targetAttributes = [];
            if (null !== $this->targetLocale) {
                $targetAttributes['xml:lang'] = $this->targetLocale;
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
            $unit = $unit->appendChild(new \DOMElement($qualifiedName));
        }
        $sourceChild = new \DOMElement('source', $source);
        $unit->appendChild($sourceChild);
        foreach ($sourceAttributes as $attribute => $value) {
            $sourceChild->setAttribute($attribute, $value);
        }

        if (null === $target) {
            return;
        }
        $targetChild = new \DOMElement('target', $target);
        $unit->appendChild($targetChild);
        foreach ($targetAttributes as $attribute => $value) {
            $targetChild->setAttribute($attribute, $value);
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

    private function escapeSpecialCharacters(string $text): string
    {
        return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8', true);
    }

    private function isSegmentNode(\DOMNode $sourceNode): bool
    {
        foreach ($sourceNode->childNodes as $child) {
            if ($child instanceof \DOMElement && !\in_array($child->nodeName, self::INTERNAL_TAGS)) {
                return false;
            }
        }

        return true;
    }

    private function addSegmentNode(\DOMElement $xliffElement, \DOMNode $sourceNode, Crawler $targetCrawler, bool $isFinal): void
    {
        $attributes = [];
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $qualifiedName = null;
        } else {
            $qualifiedName = 'unit';
        }
        if (null !== $qualifiedName) {
            $tempElement = new \DOMElement($qualifiedName);
            $xliffElement->appendChild($tempElement);
            $this->addId($tempElement, $sourceNode);
            $xliffElement = $tempElement;
        }

        $targetAttributes = [];
        if (\version_compare($this->xliffVersion, '2.0') < 0) {
            $qualifiedName = 'trans-unit';
            $sourceAttributes = [
                'xml:lang' => $this->sourceLocale,
            ];
            if (null !== $this->targetLocale) {
                $targetAttributes['xml:lang'] = $this->targetLocale;
            }
            if ($isFinal) {
                $targetAttributes['state'] = 'final';
            }
        } else {
            $qualifiedName = 'segment';
            $sourceAttributes = [];
        }

        if (null !== $sourceNode->attributes && \version_compare($this->xliffVersion, '2.0') < 0) {
            foreach ($sourceNode->attributes as $value) {
                if (!$value instanceof \DOMAttr) {
                    throw new \RuntimeException('Unexpected attribute object');
                }
                if (\in_array($value->nodeName, self::TRANSLATABLE_ATTRIBUTES, true)) {
                    continue;
                }
                $attributes['html:'.$value->nodeName] = $value->nodeValue;
            }
        }

        $segment = new \DOMElement($qualifiedName);
        $xliffElement->appendChild($segment);
        foreach ($attributes as $attribute => $value) {
            $segment->setAttribute($attribute, $value);
        }
        $this->addId($segment, $sourceNode);

        $source = new \DOMElement('source');
        $segment->appendChild($source);
        foreach ($sourceAttributes as $attribute => $value) {
            $source->setAttribute($attribute, $value);
        }

        $this->fillInline($sourceNode, $source);
        $nodeXPath = $this->getXPath($sourceNode);
        if (null === $nodeXPath) {
            return;
        }

        $foundTarget = $targetCrawler->filterXPath($nodeXPath);
        if (1 !== $foundTarget->count()) {
            return;
        }
        $foundTarget = $foundTarget->getNode(0);
        if (!$foundTarget instanceof \DOMElement) {
            return;
        }
        $target = new \DOMElement('target');
        $segment->appendChild($target);
        foreach ($targetAttributes as $attribute => $value) {
            $target->setAttribute($attribute, $value);
        }
        $this->fillInline($foundTarget, $target);
    }

    private function fillInline(\DOMNode $sourceNode, \DOMElement $source): void
    {
        foreach ($sourceNode->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $subNode = new \DOMElement('g');
                $source->appendChild($subNode);
                $subNode->setAttribute('ctype', $this->getRestype($child->nodeName));
                foreach ($child->attributes ?? [] as $value) {
                    if (!$value instanceof \DOMAttr) {
                        throw new \RuntimeException('Unexpected attribute object');
                    }
                    $subNode->setAttribute('html:'.$value->nodeName, $value->nodeValue);
                }
                $this->fillInline($child, $subNode);
            } elseif ($child instanceof \DOMText) {
                $source->appendChild(new \DOMText($this->trimUselessWhiteSpaces($child->textContent)));
            }
        }
    }
}
