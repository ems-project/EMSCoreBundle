<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use EMS\CoreBundle\EMSCoreBundle;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FlashMessageLogger extends AbstractProcessingHandler
{
    public function __construct(private readonly RequestStack $requestStack, private readonly TranslatorInterface $translator)
    {
        parent::__construct(Logger::NOTICE);
    }

    protected function write(LogRecord $record): void
    {
        $logArray = $record->toArray();
        if (null === $currentRequest = $this->requestStack->getCurrentRequest()) {
            return;
        }

        $headers = $currentRequest->headers;
        $logLevel = $headers->has('x-log-level') ? (int) $headers->get('x-log-level') : Logger::NOTICE;

        if ($logArray['level'] < $logLevel) {
            return;
        }

        if (true === ($logArray['context']['noFlash'] ?? false)) {
            return;
        }

        // TODO: remove the translator when all logger have been migrated to the localized logger
        $parameters = [];
        foreach ($logArray['context'] as $key => &$value) {
            $parameters['%'.$key.'%'] = $value;
        }

        $message = $this->translator->trans($logArray['message'], $parameters, EMSCoreBundle::TRANS_DOMAIN);

        /** @var Session $session */
        $session = $currentRequest->getSession();
        $session->getFlashBag()->add(\strtolower($logArray['level_name']), $message);
    }
}
