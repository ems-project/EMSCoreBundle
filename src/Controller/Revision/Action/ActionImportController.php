<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision\Action;

use EMS\CommonBundle\Contracts\File\FileReaderInterface;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use GuzzleHttp\Psr7\MimeType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Twig\Environment;

class ActionImportController
{
    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly AjaxService $ajax,
        private readonly FormFactory $formFactory,
        private readonly FileReaderInterface $fileReader,
        private readonly RevisionService $revisionService,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $templateNamespace,
    ) {
    }

    public function __invoke(Request $request, int $actionId, string $ouuid): Response
    {
        $action = $this->templateRepository->getById($actionId);
        $modal = $this->ajax->newAjaxModel("@$this->templateNamespace/action/modal_import.html.twig");

        if (null === $revision = $this->revisionService->get($ouuid, $action->giveContentType()->getName())) {
            throw new NotFoundHttpException(\sprintf('Revision not found for %s', $ouuid));
        }

        $form = $this->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->importData($action, $revision, $form->get('import_file')->getData())) {
                return $modal->getSuccessResponse();
            } else {
                $this->logger->error('log.contenttype.action.import.error.failed');
            }
        }

        return $modal
            ->setIcon($action->getIcon())
            ->setTitleRaw($action->getLabel())
            ->setBody('body', ['form' => $form->createView()])
            ->setFooter('footer')
            ->getResponse();
    }

    private function importData(Template $action, Revision $revision, UploadedFile $file): bool
    {
        $twigTemplate = $this->twig->createTemplate($action->getBody());

        /** @var array{type: string, field: string, columns: string[]} $config */
        $config = Json::decode($twigTemplate->renderBlock('config'));
        $this->configOptionsResolver()->resolve($config);

        if (null === $rows = $this->buildRows($file, $config['columns'])) {
            return false;
        }

        if ($twigTemplate->hasBlock('row')) {
            try {
                $rows = \array_map(fn ($row) => Json::decode($twigTemplate->renderBlock('row', ['row' => $row])), $rows);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['noFlash' => true]);

                return false;
            }
        }

        $importRawData = match ($config['type']) {
            'jsonMenuNested' => [$config['field'] => Json::encode($rows)],
            default => throw new \Exception(\sprintf('Not supported import type "%s"', $config['type'])),
        };

        $this->revisionService->updateRawData($revision, $importRawData);

        return true;
    }

    private function getForm(): FormInterface
    {
        return $this->formFactory->createBuilder(FormType::class, [])
            ->add('import_file', FileType::class, ['constraints' => [
                new Assert\NotBlank(),
                new Assert\File(['mimeTypes' => [
                    MimeType::fromExtension('xlsx'),
                    MimeType::fromExtension('csv'),
                ]]),
            ]])->getForm();
    }

    private function configOptionsResolver(): OptionsResolver
    {
        return (new OptionsResolver())
            ->setRequired(['type', 'field', 'columns'])
            ->setAllowedValues('type', ['jsonMenuNested'])
            ->setAllowedTypes('type', 'string')
            ->setAllowedTypes('field', 'string')
            ->setAllowedTypes('columns', 'array');
    }

    /**
     * @param string[] $columns
     *
     * @return ?array<mixed>
     */
    private function buildRows(UploadedFile $file, array $columns): ?array
    {
        $data = $this->fileReader->getData($file->getPathname());

        /** @var array<string, int> $dataColumns */
        $dataColumns = \array_flip(\array_filter($data[0], fn ($col) => \in_array($col, $columns)));

        if (\count($dataColumns) !== \count($columns)) {
            $this->logger->error('log.contenttype.action.import.error.missing_columns', [
                'columns' => \implode(', ', $columns),
            ]);

            return null;
        }

        if (\count($data) < 2) {
            $this->logger->error('log.contenttype.action.import.error.missing_data');

            return null;
        }

        $rows = \array_slice($data, 1);

        return \array_map(fn (array $row) => \array_map(fn (int $index) => $row[$index], $dataColumns), $rows);
    }
}
