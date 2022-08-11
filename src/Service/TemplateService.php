<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Common\Document;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

class TemplateService
{
    public const EMS_INDEX_PREFIX = '_ems_index_';
    public const JSON_FORMAT = 'json';
    public const XML_FORMAT = 'xml';
    public const MERGED_JSON_FORMAT = 'merged-json';
    public const MERGED_XML_FORMAT = 'merged-xml';
    public const EXPORT_FORMATS = [self::JSON_FORMAT, self::XML_FORMAT, self::MERGED_JSON_FORMAT, self::MERGED_XML_FORMAT];

    private LoggerInterface $logger;
    private Registry $doctrine;
    private Environment $twig;
    private Template $template;

    private ?TemplateWrapper $twigTemplate = null;
    private ?TemplateWrapper $filenameTwigTemplate = null;

    public function __construct(Registry $doctrine, LoggerInterface $logger, Environment $twig)
    {
        $this->twig = $twig;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function init(string $actionName, ContentType $contentType): TemplateService
    {
        $template = $contentType->getActionByName($actionName);
        if (null === $template) {
            throw new \RuntimeException('Unexpected null action');
        }
        $this->template = $template;

        $this->twigTemplate = $this->twig->createTemplate($this->template->getBody());
        $filenameTemplate = $this->template->getFilename();
        if (null !== $filenameTemplate) {
            $this->filenameTwigTemplate = $this->twig->createTemplate($filenameTemplate);
        }

        return $this;
    }

    /**
     * @param mixed[] $extraContext
     */
    public function render(Document $document, ContentType $contentType, string $environment, array $extraContext = []): string
    {
        if (null === $this->twigTemplate) {
            throw new \RuntimeException('unexpected null twigTemplate');
        }

        return $this->renderTemplate($extraContext, $environment, $contentType, $document, $this->twigTemplate);
    }

    /**
     * @param mixed[] $extraContext
     */
    public function renderFilename(Document $document, ContentType $contentType, string $environment, array $extraContext = []): string
    {
        if (null === $this->filenameTwigTemplate) {
            throw new \RuntimeException('unexpected null filenameTwigTemplate');
        }

        return $this->renderTemplate($extraContext, $environment, $contentType, $document, $this->filenameTwigTemplate);
    }

    /**
     * @param array<mixed> $source
     */
    public function getXml(ContentType $contentType, array $source, bool $arrayOfDocument, ?string $ouuid = null): string
    {
        $xmlDocument = new \DOMDocument();
        if ($arrayOfDocument) {
            $root = $xmlDocument->appendChild($xmlDocument->createElement('documents'));
            foreach ($source as $ouuid => $rawData) {
                $this->addNested($xmlDocument, $root, $contentType->getName(), $rawData, ['OUUID' => $ouuid]);
            }
        } elseif (null !== $ouuid) {
            $this->addNested($xmlDocument, $xmlDocument, $contentType->getName(), $source, ['OUUID' => $ouuid]);
        } else {
            throw new \Exception('OUUID madatory in cas of simple document');
        }

        return Type::string($xmlDocument->saveXML());
    }

    public function hasFilenameTemplate(): bool
    {
        return null !== $this->filenameTwigTemplate;
    }

    /**
     * @param array<mixed> $rawData
     * @param array<mixed> $attributes
     */
    private function addNested(\DOMDocument $xmlDocument, \DOMNode $parent, string $fieldName, array $rawData, array $attributes = []): void
    {
        $child = $parent->appendChild($xmlDocument->createElement($fieldName));
        foreach ($attributes as $name => $value) {
            $attribute = $xmlDocument->createAttribute($name);
            $attribute->value = $value;
            $child->appendChild($attribute);
        }

        foreach ($rawData as $fieldName => $fieldData) {
            $index = (\is_int($fieldName) ? self::EMS_INDEX_PREFIX : '').$fieldName;
            if (\is_array($fieldData)) {
                $this->addNested($xmlDocument, $child, $index, $fieldData);
            } else {
                $child->appendChild($xmlDocument->createElement($index, \htmlspecialchars($fieldData)));
            }
        }
    }

    /**
     * @param mixed[] $extraContext
     */
    public function renderTemplate(array $extraContext, string $environment, ContentType $contentType, Document $document, TemplateWrapper $template): string
    {
        $context = \array_merge($extraContext, [
            'environment' => $environment,
            'contentType' => $contentType,
            'object' => $document,
            'source' => $document->getSource(),
        ]);

        return $template->render($context);
    }
}
