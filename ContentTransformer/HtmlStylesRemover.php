<?php

namespace EMS\CoreBundle\ContentTransformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;

class HtmlStylesRemover implements ContentTransformInterface
{
    /** @var string */
    protected $input;

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

    public function canTransform(ContentTransformContext $contentTransformContext = null): bool
    {
        if (!$contentTransformContext) {
            return false;
        }

        foreach ($contentTransformContext->get() as $dataFieldType) {
            if (!$dataFieldType instanceof WysiwygFieldType) {
                return false;
            }

            return true;
        }
    }

    public function transform(string $input): string
    {
        $this->input = $input;
        $this->doc = $this->initDocument($input);
        $this->xpath = new \DOMXPath($this->doc);

        return $this->removeHtmlStyles();
    }

    public function changed(string $output): bool
    {
        if ($this->input === $output) {
            return false;
        }

        return true;
    }

    protected function removeHtmlStyles(): ?string
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

        /** @var \DOMNode $child */
        foreach ($node->childNodes as $child) {
            $fragment->appendChild($child->cloneNode(true));
        }

        return $fragment;
    }

    protected function initDocument(string $input): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $doc->loadHtml(
            $this->addTemporaryWrapper($input),
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
