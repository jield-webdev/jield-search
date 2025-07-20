<?php

declare(strict_types=1);

namespace Jield\Search\Command;

use Jield\Search\Service\ConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:test-index', description: 'Test the search engine index')]
final class TestIndex extends Command
{
    public function __construct(private readonly ConsoleService $consoleService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(name: 'reset', shortcut: 'r', mode: InputOption::VALUE_NONE, description: 'Reset index');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reset = $input->getOption(name: 'reset');

        $output->writeln(messages: '<info>Testing the search engine index</info>');

        $this->consoleService->testIndex(output: $output, clearIndex: $reset);

        $output->writeln(messages: '');
        $output->writeln(messages: '<info>Test the search engine index completed</info>');

        return Command::SUCCESS;
    }
}
