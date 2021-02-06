<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\User;
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

final class SelectUserPropertyType extends AbstractType
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'select2';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $eventDispatcher = $options['event_dispatcher'];

        if ($eventDispatcher instanceof EventDispatcher || false !== \boolval($options['allow_add'])) {
            $this->allowDynamicChoices($builder, $eventDispatcher);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired(['allow_add', 'user_property'])
            ->setDefaults([
                'is_dynamic' => false,
                'user_roles' => [],
                'event_dispatcher' => null,
                'multiple' => false,
            ])
            ->setNormalizer('choices', function (Options $options, $value) {
                if (true === $options['is_dynamic']) {
                    return $value; //choices overwitten by allowDynamicChoices method
                }

                return $this->getChoices($options['user_property'], $options['user_roles']);
            })
            ->setNormalizer('attr', function (Options $options, $value) {
                $allowAdd = \boolval($options['allow_add']) ? true : false;

                if ($allowAdd) {
                    $value['data-tags'] = true; //select2 allow add tags
                }

                return $value;
            });
    }

    /**
     * @param string[] $roles
     *
     * @return array<string, string>
     */
    private function getChoices(string $property, array $roles): array
    {
        $accessor = new PropertyAccessor();
        $users = $this->userService->findUsersWithRoles($roles);

        $values = \array_map(function (User $user) use ($accessor, $property) {
            $readable = $accessor->isReadable($user, $property);

            return $readable ? $accessor->getValue($user, $property) : $user->getDisplayName();
        }, $users);

        \natcasesort($values);

        return \array_combine($values, $values);
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
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
