<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Entity\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<mixed>
 */
class RevisionTaskHandleType extends AbstractType
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Task $task */
        $task = $options['task'];
        /** @var UserInterface $user */
        $user = $options['user'];

        if ($task->isStatus(TaskStatus::PROGRESS, TaskStatus::REJECTED) && $task->isAssignee($user)) {
            $builder
                ->add('comment', TextareaType::class, [
                    'attr' => ['rows' => 4],
                    'constraints' => !$task->isRequester($user) ? [new NotBlank()] : [],
                ])
                ->add('send', ButtonType::class);
        }

        if ($task->isStatus(TaskStatus::COMPLETED)
            && ($task->isRequester($user) || $this->security->isGranted('ROLE_TASK_MANAGER'))) {
            $builder
                ->add('comment', TextareaType::class, [
                    'attr' => ['rows' => 4],
                    'constraints' => 'reject' === $options['handle'] ? [new NotBlank()] : [],
                ])
                ->add('approve', ButtonType::class)
                ->add('reject', ButtonType::class);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['task', 'user', 'handle'])
            ->setAllowedValues('handle', [null, 'send', 'approve', 'reject'])
        ;
    }
}
