<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Core\User\UserOptions;
use EMS\CoreBundle\Exception\FormNotFoundException;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\DataTransformer\FormModelTransformer;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserOptionsType extends AbstractType
{
    final public const string CONTEXT_PROFILE = 'user_profile';
    final public const string CONTEXT_USER_MANAGEMENT = 'user_management';

    public function __construct(
        private readonly FormManager $formManager,
        protected FormRegistryInterface $formRegistry,
        protected DataService $dataService,
        protected LoggerInterface $logger,
        private readonly ?string $customUserOptionsForm)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $context = $options['context'];

        if (self::CONTEXT_USER_MANAGEMENT === $context) {
            $builder
                ->add(UserOptions::ALLOWED_CONFIGURE_WYSIWYG, CheckboxType::class, [
                    'required' => false,
                    'label' => 'user.option.allowed_configure_wysiwyg',
                ])
                ->add(UserOptions::SIMPLIFIED_UI, CheckboxType::class, [
                    'required' => false,
                    'label' => 'user.option.simplified_ui',
                ]);
        }

        if (null !== $this->customUserOptionsForm && '' !== $this->customUserOptionsForm) {
            try {
                $form = $this->formManager->getByName($this->customUserOptionsForm);
                $builder->add(UserOptions::CUSTOM_OPTIONS, $form->getFieldType()->getType(), [
                    'metadata' => $form->getFieldType(),
                    'label' => false,
                    'constraints' => [
                        new Callback($this->validate(...)),
                    ],
                ]);

                $builder->get(UserOptions::CUSTOM_OPTIONS)
                    ->addViewTransformer(new DataFieldViewTransformer($form->getFieldType(), $this->formRegistry))
                    ->addModelTransformer(new FormModelTransformer($form->getFieldType(), $this->formRegistry));
            } catch (FormNotFoundException) {
                $this->logger->warning('log.user.custom_form_not_found', [
                    'name' => $this->customUserOptionsForm,
                ]);
            }
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['translation_domain' => 'emsco-user'])
            ->setRequired(['context'])
            ->setAllowedValues('context', [self::CONTEXT_PROFILE, self::CONTEXT_USER_MANAGEMENT]);
    }

    /**
     * @param mixed[] $data
     */
    public function validate(array $data, ExecutionContextInterface $context): void
    {
        $object = $context->getObject();
        if (!$object instanceof FormInterface) {
            throw new \RuntimeException('Unexpected non FormInterface object');
        }

        $this->dataService->isValid($object, null, $data);
    }
}
