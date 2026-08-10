<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'topdata:error-monitor:example',
    description: 'Example command for ErrorMonitorSW6 plugin'
)]
class ExampleCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>ErrorMonitorSW6 plugin example command executed successfully!</info>');
        $output->writeln('This is a minimal example command to get you started.');
        
        return Command::SUCCESS;
    }
}