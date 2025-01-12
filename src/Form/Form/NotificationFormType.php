<?php

namespace EMS\CoreBundle\Form\Form;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class NotificationFormType extends AbstractType
{
    public function __construct(private readonly EnvironmentService $service)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('template', EntityType::class, [
            'class' => Template::class,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('t')
            ->where("t.renderOption = 'notification'"),
            'choice_label' => fn ($value, $key, $index) => /* @var Template $value */
'<i class="'.$value->getContentType()->getIcon().' text-'.$value->getContentType()->getColor().'"></i>&nbsp;&nbsp;'.$value->getName().' for '.$value->getContentType()->getSingularName(),
            'multiple' => true,
            'required' => false,
            'attr' => ['class' => 'select2'],
        ])
        ->add('environment', ChoiceType::class, [
            'attr' => [
                'class' => 'select2',
            ],
            'multiple' => true,
            'choice_translation_domain' => false,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,

            'choices' => $this->service->getEnvironments(),
            'required' => false,
            'choice_label' => fn ($value, $key, $index) => '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getName(),
            'choice_value' => function ($value) {
                if (null != $value) {
                    return $value->getId();
                }

                return $value;
            },
        ])
        ->add('contentType', EntityType::class, [
            'class' => ContentType::class,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('ct')
            ->where('ct.deleted = :false')
            ->setParameters(['false' => false])
            ->orderBy('ct.orderKey'),
            'choice_label' => fn ($value, $key, $index) => '<i class="'.$value->getIcon().' text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getSingularName(),
            'multiple' => true,
            'required' => false,
            'attr' => ['class' => 'select2'],
        ])

        ->add('filter', SubmitEmsType::class, [
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            'attr' => ['class' => 'btn btn-primary btn-sm'],
            'icon' => 'fa fa-columns',
        ]);
    }
}
