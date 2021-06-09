<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TestTransformer implements ContentTransformerInterface
{
    public function getName(): string
    {
        return 'testje';
    }

    public function supports(string $class): bool
    {
        return WysiwygFieldType::class === $class;
    }

    public function transform(TransformContext $context): void
    {

    }
}
