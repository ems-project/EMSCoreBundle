<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Submission;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\Helpers\Html\MimeTypes;
use EMS\Helpers\Standard\Base64;
use EMS\Helpers\Standard\DateTime;
use EMS\SubmissionBundle\Request\DatabaseRequest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::GENERATE_DUMMY_SUBMISSIONS,
    description: 'Generates dummy submissions',
    hidden: false
)]
class GenerateDummySubmissionsCommand extends AbstractCommand
{
    public const string ARGUMENT_COUNT = 'count';
    private int $count;

    public function __construct(
        private FormSubmissionService $formSubmissionService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_COUNT, InputArgument::REQUIRED, 'Number of dummy submissions to generate');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->count = $this->getArgumentInt(self::ARGUMENT_COUNT);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section(\sprintf('Generate %d form submissions', $this->count));
        $this->io->progressStart($this->count);
        for ($i = 0; $i < $this->count; ++$i) {
            $this->formSubmissionService->submit(new DatabaseRequest([
                'form_name' => 'dummy',
                'instance' => 'dummy',
                'label' => 'dummy',
                'expire_date' => DateTime::create('now')->format('c'),
                'locale' => 'en',
                'data' => [
                    'foobar' => true,
                    'body' => 'Lorem ipsum',
                ],
                'files' => [[
                    'filename' => 'foobar.txt',
                    'mimeType' => MimeTypes::TEXT_PLAIN->value,
                    'base64' => Base64::encode('foobar'),
                    'size' => 6,
                    'form_field' => 'attachement',
                ]],
            ]));
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        return self::EXECUTE_SUCCESS;
    }
}
