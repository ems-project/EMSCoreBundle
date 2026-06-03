<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HtmlRemoveNodeTransformer extends BaseHtmlTransformer
{
    #[\Override]
    public function getName(): string
    {
        return 'HTML Remove node';
    }

    #[\Override]
    public function supports(string $fieldTypeClass): bool
    {
        return WysiwygFieldType::class === $fieldTypeClass;
    }

    #[\Override]
    public function transform(TransformContext $context): void
    {
        if (null == $data = $context->getData()) {
            return;
        }

        $crawler = new Crawler();
        $crawler->addContent($data);
        $options = $this->resolveOptions($context->getOptions());

        $results = 0;
        foreach ($this->crawl($crawler, $options['xpath']) as $element) {
            if (null === $parentNode = $element->parentNode) {
                continue;
            }

            $parentNode->removeChild($element);
            $this->removeEmptyParents($parentNode);

            ++$results;
        }

        if ($results > 0) {
            $this->setTransformed($context, $crawler);
        }
    }

    #[\Override]
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'element' => '*',
                'attribute' => null,
                'attribute_contains' => null,
                'xpath' => null,
            ])
            ->setNormalizer('xpath', function (Options $options, $value) {
                if ($value) {
                    return $value;
                }

                $element = $options['element'] ?? null;
                $attribute = $options['attribute'] ?? null;
                $attributeContains = $options['attribute_contains'] ?? null;

                if ($attribute && $attributeContains) {
                    return \vsprintf("//%s[contains(concat(' ', normalize-space(@%s), ' '), '%s')]", [
                        $element, $attribute, $attributeContains,
                    ]);
                }

                return \sprintf('//%s', $element);
            })
        ;
    }

    private function removeEmptyParents(\DOMNode $node): void
    {
        $document = $node->ownerDocument;
        if (null === $document) {
            return;
        }

        while ($node instanceof \DOMElement
            && null !== $node->parentNode
            && $node !== $document->documentElement
            && $this->isEmptyNode($node)) {
            $parent = $node->parentNode;
            $this->unwrapEmpty($node, $parent);
            $node = $parent;
        }
    }

    private function isEmptyNode(\DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if (\XML_ELEMENT_NODE === $child->nodeType) {
                return false;
            }
            if (\XML_TEXT_NODE === $child->nodeType
                && '' !== \trim((string) $child->nodeValue, " \t\n\r\0\x0B\xC2\xA0")) {
                return false;
            }
        }

        return true;
    }
}
