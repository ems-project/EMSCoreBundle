<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use EMS\Helpers\Standard\Json;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractTransformer implements ContentTransformerInterface
{
    protected function configureOptions(OptionsResolver $resolver): void
    {
    }

    #[\Override]
    public function validateConfig(string $config): ?string
    {
        try {
            $this->resolveOptions(Json::decode($config));

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    protected function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
