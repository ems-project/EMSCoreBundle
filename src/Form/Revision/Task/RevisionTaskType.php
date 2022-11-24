<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RevisionTaskType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'attr' => ['readonly' => Task::STATUS_COMPLETED == $options['task_status']],
                'disabled' => Task::STATUS_COMPLETED == $options['task_status'],
                'label' => 'task.field.title',
            ])
            ->add('deadline', TextType::class, [
                'disabled' => Task::STATUS_COMPLETED == $options['task_status'],
                'label' => 'task.field.deadline',
                'attr' => [
                    'readonly' => Task::STATUS_COMPLETED == $options['task_status'],
                    'class' => 'datetime-picker',
                    'data-date-format' => 'D/MM/YYYY',
                    'data-date-disabled-hours' => '[true]',
                ],
            ])
            ->add('assignee', SelectUserPropertyType::class, [
                'disabled' => Task::STATUS_COMPLETED == $options['task_status'],
                'placeholder' => '',
                'label' => 'task.field.assignee',
                'allow_add' => false,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ])
            ->add('description', TextareaType::class, [
                'disabled' => Task::STATUS_COMPLETED == $options['task_status'],
                'label' => 'task.field.description',
                'attr' => ['rows' => 5],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskDTO::class,
            'translation_domain' => 'EMSCoreBundle',
            'task_status' => null,
        ]);
    }
}
