<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use EMS\CoreBundle\EMSCoreBundle;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FlashMessageLogger extends AbstractProcessingHandler
{
    /** @var LogRecord[] */
    private array $logs = [];

    public function __construct(private readonly RequestStack $requestStack, private readonly TranslatorInterface $translator)
    {
        parent::__construct(Logger::NOTICE);
    }

    #[\Override]
    protected function write(LogRecord $record): void
    {
        if (null === $currentRequest = $this->requestStack->getCurrentRequest()) {
            return;
        }

        $headers = $currentRequest->headers;
        $logLevel = $headers->has('x-log-level') ? (int) $headers->get('x-log-level') : Logger::NOTICE;

        if ($record->level->value < $logLevel) {
            return;
        }

        if (true === ($record->context['noFlash'] ?? false)) {
            return;
        }

        if (!$currentRequest->hasSession(true)) {
            $this->logs[] = $record;

            return;
        }
        $message = $this->translate($record);

        /** @var Session $session */
        $session = $currentRequest->getSession();
        $level = \strtolower($record->level->getName());
        if (\in_array($message, $session->getFlashBag()->peek($level))) {
            return;
        }
        $session->getFlashBag()->add($level, $message);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function buildJsonResponse(array $extra): JsonResponse
    {
        $response = [
            'notice' => [],
            'warning' => [],
            'error' => [],
        ];
        $levels = ['notice', 'warning', 'error'];
        if (!empty($this->logs)) {
            foreach ($this->logs as $log) {
                $level = \strtolower($log->level->getName());
                if (!isset($response[$level])) {
                    continue;
                }
                $response[$level][] = $this->translate($log);
            }
        }
        $currentRequest = $this->requestStack->getCurrentRequest();
        if (null !== $currentRequest && $currentRequest->hasSession(true)) {
            /** @var Session $session */
            $session = $currentRequest->getSession();
            foreach ($levels as $level) {
                $response[$level] = $session->getFlashBag()->get($level);
            }
        }
        $response = \array_filter($response);
        $response['acknowledged'] = true;
        $response = \array_merge($response, $extra);
        $response['success'] ??= true;

        return new JsonResponse($response);
    }

    private function translate(LogRecord $record): string
    {
        // TODO: remove the translator when all logger have been migrated to the localized logger
        $parameters = [];
        foreach ($record->context as $key => $value) {
            $parameters['%'.$key.'%'] = $value;
        }

        return $this->translator->trans($record->message, $parameters, EMSCoreBundle::TRANS_DOMAIN);
    }
}
