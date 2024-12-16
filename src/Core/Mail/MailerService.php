<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mail;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class MailerService
{
    private readonly Address $from;

    /**
     * @param array{address: string, sender_name:string} $sender
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $templating,
        private readonly TranslatorInterface $translator,
        array $sender,
    ) {
        $this->from = new Address($sender['address'], $sender['sender_name']);
    }

    public function makeMailTemplate(string $templateName): MailTemplate
    {
        $template = $this->templating->load($templateName);

        return new MailTemplate($template, $this->translator, $this->from->getName());
    }

    /**
     * @param array<string> $emails
     */
    public function send(array $emails, string $title, string $body): void
    {
        $email = (new Email())
            ->from($this->from)
            ->to(...$emails)
            ->subject($title)
            ->text($body);

        $this->mailer->send($email);
    }

    public function sendMail(Email $email): void
    {
        if (0 === \count($email->getFrom())) {
            $email->from($this->from);
        }

        $this->mailer->send($email);
    }

    public function sendMailTemplate(MailTemplate $template, string $type = 'html'): void
    {
        $email = (new Email())
            ->from($this->from)
            ->subject($template->getSubject());
        if (\count($template->getTo()) > 0) {
            $email->to(...$template->getTo());
        }
        if (\count($template->getCC()) > 0) {
            $email->cc(...$template->getCC());
        }
        if (\count($template->getBCC()) > 0) {
            $email->bcc(...$template->getBCC());
        }
        if (\count($template->getReplyTo()) > 0) {
            $email->replyTo(...$template->getReplyTo());
        }
        foreach ($template->getAttachments() as $attachment) {
            $email->attachFromPath($attachment->getPath(), $attachment->getName(), $attachment->getContentType());
        }

        if ('html' === $type) {
            $email->html($template->getBody());
        } elseif ('text' === $type) {
            $email->text($template->getBody());
        }

        $this->mailer->send($email);
    }
}
