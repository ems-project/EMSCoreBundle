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
    private UserService $userService;

    public function __construct(
        UserService $userService,
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->userService = $userService;
    }

    public function getLabel(): string
    {
        return 'Select User property field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-users';
    }

    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
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
        ]);
    }

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

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
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
        $optionsForm->get('mappingOptions')
            ->add('analyzer', AnalyzerPickerType::class)
            ->add('copy_to', TextType::class, [
                'required' => false,
            ])
        ;
    }

    /**
     * @param DataField<DataField> $dataField
     *
     * @return array<mixed>
     */
    public function viewTransform(DataField $dataField)
    {
        $test = parent::viewTransform($dataField);

        return ['value' => $test];
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    public function reverseViewTransform($data, FieldType $fieldType)
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
