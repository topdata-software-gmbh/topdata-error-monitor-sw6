<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1723331100CreateErrorLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1723331100;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `tdem_error_log` (
    `id` BINARY(16) NOT NULL,
    `error_hash` VARCHAR(32) NOT NULL,
    `exception_class` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `file` VARCHAR(512) NOT NULL,
    `line` INT NOT NULL,
    `occurrence_count` INT NOT NULL DEFAULT 1,
    `first_seen_at` DATETIME(3) NOT NULL,
    `last_seen_at` DATETIME(3) NOT NULL,
    `notified` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.tdem_error_log.error_hash` (`error_hash`),
    INDEX `idx.tdem_error_log.last_seen_at` (`last_seen_at`),
    INDEX `idx.tdem_error_log.notified` (`notified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}