<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mail;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TemplateWrapper;

final class MailTemplate
{
    private string $subject = 'subject';
    private string $body = 'body';

    /** @var array<mixed> */
    private array $to = [];
    /** @var array<mixed> */
    private array $cc = [];
    /** @var array<mixed> */
    private array $bcc = [];
    /** @var MailAttachment[] */
    private array $attachments = [];
    /** @var array<mixed> */
    private array $replyTo = [];

    public function __construct(private readonly TemplateWrapper $template, private readonly TranslatorInterface $translator, private readonly string $senderName)
    {
    }

    public function addTo(string $email, ?string $name = null): self
    {
        if ($name) {
            $this->to[$email] = $name;
        } else {
            $this->to[] = $email;
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getTo(): array
    {
        return $this->to;
    }

    public function addCc(string $email, ?string $name = null): self
    {
        if ($name) {
            $this->cc[$email] = $name;
        } else {
            $this->cc[] = $email;
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getCC(): array
    {
        return $this->cc;
    }

    public function addBCC(string $email, ?string $name = null): self
    {
        if ($name) {
            $this->bcc[$email] = $name;
        } else {
            $this->bcc[] = $email;
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getBCC(): array
    {
        return $this->bcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function setSubject(string $key, array $parameters = [], string $domain = EMSCoreBundle::TRANS_DOMAIN): self
    {
        $this->subject = $this->translator->trans($key, $parameters, $domain);

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function setSubjectBlock(string $block, array $context = []): self
    {
        $context['_senderName'] = $this->senderName;
        $this->subject = $this->template->renderBlock($block, $context);

        return $this;
    }

    public function setSubjectText(string $text): self
    {
        $this->subject = $text;

        return $this;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function setBody(string $key, array $parameters = []): self
    {
        $this->body = $this->translator->trans($key, $parameters, 'EMSCoreBundle');

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function setBodyBlock(string $block, array $context = []): self
    {
        $context['_senderName'] = $this->senderName;
        $this->body = $this->template->renderBlock($block, $context);

        return $this;
    }

    public function setBodyHtml(string $html): self
    {
        $this->body = $html;

        return $this;
    }

    public function addAttachment(string $attachmentFilename, ?string $name = null, ?string $contentType = null): self
    {
        $this->attachments[] = new MailAttachment($attachmentFilename, $name, $contentType);

        return $this;
    }

    /**
     * @return MailAttachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @return string[]
     */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function addReplyTo(string $email, ?string $name = null): self
    {
        if ($name) {
            $this->replyTo[$email] = $name;
        } else {
            $this->replyTo[] = $email;
        }

        return $this;
    }
}
