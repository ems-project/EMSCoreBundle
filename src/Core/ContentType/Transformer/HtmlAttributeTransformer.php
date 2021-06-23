<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HtmlAttributeTransformer extends AbstractTransformer
{
    public function getName(): string
    {
        return 'HTML Attribute';
    }

    public function supports(string $class): bool
    {
        return WysiwygFieldType::class === $class;
    }

    public function transform(TransformContext $context): void
    {
        if (null == $data = $context->getData()) {
            return;
        }

        $crawler = new Crawler();
        $crawler->addContent($context->getData());
        $options = $this->resolveOptions($context->getOptions());
        $results = 0;

        if ($options['remove_value_prefix']) {
            $results = $results + $this->removeValue($crawler, $options);
        }
        if ($options['remove']) {
            $results = $results + $this->removeAttribute($crawler, $options);
        }

        if ($results > 0) {
            $this->setTransformed($context, $crawler);
        }
    }

    private function setTransformed(TransformContext $context, Crawler $crawler): void
    {
        $data = $context->getData();
        $transformed = $crawler->outerHtml();

        if (false === \strpos($data, '<html>')) {
            $transformed = \str_replace(['<html>', '</html>'], '', $transformed);
        }
        if (false === \strpos($data, '<body>')) {
            $transformed = \str_replace(['<body>', '</body>'], '', $transformed);
        }

        $context->setTransformed($transformed);
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
            $filter = \array_filter($exploded,
                fn (string $class) => \substr(\trim($class), 0, \strlen($removeValuePrefix)) !== $removeValuePrefix
            );

            if ($filter !== $exploded) {
                ++$result;
            }

            if (0 === \count($filter)) {
                $element->removeAttribute('class');
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
            $filter = \array_filter(\array_filter($exploded,
                fn (string $style) => \substr(\trim($style), 0, \strlen($removeValuePrefix)) !== $removeValuePrefix)
            );

            if ($filter !== $exploded) {
                ++$result;
            }

            if (0 === \count($filter)) {
                $element->removeAttribute('style');
                continue;
            }

            $imploded = \implode(';', $filter);
            $element->setAttribute('style', $imploded.';');
        }

        return $result;
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
}
