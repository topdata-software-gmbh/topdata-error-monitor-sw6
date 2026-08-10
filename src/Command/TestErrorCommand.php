<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataErrorMonitorSW6\Service\ErrorLoggerService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

#[AsCommand(
    name: 'topdata:error-monitor:test',
    description: 'Trigger simulated unhandled errors or run reports instantly for debugging verification'
)]
class TestErrorCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly ErrorLoggerService $errorLoggerService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('throw', 'e', InputOption::VALUE_NONE, 'Register a simulated RuntimeException inside the tracking database')
            ->addOption('send-summary', 's', InputOption::VALUE_NONE, 'Bypass interval constraints and execute the daily summary email reporting immediately');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        CliLogger::title('Topdata Error Monitor Test Suite');

        if ($input->getOption('throw')) {
            CliLogger::info('Simulating unhandled exception event logging...');
            try {
                throw new \RuntimeException('This is a simulated production exception from TopdataErrorMonitorSW6 command!');
            } catch (\RuntimeException $e) {
                $this->errorLoggerService->log($e);
                CliLogger::success('Successfully logged simulated exception to tdem_error_log.');
            }
            return self::SUCCESS;
        }

        if ($input->getOption('send-summary')) {
            CliLogger::info('Instructing Daily Summary Task to dispatch report...');
            try {
                $this->errorLoggerService->sendDailySummary();
                CliLogger::success('Daily summary report email triggered successfully.');
            } catch (\Throwable $e) {
                CliLogger::error('Could not complete reporting sequence: ' . $e->getMessage());
                return self::FAILURE;
            }
            return self::SUCCESS;
        }

        CliLogger::warning('No parameter specified. Invoke command with --throw (-e) or --send-summary (-s).');
        return self::INVALID;
    }
}