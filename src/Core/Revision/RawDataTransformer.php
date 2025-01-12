<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DateFieldType;

final class RawDataTransformer
{
    /**
     * Will add virtual field(s) to the passed $data array.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function transform(FieldType $fieldType, array $data): array
    {
        $out = [];
        /** @var FieldType $child */
        foreach ($fieldType->getChildren() as $child) {
            if ($child->getDeleted()) {
                continue;
            }
            /** @var DateFieldType $type */
            $type = $child->getType();
            if ($type::isVirtual($child->getOptions())) {
                if ($type::isContainer()) {
                    $jsonNames = $type::getJsonNames($child);
                    if (0 === \count($jsonNames)) {
                        $out[$child->getName()] = self::transform($child, $data);
                    } else {
                        $colectedData = [];
                        foreach ($jsonNames as $name) {
                            if (isset($data[$name])) {
                                $colectedData[$name] = self::transform($child, $data[$name]);
                            }
                        }
                        $out[$child->getName()] = $colectedData;
                    }
                } else {
                    $out[$child->getName()] = $type::filterSubField($data, $child->getOptions());
                }
            } else {
                if ($type::isContainer()) {
                    if (isset($data[$child->getName()])) {
                        if ($type::isCollection()) {
                            if (\is_array($data[$child->getName()])) {
                                $out[$child->getName()] = [];
                                foreach ($data[$child->getName()] as $idx => $item) {
                                    $out[$child->getName()][$idx] = self::transform($child, $item);
                                }
                            }
                        } elseif (\is_array($data[$child->getName()])) {
                            $out[$child->getName()] = self::transform($child, $data[$child->getName()]);
                        } else {
                            $out[$child->getName()] = $data[$child->getName()];
                        }
                    }
                } else {
                    if (isset($data[$child->getName()])) {
                        $out[$child->getName()] = $data[$child->getName()];
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Will remove virtual field(s) from the passed $data array.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function reverseTransform(FieldType $fieldType, array $data): array
    {
        $out = [];
        /** @var FieldType $child */
        foreach ($fieldType->getChildren() as $child) {
            if ($child->getDeleted()) {
                continue;
            }
            /** @var DateFieldType $type */
            $type = $child->getType();

            if ($type::isVirtual($child->getOptions())) {
                if (isset($data[$child->getName()]) && !empty($data[$child->getName()])) {
                    if ($type::isContainer()) {
                        $jsonNames = $type::getJsonNames($child);
                        if (0 === \count($jsonNames)) {
                            $out = \array_merge_recursive($out, self::reverseTransform($child, $data[$child->getName()]));
                        } else {
                            foreach ($jsonNames as $name) {
                                if (!\is_array($data[$child->getName()][$name] ?? null)) {
                                    continue;
                                }
                                $out = \array_merge($out, [$name => self::reverseTransform($child, $data[$child->getName()][$name])]);
                            }
                        }
                    } else {
                        $out = \array_merge_recursive($out, $data[$child->getName()]);
                    }
                }
            } else {
                if ($type::isContainer() && isset($data[$child->getName()]) && \is_array($data[$child->getName()])) {
                    if (!empty($data[$child->getName()])) {
                        if ($type::isCollection()) {
                            $out[$child->getName()] = [];
                            foreach ($data[$child->getName()] as $itemIdx => $item) {
                                $out[$child->getName()][$itemIdx] = self::reverseTransform($child, $item);
                            }
                        } else {
                            $out[$child->getName()] = self::reverseTransform($child, $data[$child->getName()]);
                        }

                        if (\is_array($out[$child->getName()]) && empty($out[$child->getName()])) {
                            unset($out[$child->getName()]);
                        }
                    }
                } else {
                    if (isset($data[$child->getName()])) {
                        $out[$child->getName()] = $data[$child->getName()];
                    }
                }
            }
        }

        return $out;
    }
}
