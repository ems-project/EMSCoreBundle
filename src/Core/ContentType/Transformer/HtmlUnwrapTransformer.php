<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HtmlUnwrapTransformer extends BaseHtmlTransformer
{
    #[\Override]
    public function getName(): string
    {
        return 'HTML Unwrap';
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

        $xpath = \implode('|', \array_map(
            fn (string $el) => \sprintf('//%s[not(@*)]', $el),
            $options['elements']
        ));

        $result = 0;
        foreach ($this->crawl($crawler, $xpath) as $element) {
            $this->unwrap($element);
            ++$result;
        }

        if ($result > 0) {
            $this->setTransformed($context, $crawler);
        }
    }

    #[\Override]
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['elements'])
            ->setAllowedTypes('elements', 'string[]')
        ;
    }
}
