<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ErrorLoggerService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
        private readonly MailerInterface $mailer
    ) {}

    public function log(\Throwable $exception): void
    {
        try {
            $class = get_class($exception);
            $message = $exception->getMessage();
            $file = $exception->getFile();
            $line = $exception->getLine();

            // Compute MD5 hash of unique error signature
            $hash = md5($class . $file . $line);
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

            // Find existing error group
            $existing = $this->connection->fetchAssociative(
                'SELECT id, occurrence_count FROM tdem_error_log WHERE error_hash = :hash',
                ['hash' => $hash]
            );

            if ($existing) {
                $this->connection->executeStatement(
                    'UPDATE tdem_error_log 
                     SET occurrence_count = occurrence_count + 1, 
                         last_seen_at = :now, 
                         notified = 0 
                     WHERE id = :id',
                    ['now' => $now, 'id' => $existing['id']]
                );
            } else {
                $this->connection->executeStatement(
                    'INSERT INTO tdem_error_log (id, error_hash, exception_class, message, file, line, occurrence_count, first_seen_at, last_seen_at, notified)
                     VALUES (:id, :hash, :class, :message, :file, :line, 1, :now, :now, 0)',
                    [
                        'id' => Uuid::randomBytes(),
                        'hash' => $hash,
                        'class' => $class,
                        'message' => $message,
                        'file' => $file,
                        'line' => $line,
                        'now' => $now,
                    ]
                );
            }

            // Evaluate spike alerts
            $this->checkSpikeThreshold();
        } catch (\Throwable $e) {
            // Self-defensive block: Never let logger errors crash the main application thread
        }
    }

    private function checkSpikeThreshold(): void
    {
        $enabled = $this->systemConfigService->get('TopdataErrorMonitorSW6.config.spikeEnabled');
        if (!$enabled) {
            return;
        }

        $emailAddress = $this->systemConfigService->get('TopdataErrorMonitorSW6.config.notificationEmail');
        if (empty($emailAddress)) {
            return;
        }

        $threshold = (int) $this->systemConfigService->get('TopdataErrorMonitorSW6.config.spikeThreshold') ?: 50;
        $intervalMinutes = (int) $this->systemConfigService->get('TopdataErrorMonitorSW6.config.spikeInterval') ?: 15;
        $cooldownMinutes = (int) $this->systemConfigService->get('TopdataErrorMonitorSW6.config.spikeCooldown') ?: 60;

        // Query total error hits inside the evaluation window
        $cutoff = (new \DateTimeImmutable("-{$intervalMinutes} minutes"))->format('Y-m-d H:i:s.v');
        $errorCount = (int) $this->connection->fetchOne(
            'SELECT SUM(occurrence_count) FROM tdem_error_log WHERE last_seen_at > :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($errorCount >= $threshold) {
            // Apply notification cooldown limits
            $lastSent = $this->systemConfigService->get('TopdataErrorMonitorSW6.config.lastSpikeSentAt');
            if ($lastSent) {
                $lastSentTime = new \DateTimeImmutable($lastSent);
                $cooldownTime = $lastSentTime->modify("+{$cooldownMinutes} minutes");
                if (new \DateTimeImmutable() < $cooldownTime) {
                    return; // Cooldown active
                }
            }

            $this->sendSpikeEmail($emailAddress, $errorCount, $intervalMinutes);

            $this->systemConfigService->set(
                'TopdataErrorMonitorSW6.config.lastSpikeSentAt',
                (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            );
        }
    }

    private function sendSpikeEmail(string $toEmail, int $errorCount, int $intervalMinutes): void
    {
        $email = (new Email())
            ->from('no-reply@topdata.de')
            ->to($toEmail)
            ->subject('⚠️ Shopware 6 Error Spike Detected!')
            ->html(sprintf(
                '<div style="font-family: sans-serif; line-height: 1.5; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #d9534f; margin-top: 0;">Warning: High Error Frequency Detected</h2>
                    <p>Your Shopware 6 production instance has logged <strong>%d</strong> unhandled exceptions within the last <strong>%d</strong> minutes.</p>
                    <p>This behavior is typically seen right after theme builds, database migrations, plugin updates, or payment provider failures.</p>
                    <p>Please check your server immediately to diagnose the root cause.</p>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="font-size: 11px; color: #777;">This alert was automatically compiled and sent by Topdata Error Monitor.</p>
                 </div>',
                $errorCount,
                $intervalMinutes
            ));

        $this->mailer->send($email);
    }

    public function sendDailySummary(): void
    {
        $enabled = $this->systemConfigService->get('TopdataErrorMonitorSW6.config.summaryEnabled');
        if (!$enabled) {
            return;
        }

        $emailAddress = $this->systemConfigService->get('TopdataErrorMonitorSW6.config.notificationEmail');
        if (empty($emailAddress)) {
            return;
        }

        // Fetch errors logged in the last 24 hours
        $cutoff = (new \DateTimeImmutable("-24 hours"))->format('Y-m-d H:i:s.v');
        $errors = $this->connection->fetchAllAssociative(
            'SELECT exception_class, message, file, line, occurrence_count, last_seen_at 
             FROM tdem_error_log 
             WHERE last_seen_at > :cutoff 
             ORDER BY occurrence_count DESC',
            ['cutoff' => $cutoff]
        );

        if (empty($errors)) {
            return; // Quiet day
        }

        $rowsHtml = '';
        foreach ($errors as $error) {
            $rowsHtml .= sprintf(
                '<tr>
                    <td style="padding: 10px; border: 1px solid #ddd; word-break: break-all; font-family: monospace; font-size: 11px;"><strong>%s</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-family: sans-serif;">%s</td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 10px; color: #555;">%s:%d</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-weight: bold;">%d</td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-family: sans-serif; font-size: 11px;">%s</td>
                 </tr>',
                htmlspecialchars($error['exception_class']),
                htmlspecialchars(mb_strimwidth($error['message'], 0, 150, '...')),
                htmlspecialchars($error['file']),
                $error['line'],
                $error['occurrence_count'],
                htmlspecialchars($error['last_seen_at'])
            );
        }

        $htmlContent = sprintf(
            '<div style="font-family: sans-serif; color: #333; padding: 20px;">
                <h2 style="color: #2e6da4;">📊 Shopware 6 Daily Error Summary</h2>
                <p>The following unhandled exceptions were intercepted on your shop within the past 24 hours:</p>
                <table style="width: 100%%; border-collapse: collapse; font-size: 13px; margin-top: 20px;">
                    <thead>
                        <tr style="background-color: #f8f9fa;">
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Exception</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Message</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">File:Line</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Hits</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Last Occurrence</th>
                        </tr>
                    </thead>
                    <tbody>
                        %s
                    </tbody>
                </table>
                <p style="margin-top: 30px; font-size: 11px; color: #777;">This daily report was compiled and sent automatically by the Topdata Error Monitor plugin.</p>
             </div>',
            $rowsHtml
        );

        $email = (new Email())
            ->from('no-reply@topdata.de')
            ->to($emailAddress)
            ->subject('📊 Shopware 6 Daily Error Summary Report')
            ->html($htmlContent);

        $this->mailer->send($email);

        // Housekeeping: Purge logs older than 30 days to limit space consumption
        $thirtyDaysAgo = (new \DateTimeImmutable("-30 days"))->format('Y-m-d H:i:s.v');
        $this->connection->executeStatement(
            'DELETE FROM tdem_error_log WHERE last_seen_at < :thirtyDaysAgo',
            ['thirtyDaysAgo' => $thirtyDaysAgo]
        );
    }
}