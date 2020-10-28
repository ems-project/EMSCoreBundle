<?php

namespace EMS\CoreBundle\Service;

use Swift_Mailer;
use Swift_Message;

class MailerService
{
    /** @var Swift_Mailer */
    private $mailer;

    public function __construct(Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param array<string> $emails
     * @param string $title
     * @param string $body
     */
    public function sendMail(array $emails, string $title, string $body): void
    {
        $message = (new Swift_Message());
        $message->setSubject($title)
            ->setFrom(getenv('EMS_FROM_EMAIL_ADDRESS'), getenv('EMS_FROM_EMAIL_NAME'))
            ->setTo($emails)
            ->setBody($body, 'text/html');

        $this->mailer->send($message);
    }
}
