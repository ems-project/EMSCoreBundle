<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

final class HtmlEmptyTransformer extends AbstractTransformer
{
    #[\Override]
    public function getName(): TranslatableMessage
    {
        return t('transformer.html_empty', [], 'emsco-core');
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

        $stripTags = \strip_tags((string) $data);
        $trimmed = \trim(\html_entity_decode($stripTags), " \t\n\r\0\x0B\xC2\xA0");

        if ('' === $trimmed) {
            $context->setTransformed('');
        }
    }
}
