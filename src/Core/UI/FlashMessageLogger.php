<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use EMS\CoreBundle\EMSCoreBundle;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FlashMessageLogger extends AbstractProcessingHandler
{
    private FlashBagInterface $flashBag;
    private TranslatorInterface $translator;

    public function __construct(FlashBagInterface $flashBag, TranslatorInterface $translator)
    {
        parent::__construct(Logger::NOTICE);
        $this->flashBag = $flashBag;
        $this->translator = $translator;
    }

    /**
     * @param array<mixed> $record
     */
    protected function write(array $record): void
    {
        if ($record['level'] < Logger::NOTICE) {
            return;
        }

        $parameters = [];
        foreach ($record['context'] as $key => &$value) {
            $parameterKey = 'count' === $key ? $key : '%'.$key.'%';
            $parameters[$parameterKey] = $value;
        }

        $message = $this->translator->trans($record['message'], $parameters, EMSCoreBundle::TRANS_DOMAIN);
        $this->flashBag->add(\strtolower($record['level_name']), $message);
    }
}
