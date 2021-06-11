<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ContentType;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Command\LockCommand;
use EMS\CoreBundle\Command\UnlockRevisionsCommand;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use PhpCsFixer\Tokenizer\TransformerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TransformCommand extends AbstractCommand
{
    private ContentTypeService $contentTypeService;
    private ContentType $contentType;
    private ContentTransformer $contentTransformer;
    private RevisionService $revisionService;
    /** @var array<mixed> */
    private array $search = [];
    private int $batchSize;

    private const USER = 'TRANSFORM_CONTENT';

    public const name = 'ems:contenttype:transform';
    protected static $defaultName = self::name;

    public function __construct(
        ContentTypeService $contentTypeService,
        ContentTransformer $contentTransformer,
        RevisionService $revisionService,
        int $defaultBulkSize)
    {
        parent::__construct();
        $this->contentTypeService = $contentTypeService;
        $this->contentTransformer = $contentTransformer;
        $this->revisionService = $revisionService;
        $this->batchSize = $defaultBulkSize;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('content-type', InputArgument::REQUIRED, 'ContentType name')
            ->addOption('batch-size', '', InputOption::VALUE_REQUIRED, 'db records batch size', 'default_bulk_size')
            ->addOption('ouuid', '', InputOption::VALUE_REQUIRED, 'revision ouuid')
            ->addOption('dry-run', '', InputOption::VALUE_NONE, 'dry run')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('Transform content-type');

        $batchSize = \intval($input->getOption('batch-size'));
        if ($batchSize > 0) {
            $this->batchSize = $batchSize;
        }

        $contentTypeName = \strval($input->getArgument('content-type'));
        $this->contentType = $this->contentTypeService->giveByName($contentTypeName);

        $this->search = [
            'lockBy' => ContentTransformer::USER,
            'contentType' => $this->contentType,
            'ouuid' => $this->getOptionStringNull('ouuid'),
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transformerDefinitions = $this->contentTransformer->getTransformerDefinitions($this->contentType);
        if (0 === \count($transformerDefinitions)) {
            $this->io->warning('No transformers defined!');

            return 1;
        }

        if (false === $validate = $this->validateTransformerDefinitions($transformerDefinitions)) {
            $this->io->error('Transformers are not valid defined!');

            return 1;
        }

        if ($dryRun = $this->getOptionBool('dry-run')) {
            $this->io->note('Dry run enabled, no database changes');
        }

        $this->io->section('Locking');
        $this->executeCommand(
            LockCommand::name,
            ['theme_document', '+1day', '--user='.ContentTransformer::USER, '--force']
        );

        $this->io->section('Transforming');
        $progressBar = $this->io->createProgressBar();
        $revisions = $this->revisionService->search($this->search);
        $transformation = $this->contentTransformer->transform($revisions, $transformerDefinitions, $this->batchSize, $dryRun);

        $transformed = 0;
        foreach ($transformation as list($ouuid, $result)) {
            if ($result) {
                ++$transformed;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->io->newLine(2);

        $this->io->section('Unlock');
        $this->executeCommand(
            UnlockRevisionsCommand::name,
            [ContentTransformer::USER, $this->contentType->getName()]
        );

        if ($dryRun) {
            $this->io->warning(\sprintf('%d revisions', $transformed));
        } else {
            $this->io->success(\sprintf('Transformed %d revisions', $transformed));
        }

        return 0;
    }

    /**
     * @param array<mixed> $transformerDefinitions
     */
    private function validateTransformerDefinitions(array $transformerDefinitions): bool
    {
        $this->io->section('Validate transformers');
        $valid = true;

        foreach ($transformerDefinitions as $field => $definitions) {
            foreach ($definitions as $definition) {
                /** @var TransformerInterface $transformer */
                $transformer = $definition['transformer'];

                $this->io->definitionList(
                    ['Field' => $field],
                    ['Name' => $transformer->getName()],
                    ['Config' => $definition['config']],
                    ['Valid Config' => $definition['valid_config'] ?: 'Yes'],
                );

                if ($definition['valid_config']) {
                    $valid = false;
                }
            }
        }

        return $valid;
    }
}
