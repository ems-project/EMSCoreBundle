<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Group;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class GroupType extends AbstractType
{
    public const string MODE_CREATE = 'create';
    public const string MODE_UPDATE = 'update';
    public const string UPDATE_BUTTON = 'update_button';
    public const string CREATE_BUTTON = 'create_button';

    public function __construct(private readonly UserService $userService)
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
            ->add('name', TextType::class, [
                'required' => true,
                'label' => t('field.name', [], 'emsco-core'),
            ])
            ->add('label', TextType::class, [
                'label' => t('field.label', [], 'emsco-core'),
            ])
            ->add('roles', ChoiceType::class, [
                'label' => t('field.roles', [], 'emsco-core'),
                'choices' => $this->userService->getExistingRoles(),
                'expanded' => true,
                'multiple' => true,
                'mapped' => true,
            ]);

        if (self::MODE_CREATE === $mode) {
            $builder->add(self::CREATE_BUTTON, SubmitEmsType::class, [
                'label' => t('action.add', [], 'emsco-core'),
                'attr' => ['class' => 'btn btn-primary btn-sm'],
                'icon' => 'fa fa-plus',
            ]);
        } elseif (self::MODE_UPDATE === $mode) {
            $builder->add(self::UPDATE_BUTTON, SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ]);
        } else {
            throw new \RuntimeException('Invalid mode');
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Group::class,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->setRequired(['mode'])
            ->setAllowedValues('mode', [self::MODE_CREATE, self::MODE_UPDATE])
        ;
    }
}
