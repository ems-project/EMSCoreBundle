<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Submission;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ProcessType extends AbstractType
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('submissionId', HiddenType::class, [
                'constraints' => new NotBlank(),
            ])
            ->add('save', SubmitType::class)
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'method' => 'POST',
            'translation_domain' => false,
            'action' => $this->router->generate('form.submissions.process'),
        ]);
    }
}
