<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\UserService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @extends AbstractType<mixed>
 */
final class SelectUserPropertyType extends AbstractType
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[\Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'select2';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $eventDispatcher = $options['event_dispatcher'];

        if ($eventDispatcher instanceof EventDispatcher || false !== (bool) $options['allow_add']) {
            $this->allowDynamicChoices($builder, $eventDispatcher);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired(['allow_add', 'user_property'])
            ->setDefaults([
                'is_dynamic' => false,
                'user_roles' => [],
                'exclude_values' => [],
                'event_dispatcher' => null,
                'multiple' => false,
                'label_property' => null,
                'choice_translation_domain' => false,
            ])
            ->setNormalizer('choices', function (Options $options, $value) {
                if (true === $options['is_dynamic']) {
                    return $value; // choices overwitten by allowDynamicChoices method
                }

                $labelProperty = $options['label_property'] ?? $options['user_property'];

                return $this->getChoices(
                    $options['user_property'],
                    $labelProperty,
                    $options['user_roles'],
                    $options['exclude_values']
                );
            })
            ->setNormalizer('attr', function (Options $options, $value) {
                $allowAdd = (bool) ($options['allow_add']) ? true : false;

                if ($allowAdd) {
                    $value['data-tags'] = true; // select2 allow add tags
                }

                return $value;
            });
    }

    /**
     * @param string[] $roles
     * @param string[] $excludeValues
     *
     * @return array<int|string, int|string>
     */
    private function getChoices(string $property, string $propertyLabel, array $roles, array $excludeValues): array
    {
        $accessor = new PropertyAccessor();
        $users = $this->userService->findUsersWithRoles($roles);

        $choices = [];

        foreach ($users as $user) {
            $readableValue = $accessor->isReadable($user, $property);
            $readableLabel = $accessor->isReadable($user, $propertyLabel);

            $value = $readableValue ? $accessor->getValue($user, $property) : $user->getUsername();
            $label = $readableLabel ? $accessor->getValue($user, $propertyLabel) : $user->getUsername();

            if (!\in_array($value, $excludeValues)) {
                $choices[$value] = $label;
            }
        }

        \natcasesort($choices);

        return \array_flip($choices);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function allowDynamicChoices(FormBuilderInterface $builder, EventDispatcher $eventDispatcher): void
    {
        $elementName = $builder->getName();
        $formModifier = function (FormInterface $form, array $data) use ($elementName) {
            $currentOptions = $form->get($elementName)->getConfig()->getOptions();
            $choices = $currentOptions['choices'];

            foreach ($data as $username) {
                if (!isset($choices[$username])) {
                    $choices[$username] = $username;
                }
            }

            $currentOptions['is_dynamic'] = true;
            $currentOptions['choices'] = $choices;
            $form->add($elementName, SelectUserPropertyType::class, $currentOptions);
        };

        $eventDispatcher->addListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData() ?? [];
            $arrayData = \is_string($data) ? [$data] : $data;
            $formModifier($event->getForm(), $arrayData);
        });
        $eventDispatcher->addListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData()['value'] ?? [];
            $arrayData = \is_string($data) ? [$data] : $data;
            $formModifier($event->getForm(), $arrayData);
        });
    }
}
