<?php

declare(strict_types=1);

namespace Jield\Search\Command;

use Jield\Search\Service\ConsoleService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SyncIndex extends Command
{
    /** @var string */
    protected static $defaultName = 'search:sync-index';

    public function __construct(private readonly ConsoleService $consoleService)
    {
        parent::__construct(name: self::$defaultName);
    }

    protected function configure(): void
    {
        $this->setName(name: self::$defaultName);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(messages: '<info>Syncing the search engine index</info>');
        $this->consoleService->syncIndex(output: $output);
        $output->writeln(messages: '');
        $output->writeln(messages: '<info>Synchronisation the search engine index completed</info>');

        return Command::SUCCESS;
    }
}
