<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class Export extends AbstractType implements DashboardInterface
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
            ->add('filename', CodeEditorType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
                'max-lines' => 5,
                'min-lines' => 5,
            ])
            ->add('mimetype', null, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('fileDisposition', ChoiceType::class, [
                'expanded' => true,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
                'choices' => [
                    'dashboard.export.none' => null,
                    'dashboard.export.attachment' => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'dashboard.export.inline' => ResponseHeaderBag::DISPOSITION_INLINE,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_format' => 'dashboard.export.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }

    public function getResponse(Dashboard $dashboard): Response
    {
        $response = new Response();
        try {
            $template = $this->twig->createTemplate($dashboard->getOptions()['body'] ?? '', \sprintf('Body template for dashboard %s', $dashboard->getName()));
            $response->setContent($this->twig->render($template, [
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));

            $filename = $dashboard->getOptions()['filename'] ?? 'filename';
            $disposition = $dashboard->getOptions()['fileDisposition'] ?? 'text/text';
            $mimetype = $dashboard->getOptions()['mimetype'] ?? null;
            if (\is_string($mimetype)) {
                $response->headers->set('Content-Type', $mimetype);
            }
            $disposition = $response->headers->makeDisposition($disposition, $filename);
            $response->headers->set('Content-Disposition', $disposition);
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
