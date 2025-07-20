<?php

declare(strict_types=1);

namespace Jield\Search\Command;

use Jield\Search\Service\ConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:list-cores', description: 'List all cores in index')]
final class ListCores extends Command
{
    public function __construct(private readonly ConsoleService $consoleService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(messages: '<info>List of all cores in index</info>');
        $cores = $this->consoleService->getCores();

        foreach ($cores as $key => $core) {
            $output->writeln(
                messages: sprintf("Core for %s with service %s", $key, $core['service'])
            );
        }

        $output->writeln(messages: sprintf("<info>In total %d cores are active</info>", count($cores)));

        return Command::SUCCESS;
    }
}
