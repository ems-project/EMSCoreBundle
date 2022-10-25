<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableFilters;
use EMS\CoreBundle\Entity\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionTaskFiltersType extends AbstractType
{
    public const NAME = 'revision-task-filters';

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', ChoiceType::class, [
            'required' => false,
            'placeholder' => 'Select a status',
            'choices' => [
                'In progress' => Task::STATUS_PROGRESS,
                'Completed' => Task::STATUS_COMPLETED,
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return self::NAME;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => Request::METHOD_GET,
            'data_class' => TaskTableFilters::class,
            'csrf_protection' => false,
        ]);
    }
}
