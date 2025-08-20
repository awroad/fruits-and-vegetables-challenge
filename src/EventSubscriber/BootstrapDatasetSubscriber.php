<?php
namespace App\EventSubscriber;

use App\Application\RequestJsonImporter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BootstrapDatasetSubscriber implements EventSubscriberInterface
{
    private RequestJsonImporter $importer;
    private string $bootstrapFile;

    public function __construct(RequestJsonImporter $importer, string $bootstrapFile)
    {
        $this->importer = $importer;
        $this->bootstrapFile = $bootstrapFile;
    }

    public static function getSubscribedEvents(): array
    {
        // early enough, but after the container boot
        return [KernelEvents::REQUEST => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // only for main requests (no subrequests)
        if (!$event->isMainRequest()) {
            return;
        }
        // one-time import (importer additionally protects itself)
        $this->importer->loadOnceFromFile($this->bootstrapFile);
    }
}
