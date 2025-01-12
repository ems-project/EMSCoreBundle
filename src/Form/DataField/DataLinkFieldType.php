<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Event\UpdateRevisionReferersEvent;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\QuerySearchPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class DataLinkFieldType extends DataFieldType
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        protected EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public function postFinalizeTreatment(string $type, string $id, DataField $dataField, mixed $previousData): mixed
    {
        $name = $dataField->giveFieldType()->getName();

        if (!empty($dataField->giveFieldType()->getExtraOptions()['updateReferersField'])) {
            $rawData = $dataField->getRawData();

            $referersToRemove = $previousData[$name] ?? [];
            $referersToAdd = \is_array($rawData) ? $rawData[$name] : $rawData;

            $referersToRemove = !\is_array($referersToRemove) ? [$referersToRemove] : $referersToRemove;
            $referersToAdd = !\is_array($referersToAdd) ? [$referersToAdd] : $referersToAdd;

            $event = new UpdateRevisionReferersEvent($type, $id, $dataField->giveFieldType()->getExtraOptions()['updateReferersField'], $referersToRemove, $referersToAdd);
            $this->dispatcher->dispatch($event);
        }

        return parent::postFinalizeTreatment($type, $id, $dataField, $previousData);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Link to data object(s)';
    }

    #[\Override]
    public function getElasticsearchQuery(DataField $dataField, array $options = []): array
    {
        $opt = [...[
            'nested' => '',
        ], ...$options];
        if (\strlen((string) $opt['nested'])) {
            $opt['nested'] .= '.';
        }

        $data = $dataField->getRawData();
        $out = [];
        if (\is_array($data)) {
            $out = [
                'terms' => [
                    $opt['nested'].$dataField->giveFieldType()->getName() => $data,
                ],
            ];
        } else {
            $out = [
                'term' => [
                    $opt['nested'].$dataField->giveFieldType()->getName() => $data,
                ],
            ];
        }

        return $out;
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-sitemap';
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $options = $data->giveFieldType()->getDisplayOptions();
            if (isset($options['multiple']) && $options['multiple']) {
                $out[$data->giveFieldType()->getName()] = $data->getArrayTextValue();
            } else {
                parent::buildObjectArray($data, $out);
            }
        }
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];

        // Add an event listener in order to sort existing normData before the merge in MergeCollectionListener
        $listener = function (FormEvent $event) {
            $data = $event->getForm()->getNormData();
            $rawData = $data->getRawData();

            if (!empty($rawData) && \is_array($rawData)) {
                \usort($rawData, function ($a, $b) use ($event) {
                    if (!empty($event->getData()['value'])) {
                        $indexA = \array_search($a, $event->getData()['value']);
                        $indexB = \array_search($b, $event->getData()['value']);
                        if (false === $indexA || $indexA > $indexB) {
                            return 1;
                        }
                        if (false === $indexB || $indexA < $indexB) {
                            return -1;
                        }
                    }

                    return 0;
                });
                $event->getForm()->setData($rawData);
            }
        };

        $builder->add('value', ObjectPickerType::class, [
            'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'multiple' => $options['multiple'],
            'type' => $options['type'],
            'querySearch' => $options['querySearch'],
            'searchId' => $options['searchId'],
            'referrer-ems-id' => $options['referrer-ems-id'] ?? null,
            'dynamicLoading' => $options['dynamicLoading'],
            'sortable' => $options['sortable'],
            'with_warning' => $options['with_warning'],
            'circle-only' => ($fieldType->getContentType() && $fieldType->giveContentType()->getCirclesField() === $fieldType->getName()),
        ]);

        if ($options['sortable']) {
            $builder->addEventListener(FormEvents::PRE_SUBMIT, $listener);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('multiple', false);
        $resolver->setDefault('type', null);
        $resolver->setDefault('searchId', null);
        $resolver->setDefault('environment', null);
        $resolver->setDefault('defaultValue', null);
        $resolver->setDefault('required', false);
        $resolver->setDefault('sortable', false);
        $resolver->setDefault('dynamicLoading', true);
        $resolver->setDefault('querySearch', null);
    }

    #[\Override]
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['displayOptions']['dynamicLoading'] = true;
        $out['mappingOptions']['analyzer'] = 'keyword';
        $out['displayOptions']['querySearch'] = null;

        return $out;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    #[\Override]
    public function getChoiceList(FieldType $fieldType, array $choices): array
    {
        /** @var ObjectPickerType $objectPickerType */
        $objectPickerType = $this->formRegistry->getType(ObjectPickerType::class)->getInnerType();

        $loader = $objectPickerType->getChoiceListFactory()->createLoader($fieldType->getDisplayOptions()['type'], true /* count($choices) == 0 || !$fieldType->getDisplayOptions()['dynamicLoading'] */);
        $all = $loader->loadAll();
        if (\count($choices) > 0) {
            foreach ($all as $key => $data) {
                if (!\in_array($key, $choices)) {
                    unset($all[$key]);
                }
            }
            //             return $loader->loadChoiceList()->loadChoices($choices);
        }

        return $all;
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('multiple', CheckboxType::class, [
            'required' => false,
        ])->add('dynamicLoading', CheckboxType::class, [
            'required' => false,
        ])->add('sortable', CheckboxType::class, [
            'required' => false,
        ])->add('querySearch', QuerySearchPickerType::class, [
            'required' => false,
        ])->add('type', TextType::class, [
            'required' => false,
        ])->add('searchId', TextType::class, [
            'required' => false,
        ])->add('defaultValue', TextType::class, [
            'required' => false,
        ]);

        $optionsForm->get('extraOptions')->add('updateReferersField', TextType::class, [
            'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, ['required' => false]);
        }
    }

    #[\Override]
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $out = parent::modelTransform($data, $fieldType);
        if ($fieldType->getDisplayOption('multiple', false)) {
            $temp = [];
            if (null === $data) {
                $out->setRawData([]);
            } elseif (\is_array($data)) {
                foreach ($data as $item) {
                    if (\is_string($item)) {
                        $temp[] = $item;
                    } else {
                        $out->addMessage('Some data was not able to be imported: '.Json::encode($item));
                    }
                }
            } elseif (\is_string($data)) {
                $temp[] = $data;
                $out->addMessage('Data converted into array');
            } else {
                $out->addMessage('Data was not able to be imported: '.Json::encode($data));
            }
            $out->setRawData($temp);
        } else {
            if (\is_string($data)) {
                return $out;
            }
            if (empty($data)) {
                $out->setRawData(null);

                return $out;
            }

            if (\is_array($data)) {
                if (\is_string($data[0])) {
                    $out->setRawData($data[0]);
                    if (\count($data) > 1) {
                        $out->addMessage('Data converted into string, some data might have been lost');
                    }
                }
            } else {
                $out->setRawData(null);
                $out->addMessage('Data was not able to be imported: '.Json::encode($data));
            }
        }

        return $out;
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => $out];
    }

    /**
     * @param ?array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $data = (null !== $data && isset($data['value'])) ? $data['value'] : null;
        if (\is_array($data)) {
            $data = \array_values($data);
        }

        $out = parent::reverseViewTransform($data, $fieldType);

        return $out;
    }
}
