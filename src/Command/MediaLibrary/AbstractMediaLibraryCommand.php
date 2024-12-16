<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\MediaLibrary;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfigFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractMediaLibraryCommand extends AbstractCommand
{
    public const OPTION_HASH = 'hash';

    public function __construct(
        private readonly MediaLibraryConfigFactory $configFactory,
        protected readonly MediaLibraryService $mediaLibraryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(self::OPTION_HASH, null, InputOption::VALUE_REQUIRED, 'media config hash');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $hash = $this->getOptionString(self::OPTION_HASH);

        /** @var MediaLibraryConfig $config */
        $config = $this->configFactory->createFromHash($hash);
        $this->mediaLibraryService->setConfig($config);
    }
}
