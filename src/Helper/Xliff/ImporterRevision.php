<?php

namespace EMS\CoreBundle\Helper\Xliff;

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
                case ImporterRevision::HTML_FIELD:
                    $this->importHtmlField($field, $rawData);
                    break;
                case ImporterRevision::SIMPLE_FIELD:
                    $this->importSimpleField($field, $rawData);
                    break;
                default:
                    throw new \RuntimeException('Unexpected field type');
            }
        }
    }

    private function getTranslatedFields(): \SimpleXMLElement
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
            return ImporterRevision::HTML_FIELD;
        } elseif ('trans-unit' === $nodeName && \version_compare($this->version, '2.0') < 0) {
            return ImporterRevision::SIMPLE_FIELD;
        } elseif ('unit' === $nodeName && \version_compare($this->version, '2.0') >= 0) {
            return ImporterRevision::SIMPLE_FIELD;
        } else {
            return ImporterRevision::UNKNOWN_FIELD_TYPE;
        }
    }

    /**
     * @param array<mixed> $rawData
     */
    private function importHtmlField(\SimpleXMLElement $field, array &$rawData): void
    {
    }

    /**
     * @param array<mixed> $rawData
     */
    private function importSimpleField(\SimpleXMLElement $field, array &$rawData): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $source = \strval($field->source);
        $target = \strval($field->target);
        $propertyPath = \strval($field['id']);
        $sourceLocale = $this->getAttributeValue($field->source, 'xml:langjh', $this->sourceLocale);
//        $sourceLocale = $this->getSourceLocale($field);
//        dump($sourceLocale);
    }

    private function getAttributeValue(\SimpleXMLElement $field, string $attributeName, ?string $defaultValue = null): ?string
    {
        list($nameSpace, $attribute) = \explode(':', $attributeName);
        $attribute = $field->attributes($this->nameSpaces[$nameSpace])[$attribute] ?? null;
        if (null === $attribute) {
            return $defaultValue;
        }

        return \strval($attribute);
    }
}
