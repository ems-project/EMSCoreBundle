<?php

namespace EMS\CoreBundle\ContentTransformer;

class HtmlStylesRemover implements ContentTransformInterface
{
    /** @var string */
    private $classNamePrefix;

    /** @var \DOMDocument */
    private $doc;

    /** @var \DOMXPath */
    private $xpath;

    public function __construct(string $html, string $classNamePrefix = 'removable-style-')
    {
        $this->classNamePrefix = $classNamePrefix;
        $this->doc = $this->initDocument($html);
        $this->xpath = new \DOMXPath($this->doc);
    }

    public function canTransform(): bool
    {
        return true;
    }

    public function transform(): void
    {
        $this->removeHtmlStyles();
    }

    public function changed(): bool
    {
        return true;
    }

    public function removeHtmlStyles(): ?string
    {
        while ($node = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), '{$this->classNamePrefix}')]")->item(0)) {
            $node->parentNode->replaceChild(
                $this->getInnerNode($node),
                $node
            );
        }

        $this->removeTemporaryWrapper();

        return rtrim($this->doc->saveHTML());
    }

    private function getInnerNode(\DOMNode $node): \DOMNode
    {
        $doc = $node->ownerDocument;
        $fragment = $doc->createDocumentFragment();
        foreach ($node->childNodes as $child) {
            $fragment->appendChild($child->cloneNode(true));
        }

        return $fragment;
    }

    private function initDocument(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;

        $doc->loadHtml(
            $this->addTemporaryWrapper($html),
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );

        return $doc;
    }

    private function addTemporaryWrapper(string $html): string
    {
        return '<div>' . $html . '</div>';
    }

    private function removeTemporaryWrapper(): void
    {
        $temporaryWrapper = $this->doc->getElementsByTagName('div')->item(0);
        $temporaryWrapper = $temporaryWrapper->parentNode->removeChild($temporaryWrapper);

        while ($this->doc->firstChild) {
            $this->doc->removeChild($this->doc->firstChild);
        }

        while ($temporaryWrapper->firstChild) {
            $this->doc->appendChild($temporaryWrapper->firstChild);
        }
    }
}
