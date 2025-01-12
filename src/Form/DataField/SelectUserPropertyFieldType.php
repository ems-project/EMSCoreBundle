<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SelectUserPropertyFieldType extends DataFieldType
{
    public function __construct(
        private readonly UserService $userService,
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Select User property field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-users';
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $fieldType = $options['metadata'];

        if (!$fieldType instanceof FieldType) {
            return;
        }

        $builder->add('value', SelectUserPropertyType::class, [
            'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
            'multiple' => $options['multiple'],
            'allow_add' => $options['allow_add'],
            'user_property' => $options['user_property'],
            'user_roles' => $options['user_roles'],
            'event_dispatcher' => $builder->getEventDispatcher(),
            'required' => false,
            'empty_data' => $options['multiple'] ? [] : null,
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver
            ->setRequired(['user_property'])
            ->setDefaults([
                'allow_add' => true,
                'multiple' => false,
                'user_roles' => [],
            ])
        ;
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')
            ->add('multiple', CheckboxType::class, ['required' => false])
            ->add('allow_add', CheckboxType::class, [
                'label' => 'Allow add',
                'required' => false,
            ])
            ->add('user_property', ChoiceType::class, [
                'required' => true,
                'label' => 'User property',
                'choices' => $this->userService->listUserDisplayProperties(),
            ])
            ->add('user_roles', ChoiceType::class, [
                'required' => false,
                'label' => 'User roles',
                'multiple' => true,
                'choices' => $this->userService->listUserRoles(),
            ])
        ;

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, ['required' => false]);
        }
    }

    /**
     * @return array{'value': array<mixed>|string|int|float|bool|null}
     */
    #[\Override]
    public function viewTransform(DataField $dataField): array
    {
        return ['value' => parent::viewTransform($dataField)];
    }

    /**
     * @param ?array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $data = (null !== $data && isset($data['value'])) ? $data['value'] : null;

        if ($data instanceof User) {
            $data = $data->getUsername();
        }

        if (\is_array($data)) {
            $data = \array_map(fn ($value) => $value instanceof User ? $value->getUsername() : $value, \array_values($data));
        }

        return parent::reverseViewTransform($data, $fieldType);
    }
}
