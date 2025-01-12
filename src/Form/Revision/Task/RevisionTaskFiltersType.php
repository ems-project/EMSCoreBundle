<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableContext;
use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableFilters;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class RevisionTaskFiltersType extends AbstractType
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', ChoiceType::class, [
            'required' => false,
            'multiple' => true,
            'attr' => ['class' => 'select2'],
            'choice_translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            'choices' => [
                'task.status.progress' => TaskStatus::PROGRESS->value,
                'task.status.rejected' => TaskStatus::REJECTED->value,
                'task.status.completed' => TaskStatus::COMPLETED->value,
                'task.status.planned' => TaskStatus::PLANNED->value,
                'task.status.approved' => TaskStatus::APPROVED->value,
            ],
        ]);

        if (TasksDataTableContext::TAB_USER !== $options['tab']) {
            $builder->add('assignee', SelectUserPropertyType::class, [
                'required' => false,
                'allow_add' => false,
                'multiple' => true,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ]);
        }
        if (TasksDataTableContext::TAB_REQUESTER !== $options['tab']) {
            $builder->add('requester', SelectUserPropertyType::class, [
                'required' => false,
                'allow_add' => false,
                'multiple' => true,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ]);
        }

        $versionTags = $this->contentTypeService->getVersionTags();
        if (\count($versionTags) > 0) {
            $builder->add('versionNextTag', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'attr' => ['class' => 'select2'],
                'choices' => $versionTags,
            ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'task.filter.submit',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'filters';
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['tab'])
            ->setDefaults([
                'method' => Request::METHOD_GET,
                'data_class' => TasksDataTableFilters::class,
                'csrf_protection' => false,
                'allow_extra_fields' => true,
                'translation_domain' => false,
            ]);
    }
}
