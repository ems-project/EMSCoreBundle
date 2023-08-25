<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
        /** @var TaskDTO $taskDto */
        $taskDto = $options['data'];

        $builder
            ->add('title', TextType::class, ['label' => 'task.field.title'])
            ->add('assignee', SelectUserPropertyType::class, [
                'placeholder' => '',
                'label' => 'task.field.assignee',
                'allow_add' => false,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'task.field.description',
                'attr' => ['rows' => 5],
            ])
        ;

        if (null === $taskDto->id || Task::STATUS_PLANNED === $options['task_status']) {
            $builder->add('delay', IntegerType::class, [
                'label' => 'task.field.delay',
                'attr' => ['min' => 0],
            ]);
        } else {
            $builder->add('deadline', TextType::class, [
                'disabled' => true,
                'label' => 'task.field.deadline',
                'attr' => ['readonly' => true],
            ]);
        }
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
