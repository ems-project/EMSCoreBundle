<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISIONS_UNLOCK,
    description: 'Unlock revisions for a user.',
    aliases: ['ems:revisions:unlock'],
    hidden: false
)]
final class UnlockCommand extends AbstractCommand
{
    private const string ARGUMENT_USERNAME = 'username';
    private const string ARGUMENT_CONTENT_TYPE = 'content-type';
    private const string OPTION_ALL = 'all';
    private ?string $contentTypeName = null;
    private string $username;
    private ?bool $all = null;

    public function __construct(
        private readonly DataService $dataService,
        private readonly ContentTypeService $contentTypeService
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARGUMENT_USERNAME,
                InputArgument::OPTIONAL,
                'The username of the locking user.'
            )
            ->addArgument(
                self::ARGUMENT_CONTENT_TYPE,
                InputArgument::OPTIONAL,
                'The content type name. Instead you can target ALL content-types with the "--all" option.'
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                'If set, all the content-types will be unlocked for the user.'
            )
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->all = $this->getOptionBool(self::OPTION_ALL);
        $this->username = $this->getArgumentString(self::ARGUMENT_USERNAME, 'Username of the locking user');
        if ($this->all) {
            $this->contentTypeName = $this->getArgumentStringNull(self::ARGUMENT_CONTENT_TYPE);
        } else {
            $this->choiceArgumentString(self::ARGUMENT_CONTENT_TYPE, 'Select an existing content type', $this->contentTypeService->getAllNames());
            $this->contentTypeName = $this->getArgumentString(self::ARGUMENT_CONTENT_TYPE);
        }
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title(\sprintf('Unlock revisions for user %s', $this->username));

        if (null !== $this->contentTypeName && !$this->all) {
            $contentType = $this->contentTypeService->giveByName($this->contentTypeName);
            $count = $this->dataService->unlockRevisions($contentType, Type::string($this->username));
        } elseif ($this->all && null === $this->contentTypeName) {
            $count = $this->dataService->unlockAllRevisions(Type::string($this->username));
        } else {
            $this->io->error('Exactly one of the following must be specified: --all or a Content Type name.');

            return self::INVALID;
        }
        $this->io->success(\sprintf('%s revisions have been unlocked', $count));

        return self::SUCCESS;
    }
}
