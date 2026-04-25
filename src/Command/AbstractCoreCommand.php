<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCoreCommand extends AbstractCommand
{
    public const string OPTION_USERNAME = 'username';
    private ?string $username;
    private bool $initialized = false;

    public function __construct()
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
    }

    public function addUsernameOption(?string $defaultValue = null, ?int $mode = InputOption::VALUE_OPTIONAL): void
    {
        if ($this->initialized) {
            throw new \RuntimeException('The command has been already initialized, this method must be called in the from the configure() method.');
        }
        $this->addOption(
            self::OPTION_USERNAME,
            'u',
            $mode,
            'elasticMS\'s username',
            $defaultValue
        );
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->initialized = true;
        parent::initialize($input, $output);

        if ($input->hasOption(self::OPTION_USERNAME)) {
            $this->username = $this->getOptionStringNull(self::OPTION_USERNAME);
        }
    }

    public function getUsername(): string
    {
        return Type::string($this->username);
    }

    public function hasUsername(): bool
    {
        return null !== $this->username;
    }

    protected function addDeprecatedUsernameOption(string $optionName = 'user', ?string $shortcut = null, ?string $default = null): void
    {
        $this->addOption(
            $optionName,
            $shortcut,
            InputOption::VALUE_REQUIRED,
            \sprintf('Deprecated, use --%s instead. This option will be removed in elasticMS 8.x', self::OPTION_USERNAME),
            $default
        );
    }

    protected function handleDeprecatedUsernameOption(InputInterface $input, string $optionName = 'user', ?string $shortcut = null): void
    {
        if (!$input->hasParameterOption('--'.$optionName, true) && !($shortcut && $input->hasParameterOption('-'.$shortcut, true))) {
            return;
        }

        @\trigger_error(\sprintf('Option "--%s" is deprecated, use "--%s" instead.', $optionName, self::OPTION_USERNAME), \E_USER_DEPRECATED);
        $this->username = $this->getOptionString($optionName);
    }
}
