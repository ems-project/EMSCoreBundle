<?php

namespace EMS\CoreBundle\Helper\Xliff;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ImporterRevision
{
    private const HTML_FIELD = 'html_field';
    private const SIMPLE_FIELD = 'simple_field';
    private const UNKNOWN_FIELD_TYPE = 'UNKNOWN_FIELD_TYPE';
    private string $version;
    private string $contentType;
    private string $ouuid;
    private string $revisionId;
    private \SimpleXMLElement $document;
    private ?string $sourceLocale;
    private ?string $targetLocale;
    /** @var string[] */
    private array $nameSpaces;

    /**
     * @param string[] $nameSpaces
     */
    public function __construct(\SimpleXMLElement $document, string $version, array $nameSpaces)
    {
        $this->document = $document;
        $this->version = $version;
        $this->nameSpaces = $nameSpaces;
        if (\version_compare($this->version, '2.0') < 0) {
            list($this->contentType, $this->ouuid, $this->revisionId) = \explode(':', \strval($document['original']));
            $this->sourceLocale = null;
            $this->targetLocale = null;
        } else {
            list($this->contentType, $this->ouuid, $this->revisionId) = \explode(':', \strval($document['id']));
            $this->sourceLocale = \strval($this->document['srcLang']);
            $this->targetLocale = \strval($this->document['trgLang']);
        }
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getOuuid(): string
    {
        return $this->ouuid;
    }

    public function getRevisionId(): string
    {
        return $this->revisionId;
    }

    /**
     * @param array<mixed> $rawData
     */
    public function importTranslations(array &$rawData): void
    {
        foreach ($this->getTranslatedFields() as $field) {
            switch ($this->filedType($field)) {
                case self::HTML_FIELD:
                    $this->importHtmlField($field, $rawData);
                    break;
                case self::SIMPLE_FIELD:
                    $this->importSimpleField($field, $rawData);
                    break;
                default:
                    throw new \RuntimeException('Unexpected field type');
            }
        }
    }

    public function getTranslatedFields(): \SimpleXMLElement
    {
        if (\version_compare($this->version, '2.0') < 0) {
            $fields = $this->document->body->children();
        } else {
            $fields = $this->document->children();
        }

        return $fields;
    }

    private function filedType(\SimpleXMLElement $field): string
    {
        $nodeName = $field->getName();
        if ('group' === $nodeName) {
            return self::HTML_FIELD;
        } elseif ('trans-unit' === $nodeName && \version_compare($this->version, '2.0') < 0) {
            return self::SIMPLE_FIELD;
        } elseif ('unit' === $nodeName && \version_compare($this->version, '2.0') >= 0) {
            return self::SIMPLE_FIELD;
        } else {
            return self::UNKNOWN_FIELD_TYPE;
        }
    }

    /**
     * @param array<mixed> $rawData
     */
    private function importHtmlField(\SimpleXMLElement $field, array &$rawData): void
    {
        $propertyPath = \strval($field['id']);
        $field->registerXPathNamespace('ns', $this->nameSpaces['']);

        $sourceLocale = $this->sourceLocale;
        $firstSource = $field->xpath('(//ns:source)[1]');
        if (false === $firstSource) {
            throw new \RuntimeException('Unexpected missing source');
        }
        foreach ($firstSource as $item) {
            $sourceLocale = $this->getAttributeValue($item, 'xml:lang', $this->sourceLocale);
            break;
        }
        if (null === $sourceLocale) {
            throw new \RuntimeException('Unexpected missing source locale');
        }
        $sourcePropertyPath = \str_replace('%locale%', $sourceLocale, $propertyPath);

        $targetLocale = $this->targetLocale;
        $firstTarget = $field->xpath('(//ns:target)[1]');
        if (false === $firstTarget) {
            throw new \RuntimeException('Unexpected missing source');
        }
        foreach ($firstTarget as $item) {
            $targetLocale = $this->getAttributeValue($item, 'xml:lang', $this->targetLocale);
            break;
        }
        if (null === $targetLocale) {
            throw new \RuntimeException('Unexpected missing target locale');
        }
        $targetPropertyPath = \str_replace('%locale%', $targetLocale, $propertyPath);

        if ($sourcePropertyPath === $targetPropertyPath) {
            throw new \RuntimeException(\sprintf('Unexpected identical source and target id: %s', $targetPropertyPath));
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $sourceValue = $propertyAccessor->getValue($rawData, $sourcePropertyPath);

        if (null === $sourceValue) {
            throw new \RuntimeException(\sprintf('Unexpected missing source value for field %s', $sourcePropertyPath));
        }

        $crawler = new Crawler($sourceValue);
        $extractor = new Extractor($sourceLocale, $targetLocale, $this->version);
        $extractor->translateDom($crawler, $field, $this->nameSpaces['']);
        $propertyAccessor->setValue($rawData, $targetPropertyPath, $crawler->filterXPath('//body')->html());
    }

    /**
     * @param array<mixed> $rawData
     */
    private function importSimpleField(\SimpleXMLElement $field, array &$rawData): void
    {
        $propertyPath = \strval($field['id']);

        $source = \strval($field->source);
        $sourceLocale = $this->getAttributeValue($field->source, 'xml:lang', $this->sourceLocale);
        if (null === $sourceLocale) {
            throw new \RuntimeException('Unexpected missing source locale');
        }
        $sourcePropertyPath = \str_replace('%locale%', $sourceLocale, $propertyPath);

        $target = \strval($field->target);
        $targetLocale = $this->getAttributeValue($field->target, 'xml:lang', $this->targetLocale);
        if (null === $targetLocale) {
            throw new \RuntimeException('Unexpected missing target locale');
        }
        $targetPropertyPath = \str_replace('%locale%', $targetLocale, $propertyPath);

        if ($sourcePropertyPath === $targetPropertyPath) {
            throw new \RuntimeException(\sprintf('Unexpected identical source and target id: %s', $targetPropertyPath));
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $sourceValue = $propertyAccessor->getValue($rawData, $sourcePropertyPath);

        if ($sourceValue !== $source) {
            throw new \RuntimeException(\sprintf('Unexpected mismatched sources expected "%s" got "%s" for property %s', $sourceValue, $source, $sourcePropertyPath));
        }

        $propertyAccessor->setValue($rawData, $targetPropertyPath, $target);
    }

    public function getAttributeValue(\SimpleXMLElement $field, string $attributeName, ?string $defaultValue = null): ?string
    {
        if (false === \strpos($attributeName, ':')) {
            $nameSpace = null;
            $tag = $attributeName;
        } else {
            list($nameSpace, $tag) = \explode(':', $attributeName);
        }

        if (null === $nameSpace) {
            $attribute = $field->attributes()[$tag] ?? null;
        } elseif (!isset($this->nameSpaces[$nameSpace])) {
            return $defaultValue;
        } else {
            $attribute = $field->attributes($this->nameSpaces[$nameSpace])[$tag] ?? null;
        }
        if (null === $attribute) {
            return $defaultValue;
        }

        return \strval($attribute);
    }
}
