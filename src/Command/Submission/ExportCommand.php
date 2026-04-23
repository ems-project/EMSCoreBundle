<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Submission;

use EMS\CommonBundle\Common\Config\ConfigResolver;
use EMS\CoreBundle\Command\AbstractCoreCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Submission\ExportConfig;
use EMS\CoreBundle\Core\Submission\SubmissionExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::SUBMISSION_EXPORT,
    description: 'Extract form submissions',
    hidden: false
)]
class ExportCommand extends AbstractCoreCommand
{
    public const string MAIL_TEMPLATE = '@EMSCore/email/submissions-export.html.twig';
    public const string ARGUMENT_CONFIG = 'config-file';
    private ExportConfig $exportConfig;

    public function __construct(
        private readonly SubmissionExporter $exporter,
        private readonly ConfigResolver $configResolver,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_CONFIG, InputArgument::REQUIRED, 'JSON config file (path, file hash or JSON string)');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->exportConfig = $this->getExportConfig();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Exporting form submissions');

        $result = $this->exporter->export($this->exportConfig);

        if (0 === $result->exportCount) {
            $this->io->warning(\sprintf('No exported submissions on %d unprocessed submissions. No file or emails were generated.', $result->unprocessedSubmissionsCount));

            return self::EXECUTE_SUCCESS;
        }

        $this->io->success(\sprintf('Exported %d submissions on %d unprocessed submissions.', $result->exportCount, $result->unprocessedSubmissionsCount));

        return self::EXECUTE_SUCCESS;
    }

    private function getExportConfig(): ExportConfig
    {
        $input = $this->getArgumentString(self::ARGUMENT_CONFIG);
        $config = $this->configResolver->resolve($input);

        return ExportConfig::fromJson($config);
    }
}
