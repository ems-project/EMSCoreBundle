<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Form;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;

class FieldTypeManager
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FormRegistryInterface $formRegistry
    ) {
    }

    /**
     * @param array<mixed> $inputFieldType
     */
    public function handleRequest(FieldType $fieldType, array $inputFieldType): ?string
    {
        if ($out = $this->addNewField($inputFieldType, $fieldType)) {
            return true === $out ? null : $out;
        } elseif ($out = $this->addNewSubfield($inputFieldType, $fieldType)) {
            return true === $out ? null : $out;
        } elseif ($out = $this->duplicateField($inputFieldType, $fieldType)) {
            return true === $out ? null : $out;
        } elseif ($this->removeField($inputFieldType, $fieldType)) {
            return null;
        }
        $this->reorderFields($inputFieldType, $fieldType);

        return null;
    }

    public static function isValidName(string $name): bool
    {
        if (\in_array($name, [Mapping::HASH_FIELD, Mapping::SIGNATURE_FIELD, Mapping::FINALIZED_BY_FIELD, Mapping::FINALIZATION_DATETIME_FIELD])) {
            return false;
        }

        return \preg_match('/^[a-z][a-z0-9\-_]*$/i', $name) && \strlen($name) <= 100;
    }

    /**
     * @param array<mixed> $formArray
     */
    private function addNewField(array $formArray, FieldType $fieldType): bool|string
    {
        if (\array_key_exists('add', $formArray)) {
            if (isset($formArray['ems:internal:add:field:name'])
                && 0 != \strcmp((string) $formArray['ems:internal:add:field:name'], '')
                && isset($formArray['ems:internal:add:field:class'])
                && 0 != \strcmp((string) $formArray['ems:internal:add:field:class'], '')) {
                if (static::isValidName($formArray['ems:internal:add:field:name'])) {
                    $fieldTypeNameOrServiceName = $formArray['ems:internal:add:field:class'];
                    $fieldName = $formArray['ems:internal:add:field:name'];
                    $dataFieldType = $this->getDataFieldType($fieldTypeNameOrServiceName);
                    $child = new FieldType();
                    $child->setName($fieldName);
                    $child->setType($fieldTypeNameOrServiceName);
                    $child->setParent($fieldType);
                    $child->setOptions($dataFieldType->getDefaultOptions($fieldName));
                    $fieldType->addChild($child);
                    $this->logger->notice('log.contenttype.field.added', [
                        'field_name' => $fieldName,
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                    ]);

                    return '_ems_'.$child->getName().'_modal_options';
                } else {
                    $this->logger->error('log.contenttype.field.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/ !'.Mapping::HASH_FIELD.' !'.Mapping::HASH_FIELD,
                    ]);
                }
            } else {
                $this->logger->error('log.contenttype.field.name_mandatory', [
                ]);
            }

            return true;
        } else {
            foreach ($fieldType->getChildren() as $child) {
                if (!$child instanceof FieldType) {
                    throw new \RuntimeException('Unexpected FieldType object');
                }
                if (!$child->getDeleted()) {
                    $out = $this->addNewField($formArray['ems_'.$child->getName()], $child);
                    if (false !== $out) {
                        return '_ems_'.$child->getName().$out;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $formArray
     */
    private function addNewSubfield(array $formArray, FieldType $fieldType): bool|string
    {
        if (\array_key_exists('subfield', $formArray)) {
            if (isset($formArray['ems:internal:add:subfield:name'])
                && 0 !== \strcmp((string) $formArray['ems:internal:add:subfield:name'], '')) {
                if (static::isValidName($formArray['ems:internal:add:subfield:name'])) {
                    $child = new FieldType();
                    $child->setName($formArray['ems:internal:add:subfield:name']);
                    $child->setType(SubfieldType::class);
                    $child->setParent($fieldType);
                    $fieldType->addChild($child);
                    $this->logger->notice('log.contenttype.subfield.added', [
                        'subfield_name' => $formArray['ems:internal:add:subfield:name'],
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                    ]);

                    return '_ems_'.$child->getName().'_modal_options';
                } else {
                    $this->logger->error('log.contenttype.subfield.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/',
                    ]);
                }
            } else {
                $this->logger->error('log.contenttype.subfield.name_mandatory', [
                ]);
            }

            return true;
        } else {
            foreach ($fieldType->getChildren() as $child) {
                if (!$child instanceof FieldType) {
                    throw new \RuntimeException('Unexpected FieldType object');
                }
                if (!$child->getDeleted()) {
                    $out = $this->addNewSubfield($formArray['ems_'.$child->getName()], $child);
                    if (false !== $out) {
                        return '_ems_'.$child->getName().$out;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $formArray
     */
    private function duplicateField(array $formArray, FieldType $fieldType): bool|string
    {
        if (\array_key_exists('duplicate', $formArray)) {
            if (isset($formArray['ems:internal:add:subfield:target_name'])
                && 0 !== \strcmp((string) $formArray['ems:internal:add:subfield:target_name'], '')) {
                if (static::isValidName($formArray['ems:internal:add:subfield:target_name'])) {
                    $new = clone $fieldType;
                    $new->setName($formArray['ems:internal:add:subfield:target_name']);
                    if ($parent = $new->getParent()) {
                        $parent->addChild($new);
                    }

                    $this->logger->notice('log.contenttype.field.added', [
                        'field_name' => $formArray['ems:internal:add:subfield:target_name'],
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                    ]);

                    return 'first_ems_'.$new->getName().'_modal_options';
                } else {
                    $this->logger->error('log.contenttype.field.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/ !'.Mapping::HASH_FIELD.' !'.Mapping::HASH_FIELD,
                    ]);
                }
            } else {
                $this->logger->error('log.contenttype.field.name_mandatory', [
                ]);
            }

            return true;
        } else {
            foreach ($fieldType->getChildren() as $child) {
                if (!$child instanceof FieldType) {
                    throw new \RuntimeException('Unexpected FieldType object');
                }
                if (!$child->getDeleted()) {
                    $out = $this->duplicateField($formArray['ems_'.$child->getName()], $child);
                    if (false !== $out) {
                        if (\is_string($out) && \str_starts_with($out, 'first')) {
                            return \substr($out, 5);
                        }

                        return '_ems_'.$child->getName().$out;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $formArray
     */
    private function removeField(array $formArray, FieldType $fieldType): bool
    {
        if (\array_key_exists('remove', $formArray)) {
            $fieldType->setDeleted(true);
            $this->logger->notice('log.contenttype.field.deleted', [
                'field_name' => $fieldType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
            ]);

            return true;
        } else {
            foreach ($fieldType->getChildren() as $child) {
                if (!$child instanceof FieldType) {
                    throw new \RuntimeException('Unexpected FieldType object');
                }
                if (!$child->getDeleted() && $this->removeField($formArray['ems_'.$child->getName()], $child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $formArray
     */
    private function reorderFields(array $formArray, FieldType $fieldType): bool
    {
        if (\array_key_exists('reorder', $formArray)) {
            /** @var string[] $keys */
            $keys = \array_keys($formArray);
            $fields = [];
            foreach ($fieldType->getChildren() as $child) {
                $fields[] = $child;
            }
            \usort($fields, function (FieldType $a, FieldType $b) use ($keys) {
                $orderA = \array_search('ems_'.$a->getName(), $keys, true);
                $orderB = \array_search('ems_'.$b->getName(), $keys, true);
                if (!\is_int($orderA)) {
                    $orderA = $a->getOrderKey();
                }
                if (!\is_int($orderB)) {
                    $orderB = $b->getOrderKey();
                }

                return $orderA <=> $orderB;
            });
            $fieldType->getChildren()->clear();
            foreach ($fields as $field) {
                $fieldType->getChildren()->add($field);
            }
            $this->logger->notice('log.contenttype.field.reordered', [
                'field_name' => $fieldType->getName(),
            ]);

            return true;
        } else {
            foreach ($fieldType->getChildren() as $child) {
                if (!$child instanceof FieldType) {
                    throw new \RuntimeException('Unexpected FieldType object');
                }
                if (!$child->getDeleted() && $this->reorderFields($formArray['ems_'.$child->getName()], $child)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getDataFieldType(string $fieldTypeNameOrServiceName): DataFieldType
    {
        $dataFieldType = $this->formRegistry->getType($fieldTypeNameOrServiceName)->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            return $dataFieldType;
        }
        throw new ElasticmsException(\sprintf('Expecting a DataFieldType instance, got a %s', $dataFieldType::class));
    }
}
