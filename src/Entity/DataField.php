<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use EMS\CoreBundle\Exception\DataFormatException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\OuuidFieldType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @implements \IteratorAggregate<DataField>
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

    /** @var Collection */
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
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->children->offsetSet($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset)
    {
        if ((\is_int($offset) || \ctype_digit($offset)) && !$this->children->offsetExists($offset) && null !== $this->fieldType && $this->fieldType->getChildren()->count() > 0) {
            $value = new DataField();
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
        return ('children' === $offset) ? $this->children : $this->children->offsetGet($offset);
    }

    public function getIterator()
    {
        return $this->children->getIterator();
    }

    /**
     * @Assert\Callback
     */
    public function isDataFieldValid(ExecutionContextInterface $context)
    {
        //TODO: why is it not working? See https://stackoverflow.com/a/25265360
        //Transformed: (but not used??)
        $context
            ->buildViolation('Haaaaha')
            ->atPath('textValue')
            ->addViolation();
    }

    public function propagateOuuid(string $ouuid)
    {
        if ($this->getFieldType() && 0 == \strcmp(OuuidFieldType::class, $this->getFieldType()->getType())) {
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

        return \json_encode($this->rawData);
    }

    public function orderChildren()
    {
        $children = null;

        if (null == $this->getFieldType()) {
            $children = $this->getParent()->getFieldType()->getChildren();
        } elseif (0 != \strcmp($this->getFieldType()->getType(), CollectionFieldType::class)) {
            $children = $this->getFieldType()->getChildren();
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

            foreach ($ancestor->getChildren() as $key => $child) {
                $this->addChild(new DataField($child, $this), $key);
            }
        }
    }

    public function __set($key, $input)
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
                    $this->updateDataStructure($this->getParent()->getFieldType());
                }
            }

            /** @var DataField $dataField */
            foreach ($this->children as &$dataField) {
                if (null != $dataField->getFieldType() && !$dataField->getFieldType()->getDeleted() && 0 == \strcmp($key, $dataField->getFieldType()->getName())) {
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
    public function updateDataStructure(FieldType $meta)
    {
        throw new \Exception('Deprecated method');
    }

    /**
     * Assign data in dataValues based on the elastic index content.
     *
     * @deprecated

     *
     * @throws \Exception
     */
    public function updateDataValue(array &$elasticIndexDatas, $isMigration = false)
    {
        throw new \Exception('Deprecated method');
    }

    /**
     * @param Collection<int, FieldType> $fieldTypes
     */
    public function linkFieldType(Collection $fieldTypes)
    {
        $index = 0;
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
    public function __get($key)
    {
        if (0 !== \strpos($key, 'ems_')) {
            throw new \Exception('unprotected ems get with key '.$key);
        } else {
            $key = \substr($key, 4);
        }

        if ($this->getFieldType() && 0 == \strcmp($this->getFieldType()->getType(), CollectionFieldType::class)) {
            //Symfony wants iterate on children
            return $this;
        } else {
            /** @var DataField $dataField */
            foreach ($this->children as $dataField) {
                if (null != $dataField->getFieldType() && !$dataField->getFieldType()->getDeleted() && 0 == \strcmp($key, $dataField->getFieldType()->getName())) {
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
     * @return string
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
        $this->getFieldType()->setDataValue($inputString, $this);

        return $this;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function addMessage($message)
    {
        if (!\in_array($message, $this->messages)) {
            $this->messages[] = $message;
        }
    }

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
     * @return array|null
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
            return $this->rawData;
        } elseif (\intval($this->rawData) || 0 === $this->rawData || '0' === $this->rawData) {
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

    public function getDateValues()
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
        while ($out->getParent()) {
            $out = $out->getParent();
        }

        return $out;
    }

    public function setMarked($marked)
    {
        $this->marked = $marked;

        return $this;
    }

    public function isMarked()
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
        return \json_encode($this->rawData);
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

    /**
     * Remove child.
     */
    public function removeChild(DataField $child)
    {
        $this->children->removeElement($child);
    }

    /**
     * Get children.
     *
     * @return Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set rawData.
     *
     * @param array|string|int|float|null $rawData
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
     * @return array|string|int|float|null
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * @param object $inputValue
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
     * @return array
     */
    public function getInputValue()
    {
        return $this->inputValue;
    }
}
