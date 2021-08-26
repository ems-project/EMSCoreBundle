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

    public function __construct(\SimpleXMLElement $document, string $version)
    {
        $this->document = $document;
        $this->version = $version;
        $original = \strval($document['original']);
        list($this->contentType, $this->ouuid, $this->revisionId) = \explode(':', $original);
        if (\version_compare($this->version, '2.0') < 0) {
            $this->sourceLocale = null;
            $this->targetLocale = null;
        } else {
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
        if ($nodeName === 'group') {
            return self::HTML_FIELD;
        } elseif ($nodeName === 'trans-unit' && \version_compare($this->version, '2.0') < 0) {
            return self::SIMPLE_FIELD;
        } elseif ($nodeName === 'unit' && \version_compare($this->version, '2.0') >= 0) {
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
    }

    /**
     * @param array<mixed> $rawData
     */
    private function importSimpleField(\SimpleXMLElement $field, array &$rawData): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $source = \strval($field->source);
        $target = \strval($field->target);
        $propertyPath= \strval($field['id']);
        $sourceLocale = $this->getSourceLocale($field);
        dump($sourceLocale);
    }

    private function getSourceLocale(\SimpleXMLElement $field): string
    {
        dump($field->source->attributes());
        return $this->sourceLocale ?? \strval($field->source['xml:xml:lang']);
    }
}
