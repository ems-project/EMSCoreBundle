<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use Doctrine\ORM\EntityNotFoundException;
use EMS\CoreBundle\Entity\EntityInterface;
use EMS\CoreBundle\Service\EntityServiceInterface;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<string|string[],EntityInterface|EntityInterface[]>
 */
readonly class EntityNameModelTransformer implements DataTransformerInterface
{
    public function __construct(private EntityServiceInterface $entityService, private bool $multiple = false)
    {
    }

    #[\Override]
    public function transform(mixed $value)
    {
        if (null === $value) {
            return null;
        }

        return $this->multiple ? $this->transformMultiple($value) : $this->transformSingle($value);
    }

    #[\Override]
    public function reverseTransform(mixed $value)
    {
        return $this->multiple ? $this->reverseTransformMultiple($value) : $this->reverseTransformSingle($value);
    }

    private function transformSingle(mixed $value): EntityInterface
    {
        $name = Type::string($value);
        $entity = $this->entityService->getByItemName($value);
        if (!$entity instanceof EntityInterface) {
            throw new EntityNotFoundException(\sprintf('for name %s', $name));
        }

        return $entity;
    }

    /**
     * @return EntityInterface[]
     */
    private function transformMultiple(mixed $value): array
    {
        $names = Type::array($value);
        $values = [];
        foreach ($names as $name) {
            $values[] = $this->transformSingle($name);
        }

        return $values;
    }

    /**
     * @return string[]
     */
    private function reverseTransformMultiple(mixed $value): array
    {
        $entities = Type::array($value);
        $values = [];
        foreach ($entities as $entity) {
            $values[] = $this->reverseTransformSingle($entity);
        }

        return $values;
    }

    private function reverseTransformSingle(mixed $value): string
    {
        if (!$value instanceof EntityInterface) {
            throw new \RuntimeException(\sprintf('EntityInterface expected got %s', \get_debug_type($value)));
        }

        return $value->getName();
    }
}
