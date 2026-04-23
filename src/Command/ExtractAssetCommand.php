<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\Helpers\Standard\Number;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: Commands::ASSET_EXTRACT,
    description: "Extracts data from all found files and loads it into the asset extractor service's cache",
    aliases: ['ems:asset:extract'],
    hidden: false
)]
class ExtractAssetCommand extends AbstractCoreCommand
{
    private const string ARG_PATH = 'path';
    private const string ARG_NAME = 'name';
    private const string OPTION_REPORT = 'report';

    public function __construct(
        protected LoggerInterface $logger,
        protected AssetExtractorService $extractorService,
        protected StorageManager $storageManager
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_PATH, InputArgument::REQUIRED, 'Path to the files to extract data from')
            ->addArgument(self::ARG_NAME, InputArgument::OPTIONAL, 'File pattern or file name i.e. *.pdf', '*.*')
            ->addOption(self::OPTION_REPORT, null, InputOption::VALUE_NONE, 'Print extract data report')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - asset - extract');

        $path = $this->getArgumentString(self::ARG_PATH);
        $name = $this->getArgumentString(self::ARG_NAME);

        $files = new Finder()->in($path)->name($name);

        $this->io->section('Extracting files');
        $this->extract($files);

        if ($this->getOptionBool(self::OPTION_REPORT)) {
            $this->io->section('Report');
            $this->report($files);
        }

        return self::EXECUTE_SUCCESS;
    }

    private function extract(Finder $files): void
    {
        $progress = $this->io->createProgressBar($files->count());
        $progress->start();

        foreach ($files as $file) {
            $filename = Type::string($file->getRealPath());
            $hash = $this->storageManager->computeFileHash($filename);

            if (!$this->storageManager->head($hash)) {
                $this->storageManager->saveFile($filename, StorageInterface::STORAGE_USAGE_ASSET);
            }

            $this->extractorService->extractMetaData($hash, $filename, true);

            $progress->advance();
        }

        $progress->finish();
        $this->io->newLine(2);
    }

    private function report(Finder $files): void
    {
        $rows = [];
        foreach ($files as $file) {
            $filename = Type::string($file->getRealPath());
            $hash = $this->storageManager->computeFileHash($filename);

            if (null === $extractedData = $this->extractorService->findCachedExtractedData($hash)) {
                throw new \RuntimeException('Extracted file not found');
            }

            $content = $extractedData->getContent(false);
            $rows[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'length' => \strlen($content),
                'words' => \str_word_count($content),
                'tokens' => $this->estimateTokens($content),
            ];
        }

        $totals = \array_reduce($rows, static function ($carry, $row) {
            $carry['size'] += $row['size'];
            $carry['length'] += $row['length'];
            $carry['words'] += $row['words'];
            $carry['tokens'] += $row['tokens'];

            return $carry;
        }, ['size' => 0, 'length' => 0, 'words' => 0, 'tokens' => 0]);

        $rows[] = new TableSeparator();
        $rows[] = ['name' => '<info>totals</info>', ...$totals];

        $this->io->table(['name', 'size', 'length', 'words', 'tokens'], \array_map(static fn ($row) => \is_array($row) ? [
            $row['name'],
            Number::formatBytes($row['size']),
            Number::format($row['length']),
            Number::format($row['words']),
            Number::format($row['tokens']),
        ] : $row, $rows));
    }

    private function estimateTokens(string $content): int
    {
        if (false === $words = \preg_split('/\s+/', \trim($content))) {
            throw new \RuntimeException('Invalid content');
        }

        $wordCount = \count($words);

        return (int) \ceil($wordCount * 1.33);
    }
}
