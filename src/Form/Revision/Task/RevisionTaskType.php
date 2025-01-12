<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\ContentType\ContentTypeSettings;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class RevisionTaskType extends AbstractType
{
    public function __construct(private readonly string $coreDatepickerFormat)
    {
    }

    /**
     * @param FormBuilderInterface<mixed>                                                    $builder
     * @param array{'data': TaskDTO, 'task_status': TaskStatus, 'content_type': ContentType} $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $taskDto = $options['data'];
        $taskStatus = $options['task_status'];

        $this->addTitle($builder, $options['content_type']);
        $builder
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

        if (null === $taskDto->id || TaskStatus::PLANNED === $taskStatus) {
            $builder->add('delay', IntegerType::class, [
                'label' => 'task.field.delay',
                'attr' => ['min' => 0],
            ]);
        } else {
            $builder->add('deadline', TextType::class, [
                'disabled' => TaskStatus::COMPLETED === $taskStatus,
                'label' => 'task.field.deadline',
                'attr' => [
                    'readonly' => TaskStatus::COMPLETED === $taskStatus,
                    'class' => 'datetime-picker',
                    'data-date-format' => $this->coreDatepickerFormat,
                    'data-date-disabled-hours' => '[true]',
                ],
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['content_type'])
            ->setAllowedTypes('content_type', ContentType::class)
            ->setDefaults([
                'data_class' => TaskDTO::class,
                'translation_domain' => 'EMSCoreBundle',
                'task_status' => null,
            ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function addTitle(FormBuilderInterface $builder, ContentType $contentType): void
    {
        $tasksTitles = $contentType->getSettings()->getSettingArrayString(ContentTypeSettings::TASKS_TITLES);

        if (0 === \count($tasksTitles)) {
            $builder->add('title', TextType::class, ['label' => 'task.field.title']);

            return;
        }

        $choiceTitles = \array_combine($tasksTitles, $tasksTitles);
        $formModifier = static function (FormInterface $form, ?string $title) use ($choiceTitles) {
            if ($title && !\array_key_exists($title, $choiceTitles)) {
                $choiceTitles[$title] = $title;
            }

            $form->add('title', ChoiceType::class, [
                'label' => 'task.field.title',
                'attr' => ['data-tags' => true, 'class' => 'select2'],
                'choices' => $choiceTitles,
                'multiple' => false,
                'choice_translation_domain' => false,
            ]);
        };

        $builder->getEventDispatcher()->addListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            /** @var TaskDTO $data */
            $data = $event->getData();
            $formModifier($event->getForm(), $data->title);
        });
        $builder->getEventDispatcher()->addListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $title = $event->getData()['title'] ?? null;
            $formModifier($event->getForm(), $title);
        });
    }
}
