<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TemplateWrapper;

final class AjaxModal
{
    private TemplateWrapper $template;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;

    private ?string $title = null;
    private ?string $body = null;
    private ?string $footer = null;
    private bool $success = false;

    /** @var array<mixed> */
    private array $messages = [];

    public function __construct(TemplateWrapper $template, TranslatorInterface $translator, LoggerInterface $logger)
    {
        $this->template = $template;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function addMessageSuccess(string $key, array $parameters = []): self
    {
        $this->success = true;

        return $this->addMessage('success', $key, $parameters);
    }

    /**
     * @param array<mixed> $parameters
     */
    public function addMessageError(string $key, array $parameters = [], \Throwable $exception = null): self
    {
        if (null !== $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        return $this->addMessage('error', $key, $parameters);
    }

    /**
     * @param array<mixed> $parameters
     */
    public function setTitle(string $key, array $parameters = []): self
    {
        $this->title = $this->translator->trans($key, $parameters, 'EMSCoreBundle');

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function setBody(string $block, array $context = []): self
    {
        $this->body = $this->template->renderBlock($block, $context);

        return $this;
    }

    public function setBodyHtml(string $html): self
    {
        $this->body = $html;

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function setFooter(string $block, array $context = []): self
    {
        $this->footer = $this->template->renderBlock($block, $context);

        return $this;
    }

    public function setFooterHtml(string $html): self
    {
        $this->footer = $html;

        return $this;
    }

    public function getResponse(): JsonResponse
    {
        return new JsonResponse(\array_filter([
            'modalMessages' => $this->messages,
            'modalTitle' => $this->title,
            'modalBody' => $this->body,
            'modalFooter' => $this->footer,
            'modalSuccess' => $this->success,
        ], fn ($value) => null !== $value));
    }

    /**
     * @param array<mixed> $parameters
     */
    private function addMessage(string $type, string $key, array $parameters = []): self
    {
        $this->messages[] = [$type => $this->translator->trans($key, $parameters, 'EMSCoreBundle')];

        return $this;
    }
}
