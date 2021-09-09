<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

final class ContentTransformers
{
    /** @var ContentTransformerInterface[] */
    private array $transformers = [];

    public function add(ContentTransformerInterface $transformer): void
    {
        $this->transformers[] = $transformer;
    }

    public function get(string $class): ContentTransformerInterface
    {
        foreach ($this->transformers as $transformer) {
            if (\get_class($transformer) === $class) {
                return $transformer;
            }
        }

        throw new \RuntimeException(\sprintf('Transformer "%s" not found', $class));
    }

    /**
     * @return array<string, string>
     */
    public function getMigrationOptionsChoices(string $fieldTypeClass): array
    {
        $choices = [];

        foreach ($this->transformers as $transformer) {
            if (!$transformer->supports($fieldTypeClass)) {
                continue;
            }

            $choices[$transformer->getName()] = \get_class($transformer);
        }

        return $choices;
    }
}
