<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Helper;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\View;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalize and denormalize ContentType and FieldType objects in Json.
 *
 * @see http://symfony.com/doc/current/components/serializer.html
 * @see http://php-and-symfony.matthiasnoback.nl/2012/01/the-symfony2-serializer-component-create-a-normalizer-for-json-class-hinting/
 */
class JsonNormalizer implements NormalizerInterface, DenormalizerInterface
{
    //If you want to parse a new object, provide here the getXXXX method of the object to be skipped of normalization
    //[<ObjectName>] => [<XXXX>,...]
    //TODO: Anotate the object to allow the method normalize to be able get methods of the object to be skipped.
    private $toSkip = ['ContentType' => ['id',
                                         'indexAnalysisConfiguration', ],
                       'FieldType' => ['id',
                                          'contentType',
                                          'parent',
                                          'children',
                                          'displayOptions',
                                          'mappingOptions',
                                          'restrictionOptions',
                                          'migrationOptions',
                                          'extraOptions',
                                       'fieldRoles',
                                         ],
                       'Template' => ['id',
                                         'created',
                                         'modified',
                                         'environments',
                                        ],
                       'View' => ['id',
                                     'created',
                                     'modified',
                                    ],
    ];

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $data = [];

        $reflectionClass = new \ReflectionClass($object);

        $data['__jsonclass__'] = [
                \get_class($object),
                [], // constructor arguments
        ];
        //Parsing all methods of the object
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if ('get' !== \strtolower(\substr($reflectionMethod->getName(), 0, 3)) && 'is' !== \strtolower(\substr($reflectionMethod->getName(), 0, 2))) {
                continue;
            }

            if ($reflectionMethod->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $property = \lcfirst(('get' === \strtolower(\substr($reflectionMethod->getName(), 0, 3))) ? \substr($reflectionMethod->getName(), 3) : \substr($reflectionMethod->getName(), 2));
            $value = $reflectionMethod->invoke($object);
            if ('deleted' == $property && true == $value) {
                break;
            }
            if (null != $value) {
                //If you want to parse a new object, provide here the way to normalize it.
                if ($object instanceof ContentType) {
                    if (\in_array($property, $this->toSkip['ContentType'])) {
                        continue;
                    }
                    if ($value instanceof FieldType) {
                        $value = $this->normalize($value, $format, $context);
                    }
                    if ('views' == $property) {
                        $arrayValues = [];
                        foreach ($value as $index => $view) {
                            $arrayValues[$index] = $this->normalize($view, $format, $context); //Recursive
                        }
                        $value = $arrayValues;
                    }
                    if ('templates' == $property) {
                        $arrayValues = [];
                        foreach ($value as $index => $template) {
                            $arrayValues[$index] = $this->normalize($template, $format, $context); //Recursive
                        }
                        $value = $arrayValues;
                    }
                } elseif ($object instanceof FieldType) {
                    if (\in_array($property, $this->toSkip['FieldType'])) {
                        continue;
                    }
                    if ('validChildren' == $property) {
                        $arrayValues = [];
                        foreach ($value as $index => $subElement) {//subElement is always FieldType
                            if (!$subElement->getDeleted()) {
                                $arrayValues[$index] = $this->normalize($subElement, $format, $context); //Recursive
                            }
                        }
                        $value = $arrayValues;
                    }
                } elseif ($object instanceof Template) {
                    if (\in_array($property, $this->toSkip['Template'])) {
                        continue;
                    }
                } elseif ($object instanceof View) {
                    if (\in_array($property, $this->toSkip['View'])) {
                        continue;
                    }
                }
            }
            $data[$property] = $value;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    //TODO: Refactoring
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $class = $data['__jsonclass__'][0];
        $reflectionClass = new \ReflectionClass($class);

        $constructorArguments = $data['__jsonclass__'][1] ?: [];

        $object = $reflectionClass->newInstanceArgs($constructorArguments);

        unset($data['__jsonclass__']);
        $options = [];
        foreach ($data as $property => $value) {
            if ('fieldType' == $property) {
                $object->setFieldType($this->denormalize($value, $class, $format, $context));
            } elseif ('validChildren' == $property) {
                foreach ($value as $index => $subElement) {
                    if (!empty($subElement)) {
                        $object->addChild($this->denormalize($subElement, $class, $format, $context));
                    }
                }
            } elseif ('views' == $property) {
                foreach ($value as $index => $view) {
                    if (!empty($view)) {
                        $object->addView($this->denormalize($view, "EMS\CoreBundle\Entity\View", $format, ['contentType' => $object]));
                    }
                }
            } elseif ('templates' == $property) {
                foreach ($value as $index => $template) {
                    if (!empty($template)) {
                        $object->addTemplate($this->denormalize($template, "EMS\CoreBundle\Entity\Template", $format, ['contentType' => $object]));
                    }
                }
            } elseif ("EMS\CoreBundle\Entity\Template" == $class && 'contentType' == $property) {
                $object->setContentType($context['contentType']);
            } elseif ("EMS\CoreBundle\Entity\View" == $class && 'contentType' == $property) {
                $object->setContentType($context['contentType']);
            } else {
                $setter = 'set'.\ucfirst($property);
                if (\method_exists($object, $setter)) {
                    $object->$setter($value);
                }
            }
        }
        if (!empty($options)) {
            $object->setOptions($options);
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && 'json' === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return isset($data['__jsonclass__']) && 'json' === $format;
    }
}
