<?php

namespace EMS\CoreBundle\Service;

use Swift_Mailer;
use Swift_Message;

class MailerService
{
    /** @var array<string> */
    private array $fromMail;
    private string $fromName;
    private \Swift_Mailer $mailer;

    /**
     * @param array<string> $fromMail
     */
    public function __construct(Swift_Mailer $mailer, array $fromMail, string $fromName)
    {
        $this->mailer = $mailer;
        $this->fromMail = $fromMail;
        $this->fromName = $fromName;
    }

    /**
     * @param array<string> $emails
     */
    public function sendMail(array $emails, string $title, string $body): void
    {
        $message = (new Swift_Message());
        $message->setSubject($title)
            ->setFrom($this->fromMail, $this->fromName)
            ->setTo($emails)
            ->setBody($body, 'text/html');

        $this->mailer->send($message);
    }
}
