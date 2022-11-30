<?php

namespace EMS\CoreBundle\Command\ContentType;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwitchDefaultCommand extends AbstractCommand
{
    public const CONTENT_TYPE_SWITCH_DEFAULT_ENV_USERNAME = 'CONTENT_TYPE_SWITCH_DEFAULT_ENV_USERNAME';
    protected static $defaultName = Commands::CONTENT_TYPE_SWITCH_DEFAULT_ENV;
    private EnvironmentService $environmentService;
    private ContentTypeService $contentTypeService;

    private ContentType $contentType;
    private Environment $target;

    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const ARGUMENT_TARGET_ENVIRONMENT = 'target-environment';

    public function __construct(EnvironmentService $environmentService, ContentTypeService $contentTypeService)
    {
        $this->environmentService = $environmentService;
        $this->contentTypeService = $contentTypeService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Switch the default environment for a given content type')
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'ContentType')
            ->addArgument(self::ARGUMENT_TARGET_ENVIRONMENT, InputArgument::REQUIRED, 'Target environment');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->target = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_TARGET_ENVIRONMENT));
        $this->contentType = $this->contentTypeService->giveByName($this->getArgumentString(self::ARGUMENT_CONTENT_TYPE));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->target === $this->contentType->giveEnvironment()) {
            $this->io->warning('The target environment is already the default environment');

            return self::EXECUTE_ERROR;
        }
        if (!$this->target->getManaged()) {
            $this->io->warning('The target environment is a managed environment');

            return self::EXECUTE_ERROR;
        }
        $sourceEnvironmentName = $this->contentType->giveEnvironment()->getName();
        $this->io->title(\sprintf('EMSCO - Switch the %s\'s default environment to %s', $this->contentType->getName(), $this->target->getName()));
        $this->contentTypeService->switchDefaultEnvironment($this->contentType, $this->target, self::CONTENT_TYPE_SWITCH_DEFAULT_ENV_USERNAME);
        $this->io->warning(\sprintf('If you are done with switching default environment, rebuild both environment now %s and %s', $sourceEnvironmentName, $this->contentType->giveEnvironment()));

        return self::EXECUTE_SUCCESS;
    }
}
