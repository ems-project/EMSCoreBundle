<?php

namespace EMS\CoreBundle\ContentTransformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;

class HtmlStylesRemover implements ContentTransformInterface
{
    /** @var string */
    private $classNamePrefix;

    /** @var \DOMDocument */
    protected $doc;

    /** @var \DOMXPath */
    protected $xpath;

    public function __construct(string $classNamePrefix = 'removable-style-')
    {
        $this->classNamePrefix = $classNamePrefix;
    }

    public function canTransform(ContentTransformContext $contentTransformContext): bool
    {
        return $contentTransformContext->getDataFieldType() === WysiwygFieldType::class;
    }

    public function transform(ContentTransformContext $contentTransformContext): string
    {
        $this->doc = $this->initDocument($contentTransformContext->getData());
        $this->xpath = new \DOMXPath($this->doc);

        $this->removeHtmlStyles();

        return $this->outputDocument();
    }

    public function hasChanges(ContentTransformContext $contentTransformContext): bool
    {
        return $this->outputHtmlFormat($contentTransformContext->getData()) !== $contentTransformContext->getTransformedData();
    }

    protected function removeHtmlStyles(): void
    {
        while ($query = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), '{$this->classNamePrefix}')]")) {
            $node = $query->item(0);
            if ($node === null) {
                break;
            }
            if ($node->parentNode === null) {
                throw new \RuntimeException('Unexpected orphan node');
            }
            $node->parentNode->replaceChild(
                $this->getInnerNode($node),
                $node
            );
        }
    }

    private function getInnerNode(\DOMNode $node): \DOMNode
    {
        $doc = $node->ownerDocument;
        if ($doc === null) {
            throw new \RuntimeException('Unexpected null document');
        }
        $fragment = $doc->createDocumentFragment();

        /** @var \DOMNode $child */
        foreach ($node->childNodes as $child) {
            $fragment->appendChild($child->cloneNode(true));
        }

        return $fragment;
    }

    protected function initDocument(string $input): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        @$doc->loadHtml( // Need @ to bypass the htmlParseEntityRef warnings/errors
            \mb_convert_encoding($this->addTemporaryWrapper($input), 'HTML-ENTITIES', 'UTF-8'),
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );

        return $doc;
    }

    protected function outputDocument(): string
    {
        $this->removeTemporaryWrapper();
        $html = $this->doc->saveHTML();
        if ($html === false) {
            throw new \RuntimeException('Unexpected error while dumping in HTML format');
        }
        return $this->outputHtmlFormat($html);
    }

    private function outputHtmlFormat(string $html): string
    {
        $html = \html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = \rtrim($html);

        return $html;
    }

    private function addTemporaryWrapper(string $html): string
    {
        return '<div>' . $html . '</div>';
    }

    private function removeTemporaryWrapper(): void
    {
        $temporaryWrapper = $this->doc->getElementsByTagName('div')->item(0);
        if ($temporaryWrapper === null || $temporaryWrapper->parentNode === null) {
            return;
        }
        $temporaryWrapper = $temporaryWrapper->parentNode->removeChild($temporaryWrapper);

        while ($this->doc->firstChild) {
            $this->doc->removeChild($this->doc->firstChild);
        }

        while ($temporaryWrapper->firstChild) {
            $this->doc->appendChild($temporaryWrapper->firstChild);
        }
    }
}
