<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Nature\ImporterType;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\JobService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class ImporterViewType extends ViewType
{
    /** @var FileService */
    private $fileService;
    /** @var JobService */
    private $jobService;
    /** @var TokenStorageInterface */
    private $security;
    /** @var Router */
    private $router;

    public function __construct(FormFactory $formFactory, Environment $twig, Client $client, LoggerInterface $logger, FileService $fileService, JobService $jobService, TokenStorageInterface $security, Router $router)
    {
        parent::__construct($formFactory, $twig, $client, $logger);
        $this->fileService = $fileService;
        $this->jobService = $jobService;
        $this->security = $security;
        $this->router = $router;
    }

    public function getLabel(): string
    {
        return 'Importer: form to import a zip file containing JSON files';
    }

    public function getName(): string
    {
        return 'Importer';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('finalize', CheckboxType::class, [
                'data' => true,
                'required' => false,
            ])
            ->add('signData', CheckboxType::class, [
                'data' => true,
                'required' => false,
            ])
            ->add('businessKey', CheckboxType::class, [
                'data' => false,
                'required' => false,
            ])
            ->add('rawImport', CheckboxType::class, [
                'data' => false,
                'required' => false,
            ])
            ->add('force', CheckboxType::class, [
                'data' => false,
                'required' => false,
            ]);
    }

    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        return [];
    }

    public function generateResponse(View $view, Request $request): Response
    {
        $form = $this->formFactory->create(ImporterType::class, [
            'view' => $view,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $fileArray = $form->getData()['archive'];
            $filename = $this->fileService->getFile($fileArray[EmsFields::CONTENT_FILE_HASH_FIELD] ?? 'missing');

            $command = \sprintf(
                'ems:make:document %s %s%s%s%s%s%s',
                $view->getContentType()->getName(),
                $filename,
                $view->getOptions()['rawImport'] ?? false ? ' --raw' : '',
                $view->getOptions()['signData'] ?? true ? '' : ' --dont-sign-data',
                $view->getOptions()['force'] ?? false ? ' --force' : '',
                $view->getOptions()['finalize'] ?? true ? '' : ' --dont-finalize',
                $view->getOptions()['businessKey'] ?? false ? ' --businessKey' : ''
            );

            $user = $this->security->getToken()->getUser();
            if (!$user instanceof UserInterface) {
                throw new \RuntimeException('Unexpected user object');
            }

            $job = $this->jobService->createCommand($user, $command);

            return new  RedirectResponse($this->router->generate('job.status', [
                'job' => $job->getId(),
            ]));
        }

        $response = new Response();
        $response->setContent($this->twig->render('@EMSCore/view/custom/simple_form_view.html.twig', [
            'view' => $view,
            'form' => $form->createView(),
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
        ]));

        return $response;
    }
}
