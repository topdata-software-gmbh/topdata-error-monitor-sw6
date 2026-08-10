<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Topdata\TopdataErrorMonitorSW6\Service\ErrorLoggerService;

class SendDailySummaryTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly ErrorLoggerService $errorLoggerService
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->errorLoggerService->sendDailySummary();
    }
}