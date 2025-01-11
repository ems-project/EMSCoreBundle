<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISION_DISCARD_DRAFT,
    description: 'Discard drafts for content types.',
    hidden: false
)]
class DiscardDraftCommand extends AbstractCommand
{
    private const string ARGUMENT_CONTENT_TYPES = 'content-types';
    private const string OPTION_FORCE = 'force';
    private const string OPTION_OLDER = 'older';
    private const string DISCARD_DRAFT_COMMAND_USER = 'DISCARD_DRAFT_COMMAND_USER';
    /**
     * @var string[]
     */
    private array $contentTypes;
    private bool $force;
    private \DateTimeInterface $olderThan;

    public function __construct(private readonly DataService $dataService, private readonly RevisionService $revisionService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPES, InputArgument::IS_ARRAY, 'ContentType names')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Also discard drafts with auto-saved content')
            ->addOption(self::OPTION_OLDER, null, InputOption::VALUE_REQUIRED, 'Discard revision that are older than this  (time format)', '-5minutes');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->contentTypes = $this->getArgumentStringArray(self::ARGUMENT_CONTENT_TYPES);
        $this->force = $this->getOptionBool(self::OPTION_FORCE);
        $olderDateFormat = $this->getOptionString(self::OPTION_OLDER);
        $this->olderThan = DateTime::create($olderDateFormat);
    }

    #[\Override]
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
