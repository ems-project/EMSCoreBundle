<?php

namespace EMS\CoreBundle\Service;

use Swift_Mailer;
use Swift_Message;

class MailerService
{
    const EMAIL_FROM = 'reporting@elasticms.test';
    const NAME_FROM = 'ElasticMS';

    public function __construct(Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendMail($emails, $title, $body)
    {
        $message = (new Swift_Message());
        $message->setSubject($title)
            ->setFrom(self::EMAIL_FROM, self::NAME_FROM)
            ->setTo($emails)
            ->setBody($body, 'text/html');

        $this->mailer->send($message);
    }
}
