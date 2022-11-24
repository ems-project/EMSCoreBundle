<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class Template extends AbstractType implements DashboardInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('header', CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('footer', CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_format' => 'dashboard.template.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }

    public function getResponse(Dashboard $dashboard): Response
    {
        $response = new Response();
        try {
            $response->setContent($this->twig->render('@EMSCore/dashboard/services/template.html.twig', [
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));
        } catch (\Throwable $e) {
            $response->setContent($this->twig->render('@EMSCore/dashboard/services/error.html.twig', [
                'exception' => $e,
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
