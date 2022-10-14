<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use EMS\CoreBundle\EMSCoreBundle;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FlashMessageLogger extends AbstractProcessingHandler
{
    private RequestStack $requestStack;
    private TranslatorInterface $translator;

    public function __construct(RequestStack $requestStack, TranslatorInterface $translator)
    {
        parent::__construct(Logger::NOTICE);
        $this->requestStack = $requestStack;
        $this->translator = $translator;
    }

    /**
     * @param array{level: int, level_name: string, message: string, context: array<mixed>} $record
     */
    protected function write(array $record): void
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if (null === $currentRequest || $record['level'] < Logger::NOTICE) {
            return;
        }

        // TODO: remove the translator when all logger have been migrated to the localized logger
        $parameters = [];
        foreach ($record['context'] as $key => &$value) {
            $parameters['%'.$key.'%'] = $value;
        }

        $message = $this->translator->trans($record['message'], $parameters, EMSCoreBundle::TRANS_DOMAIN);

        /** @var Session $session */
        $session = $currentRequest->getSession();
        $session->getFlashBag()->add(\strtolower($record['level_name']), $message);
    }
}
