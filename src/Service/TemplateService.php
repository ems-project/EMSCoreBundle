<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Common\Document;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
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

    /** @var LoggerInterface */
    private $logger;

    /** @var Registry */
    private $doctrine;

    /** @var Environment */
    private $twig;

    /** @var Template */
    private $template;

    /** @var ?TemplateWrapper */
    private $twigTemplate;

    public function __construct(Registry $doctrine, LoggerInterface $logger, Environment $twig)
    {
        $this->twig = $twig;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->twigTemplate = null;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function init(string $templateId): TemplateService
    {
        $em = $this->doctrine->getManager();
        $this->template = $em->getRepository(Template::class)->find($templateId);

        if (null === $this->template) {
            throw new \Exception('Template not found');
        }

        $this->twigTemplate = $this->twig->createTemplate($this->template->getBody());

        return $this;
    }

    public function render(Document $document, ContentType $contentType, string $environment, array $extraContext = []): string
    {
        $context = \array_merge($extraContext, [
            'environment' => $environment,
            'contentType' => $contentType,
            'object' => $document,
            'source' => $document->getSource(),
        ]);

        return $this->twigTemplate->render($context);
    }

    public function getXml(ContentType $contentType, array $source, bool $arrayOfDocument, ?string $ouuid = null)
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

        return $xmlDocument->saveXML();
    }

    private function addNested(\DOMDocument $xmlDocument, \DOMNode $parent, string $fieldName, array $rawData, array $attributes = [])
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
}
