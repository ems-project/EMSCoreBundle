<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use EMS\CoreBundle\Form\DataField\MultiplexedTabContainerFieldType;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Twig\Environment;

final class PostProcessingService
{
    private Environment $twig;
    private FormFactoryInterface $formFactory;
    private LoggerInterface $logger;

    public function __construct(Environment $twig, FormFactoryInterface $formFactory, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->logger = $logger;
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<mixed>                 $object
     */
    public function jsonMenuNested(FormInterface $form, ContentType $contentType, array &$object): bool
    {
        return $this->postProcessing($form, $contentType, $object);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<mixed>                 $objectArray
     * @param array<mixed>                 $context
     * @param array<mixed>                 $parent
     */
    public function postProcessing(FormInterface $form, ContentType $contentType, array &$objectArray, array $context = [], ?array &$parent = [], string $path = ''): bool
    {
        $migration = isset($context['migration']) && $context['migration'];
        $context = \array_merge($context, [
            '_source' => &$objectArray, // if update also update the context
            '_type' => $contentType->getName(),
            'index' => $contentType->giveEnvironment()->getAlias(),
            'alias' => $contentType->giveEnvironment()->getAlias(),
            'parent' => $parent,
            'path' => $path,
            'form' => $form,
        ]);

        $found = false;
        /** @var DataField $dataField */
        $dataField = $form->getNormData();

        if (!$dataField instanceof DataField) {
            return true;
        }

        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();
        if (null === $fieldType = $dataField->getFieldType()) {
            throw new \RuntimeException('Field type not found!');
        }
        $options = $fieldType->getOptions();

        if (!$dataFieldType::isVirtual(!$options ? [] : $options)) {
            $path .= ('' == $path ? '' : '.').$form->getConfig()->getName();
        }

        if ($migration && JsonMenuNestedEditorFieldType::class === $fieldType->getType()) {
            $this->jsonMenuNestedEditor($fieldType, $contentType, $objectArray, $context);
        }

        $extraOption = $fieldType->getExtraOptions();
        if (isset($extraOption['postProcessing']) && !empty($extraOption['postProcessing'])) {
            try {
                $out = $this->twig->createTemplate($extraOption['postProcessing'])->render($context);
                $out = \trim($out);

                if (\strlen($out) > 0) {
                    $json = \json_decode($out, true);
                    $meg = \json_last_error_msg();
                    if (0 == \strcasecmp($meg, 'No error')) {
                        if (null === $fieldType->getParent()) {
                            $objectArray = $json;
                        } else {
                            $objectArray[$fieldType->getName()] = $json;
                        }
                        $found = true;
                    } else {
                        $this->logger->warning('service.data.json_parse_post_processing_error', [
                            'field_name' => $dataField->giveFieldType()->getName(),
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $out,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                    if (!$migration) {
                        $form->addError(new FormError($e->getPrevious()->getMessage()));
                        $this->logger->warning('service.data.cant_finalize_field', [
                            'field_name' => $dataField->giveFieldType()->getName(),
                            'field_display' => isset($fieldType->getDisplayOptions()['label']) && !empty($fieldType->getDisplayOptions()['label']) ? $fieldType->getDisplayOptions()['label'] : $fieldType->getName(),
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getPrevious()->getMessage(),
                        ]);
                    }
                } else {
                    $this->logger->warning('service.data.json_parse_post_processing_error', [
                        'field_name' => $fieldType->getName(),
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                    ]);
                }
            }
        }
        if ($form->getConfig()->getType()->getInnerType() instanceof ComputedFieldType) {
            $template = $fieldType->getDisplayOptions()['valueTemplate'] ?? '';

            $out = null;
            if (!empty($template)) {
                try {
                    $out = $this->twig->createTemplate($template)->render($context);

                    if ($fieldType->getDisplayOptions()['json']) {
                        $out = \json_decode($out, true, 512, JSON_THROW_ON_ERROR);
                    } else {
                        $out = \trim($out);
                    }
                } catch (\Throwable $e) {
                    if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                        $form->addError(new FormError($e->getPrevious()->getMessage()));
                    }

                    $this->logger->warning('service.data.template_parse_error', [
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                        'computed_field_name' => $fieldType->getName(),
                    ]);
                }
            }
            if (null !== $out && false !== $out && (!\is_array($out) || !empty($out))) {
                $objectArray[$fieldType->getName()] = $out;
            } elseif (isset($objectArray[$fieldType->getName()])) {
                unset($objectArray[$fieldType->getName()]);
            }
            $found = true;
        }

        if ($dataFieldType->isContainer() && $form instanceof \IteratorAggregate) {
            foreach ($form->getIterator() as $child) {
                /** @var DataFieldType $childType */
                $childType = $child->getConfig()->getType()->getInnerType();

                if ($childType instanceof CollectionFieldType) {
                    $fieldName = $child->getNormData()->getFieldType()->getName();
                    foreach ($child->all() as $collectionChild) {
                        if (isset($objectArray[$fieldName])) {
                            foreach ($objectArray[$fieldName] as &$elementsArray) {
                                $childPath = $path.('' == $path ? '' : '.').$fieldName;
                                $found = $this->postProcessing($collectionChild, $contentType, $elementsArray, $context, $parent, $childPath) || $found;
                            }
                        }
                    }
                } elseif ($childType instanceof MultiplexedTabContainerFieldType) {
                    foreach ($child as $multiplexedForm) {
                        if (!$multiplexedForm instanceof FormInterface) {
                            throw new \RuntimeException('Unexpected non FormInterface object');
                        }
                        if (!isset($objectArray[$multiplexedForm->getName()])) {
                            $objectArray[$multiplexedForm->getName()] = [];
                        }
                        $found = $this->postProcessing($multiplexedForm, $contentType, $objectArray[$multiplexedForm->getName()], $context, $parent, $path) || $found;
                    }
                } elseif ($childType instanceof DataFieldType) {
                    $found = $this->postProcessing($child, $contentType, $objectArray, $context, $parent, $path) || $found;
                }
            }
        }

        return $found;
    }

    /**
     * @param array<mixed> $objectArray
     * @param array<mixed> $context
     */
    private function jsonMenuNestedEditor(FieldType $fieldType, ContentType $contentType, array &$objectArray, array $context): void
    {
        if (null === $data = ($objectArray[$fieldType->getName()] ?? null)) {
            return;
        }

        $nestedTypes = [];
        foreach ($fieldType->getChildren() as $nestedContainer) {
            $nestedTypes[$nestedContainer->getName()] = $nestedContainer;
        }

        $jsonMenuNested = JsonMenuNested::fromStructure($data);

        foreach ($jsonMenuNested as $item) {
            if (null === $nestedType = ($nestedTypes[$item->getType()] ?? null)) {
                continue;
            }

            $itemObject = $item->getObject();
            $data = RawDataTransformer::transform($nestedType, $itemObject);

            $form = $this->formFactory->create(RevisionJsonMenuNestedType::class, ['data' => $data], [
                'field_type' => $nestedType,
                'content_type' => $contentType,
            ]);

            $itemObject = RawDataTransformer::reverseTransform($nestedType, $form->getData()['data']);

            $this->postProcessing($form->get('data'), $contentType, $itemObject, $context);
            $item->setObject($itemObject);
            if (isset($itemObject['label'])) {
                $item->setLabel($itemObject['label']);
            }
        }

        $objectArray[$fieldType->getName()] = \json_encode($jsonMenuNested->toArrayStructure(), JSON_THROW_ON_ERROR);
    }
}
