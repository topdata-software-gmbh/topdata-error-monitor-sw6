<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SendDailySummaryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'topdata_error_monitor.send_daily_summary';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 24 hours
    }
}