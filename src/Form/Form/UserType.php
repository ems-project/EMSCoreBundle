<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\UserService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class UserType extends AbstractType
{
    public const string MODE_CREATE = 'create';
    public const string MODE_UPDATE = 'update';

    public function __construct(private readonly UserService $userService, private readonly ?string $circleObject)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mode = $options['mode'];

        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email',
            ])
            ->add('username', null, [
                'label' => 'form.username',
                'disabled' => (self::MODE_CREATE !== $mode),
            ]);

        if (self::MODE_CREATE === $mode) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => ['autocomplete' => 'new-password'],
                    'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
                ],
                'first_options' => ['label' => 'user.password'],
                'second_options' => ['label' => 'user.password_confirmation'],
                'invalid_message' => 'user.password.mismatch',
            ]);
        }

        $builder
            ->add('expirationDate', DateTimeType::class, [
                'required' => false,
                'date_widget' => 'single_text',
                'input' => 'datetime',
                'attr' => [
                    'class' => 'datetime-picker',
                    'data-date-format' => 'D/MM/YYYY HH:mm:ss',
                ],
            ])
            ->add('emailNotification', CheckboxType::class, [
                'required' => false,
            ])
            ->add('displayName', null, [
                'required' => true,
                'label' => 'Display name',
            ])

            ->add('enabled', CheckboxType::class, [
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
            ])
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => 'WYSIWYG profile',
                'class' => WysiwygProfile::class,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC'),
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'wysiwyg-profile-picker',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => $this->userService->getExistingRoles(),
                'label' => 'Roles',
                'expanded' => true,
                'multiple' => true,
                'mapped' => true,
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'user.locale',
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
                'required' => true,
                'choices' => [Locales::getName('en') => 'en'],
                'choice_translation_domain' => false,
            ])
            ->add('localePreferred', ChoiceType::class, [
                'label' => 'user.locale_preferred',
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
                'required' => false,
                'choices' => \array_flip(Locales::getNames()),
                'choice_translation_domain' => false,
            ])
            ->add('userOptions', UserOptionsType::class, [
                'label' => 'user.option.title',
                'context' => UserOptionsType::CONTEXT_USER_MANAGEMENT,
            ])
        ;

        if ($this->circleObject) {
            $builder->add('circles', ObjectPickerType::class, [
                'multiple' => true,
                'type' => $this->circleObject,
                'dynamicLoading' => true,
            ]);
        }

        if (self::MODE_CREATE === $mode) {
            $builder->add('create', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary btn-sm'],
                'icon' => 'fa fa-plus',
            ]);
        }
        if (self::MODE_UPDATE === $mode) {
            $builder->add('update', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->setRequired(['mode'])
            ->setAllowedValues('mode', [self::MODE_CREATE, self::MODE_UPDATE])
        ;
    }
}
