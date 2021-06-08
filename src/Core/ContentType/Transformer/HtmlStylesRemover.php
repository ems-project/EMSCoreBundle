<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;

class HtmlStylesRemover implements ContentTransformInterface
{
    private string $classNamePrefix;
    protected \DOMDocument $doc;
    protected \DOMXPath $xpath;

    public function __construct(string $classNamePrefix = 'removable-style-')
    {
        $this->classNamePrefix = $classNamePrefix;

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $this->doc = $doc;
        $this->xpath = new \DOMXPath($this->doc);
    }

    public function canTransform(ContentTransformContext $contentTransformContext): bool
    {
        return WysiwygFieldType::class === $contentTransformContext->getDataFieldType();
    }

    public function transform(ContentTransformContext $contentTransformContext): string
    {
        $this->initDocument($contentTransformContext->getData());
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
            if (null === $node) {
                break;
            }
            if (null === $node->parentNode) {
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
        if (null === $doc) {
            throw new \RuntimeException('Unexpected null document');
        }
        $fragment = $doc->createDocumentFragment();

        /** @var \DOMNode $child */
        foreach ($node->childNodes as $child) {
            $fragment->appendChild($child->cloneNode(true));
        }

        return $fragment;
    }

    protected function initDocument(string $input): void
    {
        @$this->doc->loadHtml( // Need @ to bypass the htmlParseEntityRef warnings/errors
            \mb_convert_encoding($this->addTemporaryWrapper($input), 'HTML-ENTITIES', 'UTF-8'),
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );
        $this->xpath = new \DOMXPath($this->doc);
    }

    protected function outputDocument(): string
    {
        $this->removeTemporaryWrapper();
        $html = $this->doc->saveHTML();
        if (false === $html) {
            throw new \RuntimeException('Unexpected error while dumping in HTML format');
        }

        return $this->outputHtmlFormat($html);
    }

    private function outputHtmlFormat(string $html): string
    {
        $html = \html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        return \rtrim($html);
    }

    private function addTemporaryWrapper(string $html): string
    {
        return '<div>'.$html.'</div>';
    }

    private function removeTemporaryWrapper(): void
    {
        $temporaryWrapper = $this->doc->getElementsByTagName('div')->item(0);
        if (null === $temporaryWrapper || null === $temporaryWrapper->parentNode) {
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
