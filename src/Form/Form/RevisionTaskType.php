<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RevisionTaskType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', Type\TextType::class, ['required' => true])
            ->add('deadline', Type\TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'datetime-picker',
                    'data-date-format' => 'D/MM/YYYY',
                    'data-date-disabled-hours' => '[true]',
                ],
            ])
            ->add('assignee', SelectUserPropertyType::class, [
                'placeholder' => 'TEST',
                'required' => true,
                'allow_add' => false,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ])
            ->add('description', Type\TextareaType::class, [
                'required' => true,
                'attr' => ['rows' => 5],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskDTO::class,
        ]);
    }
}
