<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class ArrayDataSource implements \Countable
{
    /** @param array<int, array<string, mixed>|object> $data */
    public function __construct(
        public readonly array $data,
    ) {
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * @return array<int, array<string, mixed>|object>
     */
    public function getData(int $from, int $size): array
    {
        return \array_slice($this->data, $from, $size);
    }

    public function search(string $term): self
    {
        if ('' === $term) {
            return $this;
        }

        $filterData = \array_filter($this->data, static function (array|object $data) use ($term) {
            $pattern = '/'.\preg_quote($term, '/').'/i';
            $values = \is_object($data) ? \get_object_vars($data) : $data;

            foreach ($values as $value) {
                if (\is_array($value)) {
                    continue;
                }

                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format(\DateTimeInterface::ATOM);
                }

                if (\preg_match($pattern, (string) $value)) {
                    return true;
                }
            }

            return false;
        });

        return new self(data: \array_values($filterData));
    }

    public function sort(string $propertyPath, string $orderDirection): self
    {
        $data = $this->data;

        \usort($data, static function (
            array|object $a,
            array|object $b,
        ) use ($propertyPath, $orderDirection): int {
            $propertyAccessor = new PropertyAccessor();
            $aValue = $propertyAccessor->getValue($a, $propertyPath);
            $bValue = $propertyAccessor->getValue($b, $propertyPath);

            if ($aValue instanceof \DateTimeInterface) {
                $aValue = $aValue->getTimestamp();
            }
            if ($bValue instanceof \DateTimeInterface) {
                $bValue = $bValue->getTimestamp();
            }

            if (\is_int($aValue) && \is_int($bValue)) {
                $result = $aValue <=> $bValue;
            } else {
                $result = \strcmp((string) $aValue, (string) $bValue);
            }

            return 'desc' === $orderDirection ? $result * -1 : $result;
        });

        return new self(data: $data);
    }
}
