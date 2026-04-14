<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HtmlAttributeTransformer extends BaseHtmlTransformer
{
    /** @var list<string> */
    private const array UNWRAP_BLACKLIST = [
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'p', 'section', 'article', 'header', 'footer', 'nav', 'aside',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'figure', 'figcaption', 'blockquote', 'pre',
    ];

    #[\Override]
    public function getName(): string
    {
        return 'HTML Attribute';
    }

    #[\Override]
    public function supports(string $fieldTypeClass): bool
    {
        return WysiwygFieldType::class === $fieldTypeClass;
    }

    #[\Override]
    public function transform(TransformContext $context): void
    {
        if (null === $context->getData()) {
            return;
        }

        $crawler = new Crawler();
        $crawler->addContent($context->getData());

        $options = $this->resolveOptions($context->getOptions());
        $results = 0;

        if ($options['remove_value_prefix']) {
            $results += $this->removeValue($crawler, $options);
        }
        if ($options['remove']) {
            $results += $this->removeAttribute($crawler, $options);
        }

        if ($results > 0) {
            $this->setTransformed($context, $crawler);
        }
    }

    #[\Override]
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['attribute'])
            ->setDefaults([
                'element' => '*',
                'remove_value_prefix' => null,
                'remove' => false,
            ])
        ;
    }

    /**
     * @param array<mixed> $options
     */
    private function removeAttribute(Crawler $crawler, array $options): int
    {
        $result = 0;
        $attribute = $options['attribute'];
        $xpath = \sprintf('//%s[@%s]', $options['element'], $attribute);

        foreach ($this->crawl($crawler, $xpath) as $element) {
            $element->removeAttribute($attribute);
            if (0 === $element->attributes->length) {
                $this->unwrap($element);
            }
            ++$result;
        }

        return $result;
    }

    /**
     * @param array<mixed> $options
     */
    private function removeValue(Crawler $crawler, array $options): int
    {
        $attribute = $options['attribute'];
        $removeValuePrefix = $options['remove_value_prefix'];

        $xpathFormat = "//%s[contains(concat(' ', normalize-space(@%s), ' '), '%s')]";
        $xpath = \sprintf($xpathFormat, $options['element'], $attribute, $removeValuePrefix);
        $elements = $this->crawl($crawler, $xpath);

        if ('class' === $attribute) {
            return $this->removeValueClass($elements, $removeValuePrefix);
        }
        if ('style' === $attribute) {
            return $this->removeValueStyle($elements, $removeValuePrefix);
        }

        return 0;
    }

    /**
     * @param \DOMElement[] $elements
     */
    private function removeValueClass(iterable $elements, string $removeValuePrefix): int
    {
        $result = 0;

        foreach ($elements as $element) {
            $attributeValue = $element->getAttribute('class');

            $exploded = \explode(' ', $attributeValue);
            $filter = \array_filter(
                $exploded,
                fn (string $class) => !\str_starts_with(\trim($class), $removeValuePrefix)
            );

            if ($filter !== $exploded) {
                ++$result;
            }

            if ([] === $filter) {
                $element->removeAttribute('class');
                if (0 === $element->attributes->length) {
                    $this->unwrap($element);
                }
                continue;
            }

            $imploded = \implode(' ', $filter);
            $element->setAttribute('class', $imploded);
        }

        return $result;
    }

    /**
     * @param \DOMElement[] $elements
     */
    private function removeValueStyle(iterable $elements, string $removeValuePrefix): int
    {
        $result = 0;

        foreach ($elements as $element) {
            $styleValue = $element->getAttribute('style');

            $exploded = \explode(';', $styleValue);
            $filter = \array_filter(
                \array_filter(
                    $exploded,
                    fn (string $style) => !\str_starts_with(\trim($style), $removeValuePrefix)
                )
            );

            if ($filter !== $exploded) {
                ++$result;
            }

            if ([] === $filter) {
                $element->removeAttribute('style');
                if (0 === $element->attributes->length) {
                    $this->unwrap($element);
                }
                continue;
            }

            $imploded = \implode(';', $filter);
            $element->setAttribute('style', $imploded.';');
        }

        return $result;
    }

    private function unwrap(\DOMElement $element): void
    {
        if (\in_array(\strtolower($element->nodeName), self::UNWRAP_BLACKLIST, true)) {
            return;
        }

        $parent = $element->parentNode;
        if (null === $parent) {
            return;
        }

        $hasContent = null !== $element->firstChild;

        if (!$hasContent) {
            $prev = $element->previousSibling;
            $next = $element->nextSibling;
            $parent->removeChild($element);

            if ($next instanceof \DOMText) {
                $next->nodeValue = \preg_replace('/^[ \t]*\n/', '', $next->nodeValue ?? '');
            }
            if ($prev instanceof \DOMText) {
                $prev->nodeValue = \rtrim($prev->nodeValue ?? '', " \t");
            }

            return;
        }

        while (null !== $element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
