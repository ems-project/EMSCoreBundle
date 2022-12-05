<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableFilters;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionTaskFiltersType extends AbstractType
{
    final public const NAME = 'filters';

    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', ChoiceType::class, [
            'required' => false,
            'multiple' => true,
            'attr' => ['class' => 'select2'],
            'choices' => [
                'In progress' => Task::STATUS_PROGRESS,
                'Completed' => Task::STATUS_COMPLETED,
            ],
        ]);

        $versionTags = $this->contentTypeService->getAllVersionTags();
        if (\count($versionTags) > 0) {
            $builder->add('version', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'attr' => ['class' => 'select2'],
                'choices' => \array_combine($versionTags, $versionTags),
            ]);
        }

        if (TaskManager::TAB_USER !== $options['tab']) {
            $builder->add('assignee', SelectUserPropertyType::class, [
                'required' => false,
                'allow_add' => false,
                'multiple' => true,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ]);
        }
        if (TaskManager::TAB_OWNER !== $options['tab']) {
            $builder->add('owner', SelectUserPropertyType::class, [
                'required' => false,
                'allow_add' => false,
                'multiple' => true,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ]);
        }
    }

    public function getBlockPrefix(): string
    {
        return self::NAME;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['tab'])
            ->setDefaults([
                'method' => Request::METHOD_GET,
                'data_class' => TaskTableFilters::class,
                'csrf_protection' => false,
                'allow_extra_fields' => true,
                'translation_domain' => false,
            ]);
    }
}
