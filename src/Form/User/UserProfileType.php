<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\User;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Field\CodeEditorType;
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

class UserProfileType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', null, ['label' => 'user.display_name'])
            ->add('email', EmailType::class, ['label' => 'user.email'])
            ->add('emailNotification', CheckboxType::class, [
                'label' => 'user.email_notification',
                'required' => false,
            ])
            ->add('current_password', PasswordType::class, [
                'label' => 'user.current_password',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                    new UserPassword(['message' => 'user.current_password.invalid']),
                ],
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('layoutBoxed', null, ['label' => 'user.layout_boxed'])
            ->add('sidebarMini', null, ['label' => 'user.sidebar_mini'])
            ->add('sidebarCollapse', null, ['label' => 'user.sidebar_collapse'])
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
            ->remove('username');

        $builder
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => 'user.wysiwyg_profile',
                'class' => 'EMSCoreBundle:WysiwygProfile',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC');
                },
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'wysiwyg-profile-picker',
                ],
            ])
            ->add('wysiwygOptions', CodeEditorType::class, [
                'label' => 'user.wysiwyg_options',
                'required' => false,
                'language' => 'ace/mode/json',
                'attr' => [
                    'class' => 'wysiwyg-profile-options',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_token_id' => 'profile',
            'data_class' => User::class,
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'validation_groups' => ['Profile', 'Default'],
        ]);
    }
}
