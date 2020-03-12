<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Service\Revision\Copy\CopyRequestFactory;
use EMS\CoreBundle\Service\Revision\Copy\CopyService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RevisionCopyCommand extends Command implements CommandInterface
{
    /** @var CopyRequestFactory */
    private $copyRequestFactory;
    /** @var CopyService */
    private $copyService;
    /** @var SymfonyStyle */
    private $io;

    protected static $defaultName = 'ems:revision:copy';

    private const ARG_ENVIRONMENT_NAME = 'environment';
    private const ARG_JSON_SEARCH_QUERY = 'json_search_query';

    public function __construct(CopyRequestFactory $copyRequestFactory, CopyService $copyService)
    {
        parent::__construct();
        $this->copyRequestFactory = $copyRequestFactory;
        $this->copyService = $copyService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Copy revisions from search query')
            ->addArgument(
                self::ARG_ENVIRONMENT_NAME,
                InputArgument::REQUIRED,
                'environment name'
            )
            ->addArgument(
                self::ARG_JSON_SEARCH_QUERY,
                InputArgument::REQUIRED,
                'JSON search query (escaped)'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Copy revisions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $copyRequest = $this->copyRequestFactory->fromJSON(
            $input->getArgument(self::ARG_ENVIRONMENT_NAME),
            $input->getArgument(self::ARG_JSON_SEARCH_QUERY)
        );

        $this->copyService->setLogger(new ConsoleLogger($output));
        $this->copyService->copy($copyRequest);
    }
}