<?php declare(strict_types=1);

namespace Topdata\TopdataErrorMonitorSW6\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Topdata\TopdataErrorMonitorSW6\Service\ErrorLoggerService;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ErrorLoggerService $errorLoggerService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Register with higher priority (e.g. 50) to log exceptions before they are consumed/modified
            KernelEvents::EXCEPTION => ['onKernelException', 50],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $this->errorLoggerService->log($exception);
    }
}