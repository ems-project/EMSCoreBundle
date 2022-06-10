<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mail;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class MailerService
{
    private \Swift_Mailer $mailer;
    private Environment $templating;
    private TranslatorInterface $translator;
    /** @var array{address: string, sender_name:string} */
    private array $sender;

    /**
     * @param array{address: string, sender_name:string} $sender
     */
    public function __construct(
        \Swift_Mailer $mailer,
        Environment $templating,
        TranslatorInterface $translator,
        array $sender
    ) {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->translator = $translator;
        $this->sender = $sender;
    }

    public function makeMailTemplate(string $templateName): MailTemplate
    {
        $template = $this->templating->load($templateName);

        return new MailTemplate($template, $this->translator, $this->sender['sender_name']);
    }

    /**
     * @param array<string> $emails
     */
    public function send(array $emails, string $title, string $body): void
    {
        $message = (new \Swift_Message());
        $message->setSubject($title)
            ->setFrom($this->sender['address'], $this->sender['sender_name'])
            ->setTo($emails)
            ->setBody($body, 'text/html');

        $this->mailer->send($message);
    }

    public function sendMailTemplate(MailTemplate $template, string $contentType = 'text/html'): void
    {
        $message = (new \Swift_Message());
        $message
            ->setSubject($template->getSubject())
            ->setFrom($this->sender['address'], $this->sender['sender_name'])
            ->setTo($template->getTo())
            ->setBody($template->getBody(), $contentType);

        $this->mailer->send($message);
    }
}
