<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiscardDraftCommand extends AbstractCommand
{
    private const ARGUMENT_CONTENT_TYPES = 'content-types';
    private const OPTION_FORCE = 'force';
    private const OPTION_OLDER = 'older';
    private const DISCARD_DRAFT_COMMAND_USER = 'DISCARD_DRAFT_COMMAND_USER';
    protected static $defaultName = Commands::REVISION_DISCARD_DRAFT;
    private DataService $dataService;
    private RevisionService $revisionService;
    /**
     * @var string[]
     */
    private array $contentTypes;
    private bool $force;
    private \DateTimeInterface $olderThan;

    public function __construct(DataService $dataService, RevisionService $revisionService)
    {
        parent::__construct();
        $this->dataService = $dataService;
        $this->revisionService = $revisionService;
    }

    protected function configure(): void
    {
        $this->setDescription('Discard drafts for content types')
            ->addArgument(self::ARGUMENT_CONTENT_TYPES, InputArgument::IS_ARRAY, 'ContentType names')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Also discard drafts with auto-saved content')
            ->addOption(self::OPTION_OLDER, null, InputOption::VALUE_REQUIRED, 'Discard revision that are older than this  (time format)', '-5minutes');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->contentTypes = $this->getArgumentStringArray(self::ARGUMENT_CONTENT_TYPES);
        $this->force = $this->getOptionBool(self::OPTION_FORCE);
        $olderDateFormat = $this->getOptionString(self::OPTION_OLDER);
        $this->olderThan = DateTime::create($olderDateFormat);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Revision - Discard drafts');
        foreach ($this->contentTypes as $contentType) {
            $this->io->section(\sprintf('Discard %s drafts', $contentType));
            $this->discardDraftFor($contentType);
        }

        return parent::EXECUTE_SUCCESS;
    }

    private function discardDraftFor(string $contentType): void
    {
        foreach ($this->revisionService->findAllDraftsByContentTypeName($contentType) as $revision) {
            if (null !== $revision->getDraftSaveDate() && !$this->force) {
                continue;
            }
            if ($revision->isLocked() || $this->olderThan < $revision->getModified()) {
                continue;
            }
            $this->dataService->discardDraft($revision, true, self::DISCARD_DRAFT_COMMAND_USER);
        }
    }
}
