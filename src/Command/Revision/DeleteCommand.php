<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends AbstractCommand
{
    protected static $defaultName = Commands::REVISION_DELETE;
    private const ARGUMENT_CONTENT_TYPES = 'contentTypes';
    private const OPTION_MODE = 'mode';

    private const MODE_ALL = 'all';
    private const MODE_OLDEST = 'oldest';

    private const MODES = [self::MODE_ALL, self::MODE_OLDEST];

    /** @var string[] */
    private array $contentTypeNames = [];
    private string $mode;

    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly ContentTypeService $contentTypeService,
        private readonly PublishService $publishService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Delete all/oldest revisions for content type(s)')
            ->addArgument(self::ARGUMENT_CONTENT_TYPES, InputArgument::IS_ARRAY, 'contentType names or "all"')
            ->addOption(self::OPTION_MODE, null, InputOption::VALUE_REQUIRED, 'mode for deletion [all,oldest]', 'all')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->choiceArgumentArray(
            self::ARGUMENT_CONTENT_TYPES,
            'Select one or more contentType(s)',
            $this->contentTypeService->getAllNames()
        );

        $this->contentTypeNames = $this->getArgumentStringArray(self::ARGUMENT_CONTENT_TYPES);
        $this->mode = $this->getOptionString(self::OPTION_MODE);

        if (!\in_array($this->mode, self::MODES)) {
            throw new \RuntimeException(\sprintf('Invalid option "%s"', $this->mode));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Revision - Delete');
        $this->io->note(\sprintf('Selected "%s" contentType(s)', \implode(',', $this->contentTypeNames)));

        $results = [];

        foreach ($this->contentTypeNames as $contentTypeName) {
            $contentType = $this->contentTypeService->giveByName($contentTypeName);
            $this->io->section(\sprintf('Content Type: %s', $contentTypeName));

            if (self::MODE_ALL === $this->mode) {
                $this->publishService->unpublishByContentTye($contentType);
                $result = $this->revisionService->deleteByContentType($contentType);
                $results[] = \sprintf('Deleted all %d "%s" revisions', $result, $contentTypeName);
            } elseif (self::MODE_OLDEST === $this->mode) {
                $result = $this->revisionService->deleteOldest($contentType);
                $results[] = \sprintf('Deleted oldest %d "%s" revisions', $result, $contentTypeName);
            }
        }

        if ($results) {
            $this->io->success($results);
        }

        return parent::EXECUTE_SUCCESS;
    }
}
