<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\User;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Core\User\UserOptions;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Form\Form\UserOptionsType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class UserProfileType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['data'] instanceof User) {
            $allowToChangeWysiwygProfile = $options['data']->getUserOptions()->isEnabled(UserOptions::ALLOWED_CONFIGURE_WYSIWYG);
        } else {
            $allowToChangeWysiwygProfile = false;
        }
        $builder
            ->add('displayName', null, [
                'label' => t('field.display_name', [], 'emsco-core'),
            ])
            ->add('email', EmailType::class, [
                'label' => t('field.email', [], 'emsco-core'),
            ])
            ->add('emailNotification', CheckboxType::class, [
                'label' => t('option.email_notification', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('current_password', PasswordType::class, [
                'label' => t('key.current_password', [], 'emsco-core'),
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                    new UserPassword(message: 'user.current_password_invalid'),
                ],
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('layoutBoxed', null, [
                'label' => t('option.layout_boxed', [], 'emsco-core'),
            ])
            ->add('sidebarMini', null, [
                'label' => t('option.layout_sidebar_mini', [], 'emsco-core'),
            ])
            ->add('sidebarCollapse', null, [
                'label' => t('option.layout_sidebar_collapse', [], 'emsco-core'),
            ])
            ->add('userOptions', UserOptionsType::class, [
                'label' => t('field.options', [], 'emsco-core'),
                'context' => UserOptionsType::CONTEXT_PROFILE,
            ])
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
            ]);

        $builder
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => t('field.wysiwyg_profile', [], 'emsco-core'),
                'class' => WysiwygProfile::class,
                'disabled' => !$allowToChangeWysiwygProfile,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC'),
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'wysiwyg-profile-picker',
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_token_id' => 'profile',
            'data_class' => User::class,
        ]);
    }
}
