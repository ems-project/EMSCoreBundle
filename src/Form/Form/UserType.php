<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\Group;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\RoleMultiPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
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

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class UserType extends AbstractType
{
    public const string MODE_CREATE = 'create';
    public const string MODE_UPDATE = 'update';

    public function __construct(
        private readonly ?string $circleObject,
        private readonly bool $groupFeature,
    ) {
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
                'label' => t('field.email', [], 'emsco-core'),
            ])
            ->add('username', null, [
                'label' => t('field.username', [], 'emsco-core'),
                'disabled' => (self::MODE_CREATE !== $mode),
            ]);

        if (self::MODE_CREATE === $mode) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'first_options' => ['label' => t('field.password', [], 'emsco-core')],
                'second_options' => ['label' => t('field.password_repeat', [], 'emsco-core')],
                'invalid_message' => t('user.password.mismatch', [], 'validators'),
            ]);
        }

        $builder
            ->add('expirationDate', DateTimeType::class, [
                'label' => t('field.date_expiration', [], 'emsco-core'),
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
                'label' => t('option.email_notification', [], 'emsco-core'),
            ])
            ->add('displayName', null, [
                'required' => true,
                'label' => t('field.display_name', [], 'emsco-core'),
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => t('field.enabled', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => t('field.wysiwyg_profile', [], 'emsco-core'),
                'class' => WysiwygProfile::class,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC'),
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'wysiwyg-profile-picker',
                ],
            ])
            ->add('userRoles', RoleMultiPickerType::class)
            ->add('locale', ChoiceType::class, [
                'label' => t('field.language_ui', [], 'emsco-core'),
                'required' => true,
                'choices' => [
                    Locales::getName('en') => 'en',
                    Locales::getName('fr') => 'fr',
                    Locales::getName('nl') => 'nl',
                ],
                'choice_translation_domain' => false,
            ])
            ->add('localePreferred', ChoiceType::class, [
                'label' => t('field.language_preferred', [], 'emsco-core'),
                'required' => false,
                'choices' => \array_flip(Locales::getNames()),
                'choice_translation_domain' => false,
            ])
            ->add('userOptions', UserOptionsType::class, [
                'label' => t('field.options', [], 'emsco-core'),
                'context' => UserOptionsType::CONTEXT_USER_MANAGEMENT,
            ])
        ;
        if ($this->groupFeature) {
            $builder->add('group', EntityType::class, [
                'required' => false,
                'label' => t('field.group', [], 'emsco-core'),
                'class' => Group::class,
                'choice_label' => 'label',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('g'),
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'user-group-picker',
                ],
            ]);
        }

        if ($this->circleObject) {
            $builder->add('circles', ObjectPickerType::class, [
                'label' => t('field.circles', [], 'emsco-core'),
                'multiple' => true,
                'type' => $this->circleObject,
                'dynamicLoading' => true,
            ]);
        }

        if (self::MODE_CREATE === $mode) {
            $builder->add('create', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary btn-sm', 'data-testid' => 'user-create'],
                'label' => t('action.create', [], 'emsco-core'),
                'icon' => 'fa fa-plus',
            ]);
        }
        if (self::MODE_UPDATE === $mode) {
            $builder->add('update', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                    'data-testid' => 'user-update',
                ],
                'label' => t('action.save', [], 'emsco-core'),
                'icon' => 'fa fa-save',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
            ])
            ->setRequired(['mode'])
            ->setAllowedValues('mode', [self::MODE_CREATE, self::MODE_UPDATE])
        ;
    }
}
