<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mail;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TemplateWrapper;

final class MailTemplate
{
    private TemplateWrapper $template;
    private TranslatorInterface $translator;
    private string $senderName;

    private string $subject = 'subject';
    private string $body = 'body';

    /** @var array<mixed> */
    private array $to = [];

    public function __construct(TemplateWrapper $template, TranslatorInterface $translator, string $senderName)
    {
        $this->template = $template;
        $this->translator = $translator;
        $this->senderName = $senderName;
    }

    public function addTo(string $email, string $name = null): self
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
}
