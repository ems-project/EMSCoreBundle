<?php

namespace EMS\CoreBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use EMS\CoreBundle\Exception\DataFormatException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\OuuidFieldType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DataField.
 *
 * @implements \ArrayAccess<int, mixed>
 * @implements \IteratorAggregate<int, mixed>
 *
 * @Assert\Callback({"Vendor\Package\Validator", "validate"})
 */
class DataField implements \ArrayAccess, \IteratorAggregate
{
    /**
     * link to the linked FieldType.
     *
     * @var FieldType|null
     */
    private $fieldType;

    /**
     * TODO: a retirer???
     *
     * @var int
     */
    private $orderKey;

    /**
     * @var DataField|null
     */
    private $parent;

    /** @var Collection<int, mixed> */
    private $children;

    /** @var mixed */
    private $rawData;

    /** @var mixed */
    private $inputValue;

    /** @var array<string> */
    private $messages;

    /** @var bool */
    private $marked;

    public function setChildrenFieldType(FieldType $fieldType): void
    {
        //TODO: test if sub colletion for nested collection
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

    /**
     * @deprecated
     *
     * @param int $offset
     *
     * @throws \Exception
     */
    private function initChild(DataField $child, $offset): void
    {
        throw new \Exception('deprecate');
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->initChild($value, $offset);
        $this->children->offsetSet($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset)
    {
        if ((\is_int($offset) || \ctype_digit($offset)) && !$this->children->offsetExists($offset) && null !== $this->fieldType && $this->fieldType->getChildren()->count() > 0) {
            $value = new DataField();
            $this->initChild($value, $offset);
            $this->children->offsetSet($offset, $value);

            return true;
        }

        return $this->children->offsetExists($offset);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->children->offsetUnset($offset);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet($offset)
    {
        $value = $this->children->offsetGet($offset);
        $this->initChild($value, $offset);

        return $value;
    }

    public function getIterator()
    {
        return $this->children->getIterator();
    }

    /**
     * @Assert\Callback
     */
    public function isDataFieldValid(ExecutionContextInterface $context): void
    {
        //TODO: why is it not working? See https://stackoverflow.com/a/25265360
        //Transformed: (but not used??)
        $context
            ->buildViolation('Haaaaha')
            ->atPath('textValue')
            ->addViolation();
    }

    public function propagateOuuid(string $ouuid): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $this->getFieldType();
        if ($this->getFieldType() && 0 == \strcmp(OuuidFieldType::class, $fieldType->getType())) {
            $this->setTextValue($ouuid);
        }
        foreach ($this->children as $child) {
            $child->propagateOuuid($ouuid);
        }
    }

    public function __toString()
    {
        if (null !== $this->rawData && \is_string($this->rawData)) {
            return $this->rawData;
        }

        return \strval(\json_encode(\strval($this->rawData)));
    }

    public function orderChildren(): void
    {
        $children = null;

        /** @var FieldType $fieldType */
        $fieldType = $this->getFieldType();

        if (null == $this->getFieldType()) {
            /** @var DataField $parent */
            $parent = $this->getParent();
            /** @var FieldType $parentFieldType */
            $parentFieldType = $parent->getFieldType();
            $children = $parentFieldType->getChildren();
        } elseif (0 != \strcmp($fieldType->getType(), CollectionFieldType::class)) {
            $children = $fieldType->getChildren();
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
        $this->messages = [];

        //TODO: should use the clone method
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

            foreach ($ancestor->getChildren() as $child) {
                $this->addChild(new DataField($child, $this));
            }
        }
    }

    /**
     * @param string $key
     * @param mixed  $input
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function __set($key, $input): DataField
    {
        if (0 !== \strpos($key, 'ems_')) {
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
                if (null === $this->getParent()) {
                    throw new \Exception('null parent !!!!!! '.$key);
                } else {
                    /** @var FieldType $parentFieldType */
                    $parentFieldType = $this->getParent()->getFieldType();
                    $this->updateDataStructure($parentFieldType);
                }
            }

            /** @var DataField $dataField */
            foreach ($this->children as &$dataField) {
                /** @var FieldType $dataFieldFieldType */
                $dataFieldFieldType = $dataField->getFieldType();
                if (null != $dataField->getFieldType() && !$dataFieldFieldType->getDeleted() && 0 == \strcmp($key, $dataFieldFieldType->getName())) {
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

        return $this;
    }

    /**
     * @deprecated
     *
     * @throws \Exception
     */
    public function updateDataStructure(FieldType $meta): void
    {
        throw new \Exception('Deprecated method');
    }

    /**
     * Assign data in dataValues based on the elastic index content.
     *
     * @param array<array> $elasticIndexDatas
     *
     * @deprecated
     *
     * @throws \Exception
     */
    public function updateDataValue(array &$elasticIndexDatas, bool $isMigration = false): void
    {
        throw new \Exception('Deprecated method');
    }

    /**
     * @param ArrayCollection<mixed, mixed>|PersistentCollection<mixed, mixed> $fieldTypes
     */
    public function linkFieldType($fieldTypes): void
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

    /**
     * get a child.
     *
     * @return DataField|null
     */
    public function __get(string $key)
    {
        if (0 !== \strpos($key, 'ems_')) {
            throw new \Exception('unprotected ems get with key '.$key);
        } else {
            $key = \substr($key, 4);
        }

        /** @var FieldType $fieldType */
        $fieldType = $this->getFieldType();

        if ($this->getFieldType() && 0 == \strcmp($fieldType->getType(), CollectionFieldType::class)) {
            //Symfony wants iterate on children
            return $this;
        } else {
            /** @var DataField $dataField */
            foreach ($this->children as $dataField) {
                /** @var FieldType $dataFieldFieldType */
                $dataFieldFieldType = $dataField->getFieldType();
                if (null != $dataField->getFieldType() && !$dataFieldFieldType->getDeleted() && 0 == \strcmp($key, $dataFieldFieldType->getName())) {
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
     * Get textValue.
     *
     * @return string|null
     */
    public function getTextValue()
    {
        if (\is_array($this->rawData) && 0 === \count($this->rawData)) {
            return null; //empty array means null/empty
        }

        if (null !== $this->rawData && !\is_string($this->rawData)) {
            if (\is_array($this->rawData) && 1 == \count($this->rawData) && \is_string($this->rawData[0])) {
                $this->addMessage('String expected, single string in array instead');

                return $this->rawData[0];
            }
            $this->addMessage('String expected from the DB: '.\print_r($this->rawData, true));
        }

        return $this->rawData;
    }

    /**
     * Set passwordValue.
     *
     * @param string|null $passwordValue
     *
     * @return DataField
     */
    public function setPasswordValue($passwordValue)
    {
        if (null !== $passwordValue) {
            $this->setTextValue($passwordValue);
        }

        return $this;
    }

    /**
     * Get passwordValue.
     *
     * @return string|null
     */
    public function getPasswordValue()
    {
        return $this->getTextValue();
    }

    /**
     * Set resetPasswordValue.
     *
     * @param string $resetPasswordValue
     *
     * @return DataField
     */
    public function setResetPasswordValue($resetPasswordValue)
    {
        if (null !== $resetPasswordValue && $resetPasswordValue) {
            $this->setTextValue(null);
        }

        return $this;
    }

    /**
     * Get resetPasswordValue.
     *
     * @return bool
     */
    public function getResetPasswordValue()
    {
        return false;
    }

    public function setFloatValue(?float $rawData): DataField
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Get floatValue.
     *
     * @return float|null
     */
    public function getFloatValue()
    {
        if (\is_array($this->rawData) && 0 === \count($this->rawData)) {
            return null; //empty array means null/empty
        }

        if (null !== $this->rawData && !\is_finite($this->rawData)) {
            throw new DataFormatException('Float or double expected: '.\print_r($this->rawData, true));
        }

        return $this->rawData;
    }

    /**
     * Set dataValue, the set of field is delegated to the corresponding fieldType class.
     *
     * @param \DateTime $inputString
     *
     * @return DataField
     *
     * @throws \Exception
     */
    public function setDataValue($inputString)
    {
        /** @var FieldType $fieldType */
        $fieldType = $this->getFieldType();
        $fieldType->setDataValue($inputString, $this);

        return $this;
    }

    /**
     * @return array<string>
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
     * @param array<string>|null $rawData
     *
     * @return $this
     *
     * @throws DataFormatException
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
     * Get arrayValue.
     *
     * @return array<array>|null
     */
    public function getArrayTextValue()
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
     * Get integerValue.
     *
     * @return int
     */
    public function getIntegerValue()
    {
        if (\is_array($this->rawData)) {
            $this->addMessage('Integer expected array found: '.\print_r($this->rawData, true));

            return \count($this->rawData); //empty array means null/empty
        }

        if (null === $this->rawData || \is_int($this->rawData)) {
            return \intval($this->rawData);
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
     *
     * @return DataField
     */
    public function setIntegerValue($rawData)
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

    /**
     * Get booleanValue.
     *
     * @return bool|null
     */
    public function getBooleanValue()
    {
        if (\is_array($this->rawData) && 0 === \count($this->rawData)) {
            return null; //empty array means null/empty
        }

        if (null !== $this->rawData && !\is_bool($this->rawData)) {
            throw new DataFormatException('Boolean expected: '.\print_r($this->rawData, true));
        }

        return $this->rawData;
    }

    /**
     * @return array<DateTime|false>
     *
     * @throws DataFormatException
     */
    public function getDateValues(): array
    {
        $out = [];
        if (null !== $this->rawData) {
            if (!\is_array($this->rawData)) {
                throw new DataFormatException('Array expected: '.\print_r($this->rawData, true));
            }
            foreach ($this->rawData as $item) {
                /* @var \DateTime $converted */
                $out[] = \DateTime::createFromFormat(\DateTime::ISO8601, $item);
            }
        }

        return $out;
    }

    public function setBooleanValue(?bool $rawData): DataField
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * @return DataField
     */
    public function getRootDataField()
    {
        $out = $this;

        if ($out instanceof DataField) {
            while ($out->getParent()) {
                /** @var DataField $out */
                $out = $out->getParent();
            }
        }

        return $out;
    }

    public function setMarked(bool $marked): DataField
    {
        $this->marked = $marked;

        return $this;
    }

    public function isMarked(): bool
    {
        return $this->marked;
    }

    /****************************
     * Generated methods
     ****************************
     */

    /**
     * JSON decode the input string and save it has array in rawdata.
     *
     * @param string $text
     *
     * @return DataField
     */
    public function setEncodedText($text)
    {
        $this->rawData = \json_decode($text, true);

        return $this;
    }

    /**
     * Get the rawdata in a text encoded form.
     *
     * @return string
     */
    public function getEncodedText()
    {
        return \strval(\json_encode($this->rawData));
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return DataField
     */
    public function setOrderKey($orderKey)
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
        return $this->orderKey;
    }

    /**
     * Set fieldType.
     *
     * @param \EMS\CoreBundle\Entity\FieldType $fieldType
     *
     * @return DataField
     */
    public function setFieldType(FieldType $fieldType = null)
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    /**
     * Get fieldType.
     *
     * @return \EMS\CoreBundle\Entity\FieldType|null
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * Set parent.
     *
     * @param DataField $parent
     *
     * @return DataField
     */
    public function setParent(DataField $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return DataField|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add child.
     *
     * @return DataField
     */
    public function addChild(DataField $child)
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * Remove child.
     */
    public function removeChild(DataField $child): void
    {
        $this->children->removeElement($child);
    }

    /**
     * Get children.
     *
     * @return Collection<int, mixed>
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set rawData.
     *
     * @param mixed $rawData
     *
     * @return DataField
     */
    public function setRawData($rawData)
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Get rawData.
     *
     * @return mixed
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * @param mixed $inputValue
     *
     * @return DataField
     */
    public function setInputValue($inputValue)
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
}
