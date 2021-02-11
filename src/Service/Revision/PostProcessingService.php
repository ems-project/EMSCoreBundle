<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Twig\Environment;

final class PostProcessingService
{
    private Environment $twig;
    private LoggerInterface $logger;

    public function __construct(Environment $twig, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->logger = $logger;
    }

    public function propagateDataToComputedFieldRecursive(FormInterface $form, array &$objectArray, ContentType $contentType, string $type, ?string $ouuid, bool $migration, bool $finalize, ?array &$parent, string $path)
    {
        $found = false;
        /** @var DataField $dataField */
        $dataField = $form->getNormData();

        if (!$dataField instanceof DataField) {
            return true;
        }

        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();

        $options = $dataField->getFieldType()->getOptions();

        if (!$dataFieldType::isVirtual(!$options ? [] : $options)) {
            $path .= ('' == $path ? '' : '.').$form->getConfig()->getName();
        }

        $extraOption = $dataField->getFieldType()->getExtraOptions();
        if (isset($extraOption['postProcessing']) && !empty($extraOption['postProcessing'])) {
            try {
                $out = $this->twig->createTemplate($extraOption['postProcessing'])->render([
                    '_source' => $objectArray,
                    '_type' => $type,
                    '_id' => $ouuid,
                    'index' => $contentType->getEnvironment()->getAlias(),
                    'migration' => $migration,
                    'parent' => $parent,
                    'path' => $path,
                    'finalize' => $finalize,
                ]);
                $out = \trim($out);

                if (\strlen($out) > 0) {
                    $json = \json_decode($out, true);
                    $meg = \json_last_error_msg();
                    if (0 == \strcasecmp($meg, 'No error')) {
                        $objectArray[$dataField->getFieldType()->getName()] = $json;
                        $found = true;
                    } else {
                        $this->logger->warning('service.data.json_parse_post_processing_error', [
                            'field_name' => $dataField->getFieldType()->getName(),
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $out,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                    if (!$migration) {
                        $form->addError(new FormError($e->getPrevious()->getMessage()));
                        $this->logger->warning('service.data.cant_finalize_field', [
                            'field_name' => $dataField->getFieldType()->getName(),
                            'field_display' => isset($dataField->getFieldType()->getDisplayOptions()['label']) && !empty($dataField->getFieldType()->getDisplayOptions()['label']) ? $dataField->getFieldType()->getDisplayOptions()['label'] : $dataField->getFieldType()->getName(),
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getPrevious()->getMessage(),
                        ]);
                    }
                } else {
                    $this->logger->warning('service.data.json_parse_post_processing_error', [
                        'field_name' => $dataField->getFieldType()->getName(),
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                    ]);
                }
            }
        }
        if ($form->getConfig()->getType()->getInnerType() instanceof ComputedFieldType) {
            $template = $dataField->getFieldType()->getDisplayOptions()['valueTemplate'] ?? '';

            $out = null;
            if (!empty($template)) {
                try {
                    $out = $this->twig->createTemplate($template)->render([
                        '_source' => $objectArray,
                        '_type' => $type,
                        '_id' => $ouuid,
                        'index' => $contentType->getEnvironment()->getAlias(),
                        'migration' => $migration,
                        'parent' => $parent,
                        'path' => $path,
                        'finalize' => $finalize,
                    ]);

                    if ($dataField->getFieldType()->getDisplayOptions()['json']) {
                        $out = \json_decode($out, true);
                    } else {
                        $out = \trim($out);
                    }
                } catch (\Exception $e) {
                    if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                        $form->addError(new FormError($e->getPrevious()->getMessage()));
                    }

                    $this->logger->warning('service.data.template_parse_error', [
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                        'computed_field_name' => $dataField->getFieldType()->getName(),
                    ]);
                }
            }
            if (null !== $out && false !== $out && (!\is_array($out) || !empty($out))) {
                $objectArray[$dataField->getFieldType()->getName()] = $out;
            } elseif (isset($objectArray[$dataField->getFieldType()->getName()])) {
                unset($objectArray[$dataField->getFieldType()->getName()]);
            }
            $found = true;
        }

        if ($dataFieldType->isContainer() && $form instanceof \IteratorAggregate) {
            /** @var FormInterface $child */
            foreach ($form->getIterator() as $child) {
                /** @var DataFieldType $childType */
                $childType = $child->getConfig()->getType()->getInnerType();

                if ($childType instanceof CollectionFieldType) {
                    $fieldName = $child->getNormData()->getFieldType()->getName();

                    foreach ($child->all() as $collectionChild) {
                        if (isset($objectArray[$fieldName])) {
                            foreach ($objectArray[$fieldName] as &$elementsArray) {
                                $found = $this->propagateDataToComputedFieldRecursive($collectionChild, $elementsArray, $contentType, $type, $ouuid, $migration, $finalize, $parent, $path.('' == $path ? '' : '.').$fieldName) || $found;
                            }
                        }
                    }
                } elseif ($childType instanceof DataFieldType) {
                    $found = $this->propagateDataToComputedFieldRecursive($child, $objectArray, $contentType, $type, $ouuid, $migration, $finalize, $parent, $path) || $found;
                }
            }
        }

        return $found;
    }
}
