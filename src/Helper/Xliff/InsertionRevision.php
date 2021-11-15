<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Helper\Xliff;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PropertyAccess\PropertyAccess;

class InsertionRevision
{
    private const HTML_FIELD = 'html_field';
    private const SIMPLE_FIELD = 'simple_field';
    private const UNKNOWN_FIELD_TYPE = 'UNKNOWN_FIELD_TYPE';
    public const LOCALE_PLACE_HOLDER = '%locale%';
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
    public function __construct(\SimpleXMLElement $document, string $version, array $nameSpaces, ?string $sourceLocale, ?string $targetLocale)
    {
        $this->document = $document;
        $this->version = $version;
        $this->nameSpaces = $nameSpaces;
        $this->sourceLocale = $sourceLocale;
        $this->targetLocale = $targetLocale;
        if (\version_compare($this->version, '2.0') < 0) {
            list($this->contentType, $this->ouuid, $this->revisionId) = \explode(':', \strval($document['original']));
        } else {
            list($this->contentType, $this->ouuid, $this->revisionId) = \explode(':', \strval($document['id']));
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
     * @param array<mixed> $rawDataSource
     * @param array<mixed> $rawDataTarget
     */
    public function extractTranslations(array &$rawDataSource, array &$rawDataTarget): void
    {
        foreach ($this->getTranslatedFields() as $field) {
            switch ($this->filedType($field)) {
                case self::HTML_FIELD:
                    $this->importHtmlField($field, $rawDataSource, $rawDataTarget);
                    break;
                case self::SIMPLE_FIELD:
                    $this->importSimpleField($field, $rawDataSource, $rawDataTarget);
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
     * @param array<mixed> $rawDataSource
     * @param array<mixed> $rawDataTarget
     */
    private function importHtmlField(\SimpleXMLElement $field, array &$rawDataSource, array &$rawDataTarget): void
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
        $sourcePropertyPath = Document::fieldPathToPropertyPath(\str_replace(self::LOCALE_PLACE_HOLDER, $sourceLocale, $propertyPath));

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
        $targetPropertyPath = Document::fieldPathToPropertyPath(\str_replace(self::LOCALE_PLACE_HOLDER, $targetLocale, $propertyPath));

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $sourceValue = $propertyAccessor->getValue($rawDataSource, $sourcePropertyPath);

        if (null === $sourceValue) {
            throw new \RuntimeException(\sprintf('Unexpected missing source value for field %s', $sourcePropertyPath));
        }

        $crawler = new Crawler($sourceValue);
        if (0 === $crawler->count()) {
            $propertyAccessor->setValue($rawDataTarget, $targetPropertyPath, '');

            return;
        }
        $extractor = new Extractor($sourceLocale, $targetLocale, $this->version);
        $extractor->translateDom($crawler, $field, $this->nameSpaces['']);

        $propertyAccessor->setValue($rawDataTarget, $targetPropertyPath, $crawler->filterXPath('//body')->html());
    }

    /**
     * @param array<mixed> $rawDataSource
     * @param array<mixed> $rawDataTarget
     */
    private function importSimpleField(\SimpleXMLElement $field, array &$rawDataSource, array &$rawDataTarget): void
    {
        $propertyPath = Document::fieldPathToPropertyPath(\strval($field['id']));

        if (\version_compare($this->version, '2.0') < 0) {
            $source = $field->source;
            $target = $field->target;
        } else {
            $source = $field->segment->source;
            $target = $field->segment->target;
        }
        $sourceValue = \strval($source);

        if (0 === $target->count()) {
            throw new \RuntimeException(\sprintf('Target not found for @id=%s', $propertyPath));
        }

        $sourceLocale = $this->getAttributeValue($field->source, 'xml:lang', $this->sourceLocale);
        if (null === $sourceLocale) {
            throw new \RuntimeException('Unexpected missing source locale');
        }
        $sourcePropertyPath = \str_replace(self::LOCALE_PLACE_HOLDER, $sourceLocale, $propertyPath);

        $targetValue = \strval($target);
        $targetLocale = $this->getAttributeValue($target, 'xml:lang', $this->targetLocale);
        if (null === $targetLocale) {
            throw new \RuntimeException('Unexpected missing target locale');
        }
        $targetPropertyPath = \str_replace(self::LOCALE_PLACE_HOLDER, $targetLocale, $propertyPath);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $expectedSourceValue = $propertyAccessor->getValue($rawDataSource, $sourcePropertyPath);

        if ($expectedSourceValue !== $sourceValue) {
            throw new \RuntimeException(\sprintf('Unexpected mismatched sources expected "%s" got "%s" for property %s', $expectedSourceValue, $sourceValue, $sourcePropertyPath));
        }

        $propertyAccessor->setValue($rawDataTarget, $targetPropertyPath, $targetValue);
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

    public function getTargetLocale(): string
    {
        if (null !== $this->targetLocale) {
            return $this->targetLocale;
        }

        $result = $this->document->xpath('//xliff:target');
        if (false === $result) {
            throw new \RuntimeException('Unexpected false xpath //xliff:target result');
        }
        foreach ($result as $target) {
            if (null === $this->targetLocale) {
                $this->targetLocale = \strval($target->attributes('xml', true)['lang'] ?? null);
                continue;
            }
            if ($this->targetLocale !== \strval($target->attributes('xml', true)['lang'] ?? null)) {
                throw new \RuntimeException('Elasticms does\'t support XLIFF files containing multiple target languages');
            }
        }

        if (null === $this->targetLocale) {
            throw new \RuntimeException('Unexpected null targetLocale');
        }

        return $this->targetLocale;
    }
}
