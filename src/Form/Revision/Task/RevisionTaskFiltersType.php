<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableContext;
use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableFilters;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class RevisionTaskFiltersType extends AbstractType
{
    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', ChoiceType::class, [
            'label' => t('task.filter.status', [], 'emsco-core'),
            'required' => false,
            'multiple' => true,
            'attr' => ['class' => 'select2'],
            'choices' => [
                TaskStatus::PROGRESS->trans($this->translator) => TaskStatus::PROGRESS->value,
                TaskStatus::REJECTED->trans($this->translator) => TaskStatus::REJECTED->value,
                TaskStatus::COMPLETED->trans($this->translator) => TaskStatus::COMPLETED->value,
                TaskStatus::PLANNED->trans($this->translator) => TaskStatus::PLANNED->value,
                TaskStatus::APPROVED->trans($this->translator) => TaskStatus::APPROVED->value,
            ],
        ]);

        if (TasksDataTableContext::TAB_USER !== $options['tab']) {
            $builder->add('assignee', SelectUserPropertyType::class, [
                'label' => t('task.filter.assignee', [], 'emsco-core'),
                'required' => false,
                'allow_add' => false,
                'multiple' => true,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ]);
        }
        if (TasksDataTableContext::TAB_REQUESTER !== $options['tab']) {
            $builder->add('requester', SelectUserPropertyType::class, [
                'label' => t('task.filter.requester', [], 'emsco-core'),
                'required' => false,
                'allow_add' => false,
                'multiple' => true,
                'user_property' => 'username',
                'label_property' => 'displayName',
            ]);
        }

        $versionTags = $this->contentTypeService->getVersionTags();
        if ([] !== $versionTags) {
            $builder->add('versionNextTag', ChoiceType::class, [
                'label' => t('task.filter.version_next_tag', [], 'emsco-core'),
                'required' => false,
                'multiple' => true,
                'attr' => ['class' => 'select2'],
                'choices' => $versionTags,
            ]);
        }

        if ($this->authorizationChecker->isGranted('ROLE_PUBLISHER')) {
            $builder->add('isOverdue', CheckboxType::class, [
                'required' => false,
                'label' => t('task.filter.is_overdue', [], 'emsco-core'),
            ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => t('task.filter.submit', [], 'emsco-core'),
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
