<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CoreBundle\Core\ContentType\DataFieldFormOptions;
use EMS\CoreBundle\Exception\DataFormatException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\OuuidFieldType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @implements \IteratorAggregate<DataField>
 * @implements \ArrayAccess<int, mixed>
 */
#[Assert\Callback(['Vendor\Package\Validator', 'validate'])]
class DataField implements \ArrayAccess, \IteratorAggregate, \Stringable
{
    /**
     * link to the linked FieldType.
     */
    private ?FieldType $fieldType = null;

    /**
     * TODO: a retirer???
     */
    private ?int $orderKey = null;

    private ?DataField $parent = null;

    /** @var ArrayCollection<int, DataField> */
    private ArrayCollection $children;

    /** @var array<mixed>|string|int|float|bool|null */
    private $rawData;

    /** @var mixed */
    private $inputValue;

    /** @var string[] */
    private array $messages = [];

    private bool $marked = false;

    private ?DataFieldFormOptions $formOptions = null;

    public function setChildrenFieldType(FieldType $fieldType): void
    {
        // TODO: test if sub colletion for nested collection
        /* @var FieldType $subType */
        $this->children->first();
        foreach ($fieldType->getChildren() as $subType) {
            if (!$subType->getDeleted()) {
                $child = $this->children->current();
                if ($child) {
                    $child->setFieldType($subType);
                    $child->setOrderKey($subType->getOrderKey());
                    $child->setChildrenFieldType($subType);
                }
                $this->children->next();
            }
        }
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->children->offsetSet($offset, $value);
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        if (\is_int($offset) && !$this->children->offsetExists($offset) && null !== $this->fieldType && $this->fieldType->getChildren()->count() > 0) {
            $value = new DataField();
            $this->children->offsetSet($offset, $value);

            return true;
        }

        return $this->children->offsetExists($offset);
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->children->offsetUnset($offset);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return (0 === $offset) ? $this->children : $this->children->offsetGet($offset);
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->children->getIterator();
    }

    #[Assert\Callback]
    public function isDataFieldValid(ExecutionContextInterface $context): void
    {
        // TODO: why is it not working? See https://stackoverflow.com/a/25265360
        // Transformed: (but not used??)
        $context
            ->buildViolation('Haaaaha')
            ->atPath('textValue')
            ->addViolation();
    }

    public function propagateOuuid(string $ouuid): void
    {
        $fieldType = $this->getFieldType();

        if (null !== $fieldType && 0 == \strcmp(OuuidFieldType::class, $fieldType->getType())) {
            $this->setTextValue($ouuid);
        }
        foreach ($this->children as $child) {
            $child->propagateOuuid($ouuid);
        }
    }

    #[\Override]
    public function __toString(): string
    {
        if (null !== $this->rawData && \is_string($this->rawData)) {
            return $this->rawData;
        }

        return Json::encode($this->rawData);
    }

    public function orderChildren(): void
    {
        $children = null;

        if (null == $this->getFieldType() && null !== $parent = $this->getParent()) {
            $children = $parent->giveFieldType()->getChildren();
        } elseif (0 != \strcmp($this->giveFieldType()->getType(), CollectionFieldType::class)) {
            $children = $this->giveFieldType()->getChildren();
        }

        if ($children) {
            $temp = new ArrayCollection();
            /** @var FieldType $childField */
            foreach ($children as $childField) {
                if (!$childField->getDeleted()) {
                    $value = $this->__get('ems_'.$childField->getName());
                    if ($value) {
                        $value->setOrderKey($childField->getOrderKey());
                        $temp->add($value);
                    }
                }
            }
            $this->children = $temp;
        }

        foreach ($this->children as $child) {
            $child->orderChildren();
        }
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->children = new ArrayCollection();

        // TODO: should use the clone method
        $a = \func_get_args();
        $i = \func_num_args();
        if ($i >= 1 && $a[0] instanceof DataField) {
            /** @var DataField $ancestor */
            $ancestor = $a[0];
            $this->fieldType = $ancestor->getFieldType();
            $this->orderKey = $ancestor->orderKey;
            $this->rawData = $ancestor->rawData;
            if ($i >= 2 && $a[1] instanceof DataField) {
                $this->parent = $a[1];
            }

            foreach ($ancestor->getChildren() as $key => $child) {
                $this->addChild(new DataField($child, $this), $key);
            }
        }
    }

    public function __set(string $key, mixed $input): void
    {
        if (!\str_starts_with($key, 'ems_')) {
            throw new \Exception('unprotected ems set with key '.$key);
        } else {
            $key = \substr($key, 4);
        }

        if (null === $input || $input instanceof DataField) {
            $found = false;
            if (null !== $input) {
                /* @var DataField $input */
                $input->setParent($this);
            }

            if (null === $this->getFieldType()) {
                if (null === $parent = $this->getParent()) {
                    throw new \Exception('null parent !!!!!! '.$key);
                } else {
                    $this->updateDataStructure($parent->giveFieldType());
                }
            }

            /** @var DataField $dataField */
            foreach ($this->children as &$dataField) {
                $fieldType = $dataField->getFieldType();

                if (null !== $fieldType && !$fieldType->getDeleted() && 0 == \strcmp($key, $fieldType->getName())) {
                    $found = true;
                    $dataField = $input;
                    break;
                }
            }
            if (!$found) {
                throw new \Exception('__set an unknow kind of field '.$key);
            }
        } else {
            throw new \Exception('__set a DataField wich is not a valid object'.$key);
        }
    }

    /**
     * @deprecated
     *
     * @throws \Exception
     */
    public function updateDataStructure(FieldType $meta): never
    {
        throw new \Exception('Deprecated method');
    }

    /**
     * Assign data in dataValues based on the elastic index content.
     *
     * @deprecated
     *
     * @param array<mixed> $elasticIndexDatas
     *
     * @throws \Exception
     */
    public function updateDataValue(array &$elasticIndexDatas, mixed $isMigration = false): never
    {
        throw new \Exception('Deprecated method');
    }

    /**
     * @param Collection<int, FieldType> $fieldTypes
     */
    public function linkFieldType(Collection $fieldTypes): void
    {
        $index = 0;
        /** @var FieldType $fieldType */
        foreach ($fieldTypes as $fieldType) {
            if (!$fieldType->getDeleted()) {
                /** @var DataField $child */
                $child = $this->children->get($index);
                $child->setFieldType($fieldType);
                $child->setParent($this);
                $child->linkFieldType($fieldType->getChildren());
                ++$index;
            }
        }
    }

    public function __get(string $key): ?DataField
    {
        if (!\str_starts_with($key, 'ems_')) {
            throw new \Exception('unprotected ems get with key '.$key);
        } else {
            $key = \substr($key, 4);
        }

        $fieldType = $this->getFieldType();

        if ($fieldType && 0 == \strcmp($fieldType->getType(), CollectionFieldType::class)) {
            // Symfony wants iterate on children
            return $this;
        } else {
            /** @var DataField $dataField */
            foreach ($this->children as $dataField) {
                $childFieldType = $dataField->getFieldType();

                if (null !== $childFieldType && !$childFieldType->getDeleted() && 0 == \strcmp($key, $childFieldType->getName())) {
                    return $dataField;
                }
            }
        }

        return null;
    }

    public function setTextValue(?string $rawData): DataField
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTextValue()
    {
        if (\is_array($this->rawData) && 0 === \count($this->rawData)) {
            return null; // empty array means null/empty
        }

        if (null !== $this->rawData && !\is_string($this->rawData)) {
            if (\is_array($this->rawData) && 1 === \count($this->rawData) && \is_string($stringValue = \reset($this->rawData))) {
                $this->addMessage('String expected, single string in array instead');

                return $stringValue;
            }
            $this->addMessage('String expected from the DB: '.\print_r($this->rawData, true));
        }

        return $this->rawData;
    }

    public function setPasswordValue(?string $passwordValue): self
    {
        if (null !== $passwordValue) {
            $this->setTextValue($passwordValue);
        }

        return $this;
    }

    public function getPasswordValue(): ?string
    {
        return $this->getTextValue();
    }

    public function setResetPasswordValue(?bool $resetPasswordValue): self
    {
        if (null !== $resetPasswordValue && $resetPasswordValue) {
            $this->setTextValue(null);
        }

        return $this;
    }

    public function getResetPasswordValue(): bool
    {
        return false;
    }

    public function setFloatValue(?float $rawData): DataField
    {
        $this->rawData = $rawData;

        return $this;
    }

    public function getFloatValue(): ?float
    {
        if (\is_array($this->rawData) && empty($this->rawData)) {
            return null;
        }
        if (null === $this->rawData || '' === $this->rawData) {
            return null;
        }
        if (\is_numeric($this->rawData) && \is_finite((float) $this->rawData)) {
            return (float) $this->rawData;
        }

        throw new DataFormatException('Float or double expected: '.\print_r($this->rawData, true));
    }

    /**
     * Set dataValue, the set of field is delegated to the corresponding fieldType class.
     *
     * @throws \Exception
     */
    public function setDataValue(mixed $inputString): self
    {
        if ($fieldType = $this->getFieldType()) {
            $fieldType->setDataValue($inputString, $this);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(string $message): void
    {
        if (!\in_array($message, $this->messages)) {
            $this->messages[] = $message;
        }
    }

    /**
     * @param ?array<string, mixed> $rawData
     */
    public function setArrayTextValue(?array $rawData): DataField
    {
        if (null === $rawData) {
            $this->rawData = null;
        } else {
            foreach ($rawData as $item) {
                if (!\is_string($item)) {
                    throw new DataFormatException('String expected: '.\print_r($item, true));
                }
            }
            $this->rawData = $rawData;
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getArrayTextValue(): ?array
    {
        if (null === $this->rawData) {
            return null;
        }

        if (!\is_array($this->rawData)) {
            $this->addMessage('Array expected from the DB: '.\print_r($this->rawData, true));

            return null;
        }

        $textValue = $this->rawData;

        foreach ($textValue as $idx => $item) {
            if (!\is_string($item)) {
                $this->addMessage('String expected for the item '.$idx.' from the DB: '.\print_r($this->rawData, true));
                $textValue[$idx] = '';
            }
        }

        return $textValue;
    }

    /**
     * @return mixed
     */
    public function getIntegerValue()
    {
        if (\is_array($this->rawData)) {
            $this->addMessage('Integer expected array found: '.\print_r($this->rawData, true));

            return \is_countable($this->rawData) ? \count($this->rawData) : 0; // empty array means null/empty
        }

        if (null === $this->rawData || \is_int($this->rawData)) {
            return $this->rawData;
        } elseif (\intval($this->rawData) || '0' === $this->rawData) {
            return \intval($this->rawData);
            //             return $this->rawData;
            //             throw new DataFormatException('Integer expected: '.print_r($this->rawData, true));
        }
        $this->addMessage('Integer expected: '.\print_r($this->rawData, true));

        return $this->rawData;
    }

    /**
     * Set integerValue.
     *
     * @param string|int|null $rawData
     */
    public function setIntegerValue($rawData): self
    {
        if (null === $rawData || \is_int($rawData)) {
            $this->rawData = $rawData;
        } elseif (\intval($rawData) || '0' === $rawData) {
            $this->rawData = \intval($rawData);
        } else {
            $this->addMessage('Integer expected: '.\print_r($rawData, true));
            $this->rawData = $rawData;
        }

        return $this;
    }

    public function getBooleanValue(): ?bool
    {
        if (\is_array($this->rawData) && 0 === \count($this->rawData)) {
            return null; // empty array means null/empty
        }

        if (null !== $this->rawData && !\is_bool($this->rawData)) {
            throw new DataFormatException('Boolean expected: '.\print_r($this->rawData, true));
        }

        return $this->rawData;
    }

    /**
     * @return array<int, \DateTime|false>
     */
    public function getDateValues(): array
    {
        $out = [];
        if (null !== $this->rawData) {
            if (!\is_array($this->rawData)) {
                throw new DataFormatException('Array expected: '.\print_r($this->rawData, true));
            }
            foreach ($this->rawData as $item) {
                $out[] = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $item);
            }
        }

        return $out;
    }

    public function setBooleanValue(?bool $rawData): DataField
    {
        $this->rawData = $rawData;

        return $this;
    }

    public function getRootDataField(): self
    {
        $out = $this;
        while ($out->hasParent()) {
            $out = $out->giveParent();
        }

        return $out;
    }

    public function setMarked(bool $marked): self
    {
        $this->marked = $marked;

        return $this;
    }

    public function isMarked(): bool
    {
        return $this->marked;
    }

    public function setEncodedText(?string $text): self
    {
        $this->rawData = $text ? Json::decode($text) : [];

        return $this;
    }

    public function getEncodedText(): string
    {
        return Json::encode($this->rawData);
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     */
    public function setOrderKey($orderKey): self
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey.
     *
     * @return int
     */
    public function getOrderKey()
    {
        if (null === $this->orderKey) {
            throw new \RuntimeException('Unexpected null orderKey');
        }

        return $this->orderKey;
    }

    public function setFieldType(?FieldType $fieldType = null): self
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    public function getFieldType(): ?FieldType
    {
        return $this->fieldType;
    }

    public function giveFieldType(): FieldType
    {
        if (null === $this->fieldType) {
            throw new \RuntimeException('Unexpected null fieldType');
        }

        return $this->fieldType;
    }

    /**
     * Set parent.
     *
     * @return DataField
     */
    public function setParent(?DataField $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    public function hasParent(): bool
    {
        return null !== $this->parent;
    }

    public function giveParent(): DataField
    {
        if (null === $this->parent) {
            throw new \RuntimeException('No parent!');
        }

        return $this->parent;
    }

    public function getParent(): ?DataField
    {
        return $this->parent;
    }

    /**
     * @param int|string|null $key
     */
    public function addChild(DataField $child, $key = null): DataField
    {
        if (null === $key) {
            $this->children[] = $child;
        } else {
            $this->children[$key] = $child;
        }

        return $this;
    }

    public function removeChild(DataField $child): void
    {
        $this->children->removeElement($child);
    }

    /**
     * @return Collection<int, DataField>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Set rawData.
     *
     * @param array<mixed>|string|int|float|bool|null $rawData
     */
    public function setRawData($rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Get rawData.
     *
     * @return array<mixed>|string|int|float|bool|null
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * @return DataField
     */
    public function setInputValue(mixed $inputValue)
    {
        $this->inputValue = $inputValue;

        return $this;
    }

    /**
     * Get rawData.
     *
     * @return mixed
     */
    public function getInputValue()
    {
        return $this->inputValue;
    }

    public function getFormOptions(): ?DataFieldFormOptions
    {
        return $this->formOptions;
    }

    public function setFormOptions(?DataFieldFormOptions $formOptions): void
    {
        $this->formOptions = $formOptions;
    }
}
