<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HtmlAttributeTransformer implements ContentTransformerInterface
{
    public function getName(): string
    {
        return 'HTML Attribute Value';
    }

    public function supports(string $class): bool
    {
        return WysiwygFieldType::class === $class;
    }

    public function transform(TransformContext $context): void
    {
        $options = $this->resolveOptions($context);

        $crawler = new Crawler();
        $crawler->addContent($context->getData());

        if ($options['remove_value_prefix']) {
            $this->removeValue($crawler, $options);
        }

        if ($options['remove']) {
            $this->removeAttribute($crawler, $options);
        }

        $context->setTransformed($crawler->filterXPath('//body')->html());
    }

    /**
     * @param array<mixed> $options
     */
    private function removeAttribute(Crawler $crawler, array $options): void
    {
        $attribute = $options['attribute'];
        $xpath = \sprintf('//%s[@%s]', $options['element'], $attribute);

        foreach ($this->crawl($crawler, $xpath) as $element) {
            $element->removeAttribute($attribute);
        }
    }

    /**
     * @param array<mixed> $options
     */
    private function removeValue(Crawler $crawler, array $options): void
    {
        $attribute = $options['attribute'];
        $removeValuePrefix = $options['remove_value_prefix'];

        $xpathFormat = "//%s[contains(concat(' ', normalize-space(@%s), ' '), '%s')]";
        $xpath = \sprintf($xpathFormat, $options['element'], $attribute, $removeValuePrefix);
        $elements = $this->crawl($crawler, $xpath);

        if ('class' === $attribute) {
            $this->removeValueClass($elements, $removeValuePrefix);
        }
        if ('style' === $attribute) {
            $this->removeValueStyle($elements, $removeValuePrefix);
        }
    }

    /**
     * @param \DOMElement[] $elements
     */
    private function removeValueClass(iterable $elements, string $removeValuePrefix): void
    {
        foreach ($elements as $element) {
            $attributeValue = $element->getAttribute('class');

            $exploded = \explode(' ', $attributeValue);
            $filter = \array_filter($exploded,
                fn (string $class) => \substr(\trim($class), 0, \strlen($removeValuePrefix)) !== $removeValuePrefix
            );

            if (0 === \count($filter)) {
                $element->removeAttribute('class');
                continue;
            }

            $imploded = \implode(' ', $filter);
            $element->setAttribute('class', $imploded);
        }
    }

    /**
     * @param \DOMElement[] $elements
     */
    private function removeValueStyle(iterable $elements, string $removeValuePrefix): void
    {
        foreach ($elements as $element) {
            $styleValue = $element->getAttribute('style');

            $exploded = \explode(';', $styleValue);
            $filter = \array_filter(\array_filter($exploded,
                fn (string $style) => \substr(\trim($style), 0, \strlen($removeValuePrefix)) !== $removeValuePrefix)
            );

            if (0 === \count($filter)) {
                $element->removeAttribute('style');
                continue;
            }

            $imploded = \implode(';', $filter);
            $element->setAttribute('style', $imploded.';');
        }
    }

    /**
     * @return \Generator|\DOMElement[]
     */
    private function crawl(Crawler $crawler, string $xPath): \Generator
    {
        foreach ($crawler->filterXPath($xPath) as $element) {
            if ($element instanceof \DOMElement) {
                yield $element;
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function resolveOptions(TransformContext $context): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setRequired(['attribute'])
            ->setDefaults([
                'element' => '*',
                'remove_value_prefix' => null,
                'remove' => false,
            ])
        ;

        return $resolver->resolve($context->getOptions());
    }
}
