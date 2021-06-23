<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;

final class HtmlEmptyTransformer extends AbstractTransformer
{
    public function getName(): string
    {
        return 'HTML strip tags';
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

        $stripTags = \strip_tags($data);
        $trimmed = \trim(\html_entity_decode($stripTags), " \t\n\r\0\x0B\xC2\xA0");

        if ('' === $trimmed) {
            $context->setTransformed('');
        }
    }
}
