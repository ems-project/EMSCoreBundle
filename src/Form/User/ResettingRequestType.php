<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\User;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ResettingRequestType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username_email', null, [
                'constraints' => [new NotBlank()],
                'label' => t('user.resetting.username_email', [], 'emsco-core'),
            ])
            ->add('submit', SubmitType::class, [
                'label' => t('user.resetting.title', [], 'emsco-core'),
            ])
        ;
    }
}
